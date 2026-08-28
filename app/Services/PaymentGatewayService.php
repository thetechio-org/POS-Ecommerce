<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Log;

class PaymentGatewayService
{
    /**
     * Build the JazzCash redirect payload for a sale.
     * Customer is redirected to JazzCash with these POST params.
     */
    public function buildJazzCashPayload(Sale $sale): array
    {
        $merchantId   = config('payment.jazzcash.merchant_id');
        $password     = config('payment.jazzcash.password');
        $integritySalt = config('payment.jazzcash.integrity_salt');
        $returnUrl    = config('payment.jazzcash.return_url');

        $txDateTime   = now()->format('Ymd His');
        $txRefNo      = 'T' . now()->format('YmdHis') . $sale->id;
        $amount       = (int) ($sale->final_amount * 100); // in paisas

        $hashString   = implode('&', [
            $integritySalt,
            $amount,
            '',       // BillReference
            '',       // CNIC
            '',       // CustomerEmailAddress
            '',       // CustomerMobileNo
            $merchantId,
            $password,
            $returnUrl,
            $txDateTime,
            $txRefNo,
            'PKR',
            'MWALLET',
        ]);

        $secureHash = hash_hmac('sha256', $hashString, $integritySalt);

        return [
            'pp_Version'            => '1.1',
            'pp_TxnType'            => 'MWALLET',
            'pp_Language'           => 'EN',
            'pp_MerchantID'         => $merchantId,
            'pp_Password'           => $password,
            'pp_TxnRefNo'           => $txRefNo,
            'pp_Amount'             => $amount,
            'pp_TxnCurrency'        => 'PKR',
            'pp_TxnDateTime'        => $txDateTime,
            'pp_BillReference'      => 'Order-' . $sale->invoice_number,
            'pp_Description'        => 'Payment for order ' . $sale->invoice_number,
            'pp_TxnExpiryDateTime'  => now()->addHour()->format('Ymd His'),
            'pp_ReturnURL'          => $returnUrl,
            'pp_SecureHash'         => $secureHash,
            'ppmpf_1'               => $sale->id,
        ];
    }

    /**
     * Process JazzCash/EasyPaisa callback and record the transaction.
     *
     * This endpoint is public (the gateway posts to it directly and cannot carry
     * a CSRF token), so the gateway's own signature is the ONLY thing proving the
     * request is genuine. Verification therefore happens before anything else and
     * fails closed — never relax this without replacing it with an equivalent check.
     *
     * @throws \RuntimeException when the callback cannot be trusted.
     */
    public function handleCallback(array $data, string $gateway): PaymentTransaction
    {
        // 1. Authenticate the caller. Nothing below runs for an unsigned request.
        if (! $this->verifySignature($data, $gateway)) {
            Log::warning('Rejected payment callback with an invalid signature', [
                'gateway' => $gateway,
                'sale_id' => $data['ppmpf_1'] ?? $data['sale_id'] ?? null,
                'txn_ref' => $data['pp_TxnRefNo'] ?? $data['orderRefNum'] ?? null,
            ]);

            throw new \RuntimeException('Payment callback signature verification failed.');
        }

        $saleId = $data['ppmpf_1'] ?? $data['sale_id'] ?? null;
        $sale   = Sale::findOrFail($saleId);

        // 2. Idempotency: a retried callback returns the original transaction untouched.
        $transactionId = $data['pp_TxnRefNo'] ?? $data['transactionId'] ?? $data['orderRefNum'] ?? null;
        if ($transactionId) {
            $existing = PaymentTransaction::where('transaction_id', $transactionId)->first();
            if ($existing) {
                return $existing;
            }
        }

        $responseCode = $data['pp_ResponseCode'] ?? $data['responseCode'] ?? '999';
        $status       = ($responseCode === '000') ? 'success' : 'failed';

        // 3. A signed success still has to be for the right amount, or it is not a
        //    payment for this order. Record it as failed rather than crediting it.
        if ($status === 'success' && ! $this->amountMatches($data, $sale, $gateway)) {
            Log::warning('Payment callback amount did not match the sale total', [
                'gateway'  => $gateway,
                'sale_id'  => $sale->id,
                'expected' => $sale->final_amount,
            ]);

            $status = 'failed';
        }

        $transaction = PaymentTransaction::create([
            'sale_id'              => $sale->id,
            'gateway'              => $gateway,
            'transaction_id'       => $transactionId,
            'pp_response_code'     => $responseCode,
            'pp_response_message'  => $data['pp_ResponseMessage'] ?? $data['responseMessage'] ?? null,
            'amount'               => $sale->final_amount,
            'status'               => $status,
            'gateway_payload'      => $data,
        ]);

        // 4. Only a verified, correctly-priced success settles the sale.
        if ($status === 'success') {
            $sale->update([
                'paid_amount' => $sale->final_amount,
                'due_amount'  => 0,
                'status'      => 'confirmed',
            ]);
        }

        return $transaction;
    }

    /**
     * Verify that the callback really came from the payment gateway.
     */
    private function verifySignature(array $data, string $gateway): bool
    {
        return match ($gateway) {
            'jazzcash'  => $this->verifyJazzCashSignature($data),
            'easypaisa' => $this->verifyEasyPaisaSignature($data),
            default     => false,
        };
    }

    /**
     * JazzCash returns pp_SecureHash: an HMAC-SHA256, keyed with the integrity salt,
     * over the salt followed by every non-empty pp_* / ppmpf_* field in key order.
     */
    private function verifyJazzCashSignature(array $data): bool
    {
        $salt     = (string) config('payment.jazzcash.integrity_salt');
        $received = (string) ($data['pp_SecureHash'] ?? '');

        if ($salt === '' || $received === '') {
            return false;
        }

        $fields = [];
        foreach ($data as $key => $value) {
            if ($key === 'pp_SecureHash') {
                continue;
            }
            if (! str_starts_with($key, 'pp_') && ! str_starts_with($key, 'ppmpf_')) {
                continue;
            }
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }
            $fields[$key] = $value;
        }

        ksort($fields);

        $expected = hash_hmac('sha256', $salt . '&' . implode('&', $fields), $salt);

        return hash_equals(strtolower($expected), strtolower($received));
    }

    /**
     * EasyPaisa returns the same SHA-256 digest that was sent out, over
     * storeId + amount + orderRefNum + postBackURL + hashKey.
     *
     * NOTE: EasyPaisa's response contract varies by merchant account type. If your
     * account posts back a different field set, align this method with the hash
     * specification in your merchant integration guide — but keep it failing closed.
     */
    private function verifyEasyPaisaSignature(array $data): bool
    {
        $hashKey  = (string) config('payment.easypaisa.hash_key');
        $received = (string) ($data['signature'] ?? $data['pp_SecureHash'] ?? '');

        if ($hashKey === '' || $received === '') {
            return false;
        }

        $storeId     = (string) config('payment.easypaisa.store_id');
        $postBackUrl = (string) config('payment.easypaisa.return_url');
        $amount      = (string) ($data['amount'] ?? $data['transactionAmount'] ?? '');
        $orderRefNum = (string) ($data['orderRefNum'] ?? $data['orderRefNumber'] ?? '');

        if ($amount === '' || $orderRefNum === '') {
            return false;
        }

        $expected = strtoupper(hash('sha256', $storeId . $amount . $orderRefNum . $postBackUrl . $hashKey));

        return hash_equals($expected, strtoupper($received));
    }

    /**
     * Confirm the gateway charged the amount this sale is actually for.
     * JazzCash reports paisas; EasyPaisa reports rupees.
     */
    private function amountMatches(array $data, Sale $sale, string $gateway): bool
    {
        if ($gateway === 'jazzcash') {
            $reported = $data['pp_Amount'] ?? null;

            // Absent amount: the signature already covered the payload, so accept.
            return $reported === null
                || (int) $reported === (int) round($sale->final_amount * 100);
        }

        $reported = $data['amount'] ?? $data['transactionAmount'] ?? null;

        return $reported === null
            || abs((float) $reported - (float) $sale->final_amount) < 0.01;
    }

    /**
     * Build EasyPaisa redirect payload for a sale.
     */
    public function buildEasyPaisaPayload(Sale $sale): array
    {
        $storeId    = config('payment.easypaisa.store_id');
        $hashKey    = config('payment.easypaisa.hash_key');
        $returnUrl  = config('payment.easypaisa.return_url');
        $orderId    = 'EP-' . $sale->id . '-' . time();
        $amount     = number_format($sale->final_amount, 2, '.', '');
        $postBackUrl = $returnUrl;

        $hashData   = $storeId . $amount . $orderId . $postBackUrl . $hashKey;
        $hash       = strtoupper(hash('sha256', $hashData));

        return [
            'storeId'    => $storeId,
            'amount'     => $amount,
            'postBackURL' => $postBackUrl,
            'orderRefNum' => $orderId,
            'autoRedirect' => 1,
            'signature'  => $hash,
            'store_name' => config('app.name'),
            'ppmpf_1'    => $sale->id,
        ];
    }
}
