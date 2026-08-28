<?php

use App\Models\Sale;
use App\Services\PaymentGatewayService;

/**
 * The gateway callbacks are public by necessity — the gateway posts to them
 * directly and cannot carry a CSRF token. The gateway's signature is therefore
 * the only thing proving a callback is genuine, so it must fail closed.
 */
beforeEach(function () {
    config(['payment.jazzcash.integrity_salt' => 'TESTSALT123']);

    $this->service = new PaymentGatewayService();
});

function signJazzCash(array $data, string $salt = 'TESTSALT123'): array
{
    $fields = array_filter(
        $data,
        fn ($v, $k) => $k !== 'pp_SecureHash' && $v !== '' && $v !== null,
        ARRAY_FILTER_USE_BOTH
    );
    ksort($fields);

    $data['pp_SecureHash'] = hash_hmac('sha256', $salt . '&' . implode('&', $fields), $salt);

    return $data;
}

it('rejects a callback that carries no signature', function () {
    $this->service->handleCallback(
        ['ppmpf_1' => 1, 'pp_ResponseCode' => '000'],
        'jazzcash'
    );
})->throws(RuntimeException::class);

it('rejects a callback signed with the wrong salt', function () {
    $forged = signJazzCash(
        ['pp_Amount' => '10000', 'pp_ResponseCode' => '000', 'pp_TxnRefNo' => 'T1', 'ppmpf_1' => '1'],
        'WRONGSALT'
    );

    $this->service->handleCallback($forged, 'jazzcash');
})->throws(RuntimeException::class);

it('rejects a callback whose fields were altered after signing', function () {
    $data = signJazzCash(
        ['pp_Amount' => '10000', 'pp_ResponseCode' => '999', 'pp_TxnRefNo' => 'T2', 'ppmpf_1' => '1']
    );

    $data['pp_ResponseCode'] = '000';   // "failed" rewritten to "succeeded"

    $this->service->handleCallback($data, 'jazzcash');
})->throws(RuntimeException::class);

it('rejects a callback for an unknown gateway', function () {
    $this->service->handleCallback(['ppmpf_1' => 1], 'some-other-gateway');
})->throws(RuntimeException::class);

it('records a failure when a signed callback reports the wrong amount', function () {
    $sale = Sale::create([
        'customer_id'    => null,
        'branch_id'      => null,
        'invoice_number' => 'INV-AMT',
        'sale_date'      => now(),
        'total_amount'   => 1000,
        'final_amount'   => 1000,
        'paid_amount'    => 0,
        'due_amount'     => 1000,
        'payment_method' => 'cash',
        'sale_origin'    => 'E-commerce',
        'status'         => 'pending',
        'created_by'     => null,
    ]);

    // Correctly signed, but for Rs. 1 rather than the Rs. 1000 owed.
    $data = signJazzCash([
        'pp_Amount'       => '100',
        'pp_ResponseCode' => '000',
        'pp_TxnRefNo'     => 'T-AMT',
        'ppmpf_1'         => (string) $sale->id,
    ]);

    $transaction = $this->service->handleCallback($data, 'jazzcash');

    expect($transaction->status)->toBe('failed')
        ->and((float) $sale->fresh()->paid_amount)->toBe(0.0)
        ->and($sale->fresh()->status)->toBe('pending');
});

it('settles the sale for a correctly signed callback with the right amount', function () {
    $sale = Sale::create([
        'customer_id'    => null,
        'branch_id'      => null,
        'invoice_number' => 'INV-OK',
        'sale_date'      => now(),
        'total_amount'   => 1000,
        'final_amount'   => 1000,
        'paid_amount'    => 0,
        'due_amount'     => 1000,
        'payment_method' => 'cash',
        'sale_origin'    => 'E-commerce',
        'status'         => 'pending',
        'created_by'     => null,
    ]);

    $data = signJazzCash([
        'pp_Amount'       => '100000',   // paisas
        'pp_ResponseCode' => '000',
        'pp_TxnRefNo'     => 'T-OK',
        'ppmpf_1'         => (string) $sale->id,
    ]);

    $transaction = $this->service->handleCallback($data, 'jazzcash');

    expect($transaction->status)->toBe('success')
        ->and((float) $sale->fresh()->paid_amount)->toBe(1000.0)
        ->and((float) $sale->fresh()->due_amount)->toBe(0.0)
        ->and($sale->fresh()->status)->toBe('confirmed');
});

it('returns the original transaction when the gateway retries the same callback', function () {
    $sale = Sale::create([
        'customer_id'    => null,
        'branch_id'      => null,
        'invoice_number' => 'INV-DUP',
        'sale_date'      => now(),
        'total_amount'   => 1000,
        'final_amount'   => 1000,
        'paid_amount'    => 0,
        'due_amount'     => 1000,
        'payment_method' => 'cash',
        'sale_origin'    => 'E-commerce',
        'status'         => 'pending',
        'created_by'     => null,
    ]);

    $data = signJazzCash([
        'pp_Amount'       => '100000',
        'pp_ResponseCode' => '000',
        'pp_TxnRefNo'     => 'T-DUP',
        'ppmpf_1'         => (string) $sale->id,
    ]);

    $first  = $this->service->handleCallback($data, 'jazzcash');
    $second = $this->service->handleCallback($data, 'jazzcash');

    expect($second->id)->toBe($first->id)
        ->and(App\Models\PaymentTransaction::count())->toBe(1);
});

it('exempts the callback path from CSRF without weakening it anywhere else', function () {
    // CSRF verification is skipped automatically in the testing environment, so
    // without this the assertion would pass even with the exemption broken.
    $this->app->detectEnvironment(fn () => 'production');

    // The gateway cannot send a token, so this must not be a 419.
    $this->post('/store/payment/jazzcash/callback', ['ppmpf_1' => 1, 'pp_ResponseCode' => '000'])
        ->assertStatus(302);

    // Every other route must still reject a POST with no token.
    $this->post('/store/cart/add', ['product_id' => 1])
        ->assertStatus(419);
});
