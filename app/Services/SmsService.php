<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send an SMS via the configured Pakistani SMS gateway.
     *
     * Configure in .env:
     *   SMS_GATEWAY=smspk            (smspk | ecosms)
     *   SMSPK_API_KEY=...            SMSPK_SENDER_ID=YourBrand
     *   ECOSMS_USERNAME=...          ECOSMS_PASSWORD=...   ECOSMS_SENDER_ID=YourBrand
     */
    public function send(string $phone, string $message): bool
    {
        $gateway = config('services.sms.gateway', 'smspk');

        if (! $this->isConfigured($gateway)) {
            Log::info("SMS not sent — the '{$gateway}' gateway has no credentials configured. To: {$phone} | {$message}");

            return false;
        }

        $phone = $this->normalizePhone($phone);

        try {
            return match ($gateway) {
                'ecosms' => $this->sendViaEcoSMS($phone, $message),
                default  => $this->sendViaSMSPK($phone, $message),
            };
        } catch (\Throwable $e) {
            Log::warning('SMS send failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Each gateway authenticates differently, so check the credentials that the
     * selected one actually needs.
     */
    private function isConfigured(string $gateway): bool
    {
        if ($gateway === 'ecosms') {
            return (bool) config('services.sms.ecosms.username')
                && (bool) config('services.sms.ecosms.password');
        }

        return (bool) config('services.sms.smspk.api_key');
    }

    /**
     * A short-fused HTTP client.
     *
     * SMS goes out inline during checkout and order-status changes, so a gateway
     * that stops responding must not hold a PHP-FPM worker open. The client's
     * default is a 30-second wait; on a shared server, enough workers stuck for
     * 30 seconds will exhaust the pool and take neighbouring sites down with it.
     */
    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::connectTimeout(3)->timeout(5);
    }

    private function sendViaSMSPK(string $phone, string $message): bool
    {
        $response = $this->http()->get('https://api.smspk.net/sms/send', [
            'api_key'   => config('services.sms.smspk.api_key'),
            'to'        => $phone,
            'message'   => $message,
            'sender_id' => config('services.sms.smspk.sender_id', 'POS'),
        ]);

        return $response->successful();
    }

    private function sendViaEcoSMS(string $phone, string $message): bool
    {
        $response = $this->http()->post('https://www.ecosms.pk/api/sendsms', [
            'username' => config('services.sms.ecosms.username'),
            'password' => config('services.sms.ecosms.password'),
            'sender'   => config('services.sms.ecosms.sender_id', 'POS'),
            'number'   => $phone,
            'message'  => $message,
        ]);

        return $response->successful();
    }

    /**
     * Normalize phone to international Pakistani format: 923XXXXXXXXX
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '92'))  return $phone;
        if (str_starts_with($phone, '0'))   return '92' . substr($phone, 1);
        if (str_starts_with($phone, '3'))   return '92' . $phone;

        return $phone;
    }

    // ── Convenience methods ──────────────────────────────────────────────────

    public function sendOrderPlaced(string $phone, string $invoiceNo, float $amount): bool
    {
        $symbol  = \App\Models\Setting::first()?->currency_symbol ?? 'Rs';
        $message = "Thank you for your order! Invoice: {$invoiceNo} | Amount: {$symbol} {$amount}. We'll notify you once it's confirmed.";
        return $this->send($phone, $message);
    }

    public function sendOrderStatusUpdated(string $phone, string $invoiceNo, string $status): bool
    {
        $message = "Your order {$invoiceNo} status has been updated to: " . strtoupper($status) . ". Visit the website to track your order.";
        return $this->send($phone, $message);
    }

    public function sendPaymentReceived(string $phone, string $invoiceNo, float $amount): bool
    {
        $symbol  = \App\Models\Setting::first()?->currency_symbol ?? 'Rs';
        $message = "Payment of {$symbol} {$amount} received for order {$invoiceNo}. Thank you!";
        return $this->send($phone, $message);
    }
}
