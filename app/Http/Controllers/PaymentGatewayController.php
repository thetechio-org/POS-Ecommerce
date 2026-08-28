<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentGatewayController extends Controller
{
    public function __construct(protected PaymentGatewayService $gateway) {}

    /**
     * Redirect customer to JazzCash payment page.
     */
    public function redirectJazzCash(Sale $sale)
    {
        $payload  = $this->gateway->buildJazzCashPayload($sale);
        $endpoint = config('payment.jazzcash.endpoint');

        return view('store.payment.jazzcash-redirect', compact('payload', 'endpoint'));
    }

    /**
     * Redirect customer to EasyPaisa payment page.
     */
    public function redirectEasyPaisa(Sale $sale)
    {
        $payload  = $this->gateway->buildEasyPaisaPayload($sale);
        $endpoint = config('payment.easypaisa.endpoint');

        return view('store.payment.easypaisa-redirect', compact('payload', 'endpoint'));
    }

    /**
     * Handle JazzCash callback (customer redirect back after payment).
     */
    public function callbackJazzCash(Request $request)
    {
        try {
            $transaction = $this->gateway->handleCallback($request->all(), 'jazzcash');
            $status      = $transaction->status;
        } catch (\Exception $e) {
            Log::error('JazzCash callback error', ['error' => $e->getMessage()]);
            return redirect()->route('store.orders')
                ->with('error', 'Payment verification failed. Contact support.');
        }

        return redirect()->route('store.orders')
            ->with($status === 'success' ? 'success' : 'error',
                   $status === 'success' ? 'Payment successful! Your order is confirmed.' : 'Payment failed. Please try again.');
    }

    /**
     * Handle EasyPaisa callback.
     */
    public function callbackEasyPaisa(Request $request)
    {
        try {
            $transaction = $this->gateway->handleCallback($request->all(), 'easypaisa');
            $status      = $transaction->status;
        } catch (\Exception $e) {
            Log::error('EasyPaisa callback error', ['error' => $e->getMessage()]);
            return redirect()->route('store.orders')
                ->with('error', 'Payment verification failed. Contact support.');
        }

        return redirect()->route('store.orders')
            ->with($status === 'success' ? 'success' : 'error',
                   $status === 'success' ? 'Payment successful! Your order is confirmed.' : 'Payment failed. Please try again.');
    }
}
