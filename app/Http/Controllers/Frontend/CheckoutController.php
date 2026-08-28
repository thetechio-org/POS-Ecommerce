<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceSentMail;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DiscountRule;
use App\Models\InventoryStock;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\StockLedger;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Services\SmsService;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth; // Import Auth facade


class CheckoutController extends Controller
{
    public function index(){
        // Get the authenticated user's ID
        $userId = Auth::id();

        // If the user is not authenticated, redirect them or show an error
        if (!$userId) {
            return redirect()->route('login')->with('error', 'Please log in to proceed to checkout.');
        }

        // Find the customer using the authenticated user's ID
        $user = Customer::find($userId);

        // If the customer record is not found for the authenticated user, handle it
        if (!$user) {
            return redirect()->back()->with('error', 'Customer profile not found.');
        }

        // Get the cart data from the session
        $cart = session()->get('cart', []);

        // Calculate the initial subtotal from the cart items
        $subtotal = 0;
        foreach ($cart as $item) {
            $price = $item['price'] ?? 0;
            $quantity = $item['quantity'] ?? 0;
            $subtotal += ($price * $quantity);
        }

        // Retrieve coupon data from session
        $couponCode = session('coupon_code', null);
        $couponDiscount = session('coupon_discount', 0);

        // Calculate the total after applying discount (if any)
        $subtotalAfterCoupon = $subtotal - $couponDiscount;
        if ($subtotalAfterCoupon < 0) {
            $subtotalAfterCoupon = 0; // Ensure subtotal doesn't go negative
        }

        // Fetch the general settings for currency symbol
        $setting = Setting::first();

        // Return the view, passing all necessary variables
        return view('store.checkout', compact('cart', 'user', 'subtotal', 'subtotalAfterCoupon', 'setting', 'couponCode', 'couponDiscount'));
    }

    public function process(Request $request){
         // Get the current year for invoice number generation`
        $year = date('Y');
        // Generate the next invoice number
        $lastSale = Sale::whereYear('created_at', $year)
            ->where('invoice_number', 'like', "{$year}-invoice-%")
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = 1;
        if ($lastSale && preg_match("/{$year}-invoice-(\d+)/", $lastSale->invoice_number, $matches)) {
            $nextNumber = (int)$matches[1] + 1;
        }

        $invoiceNo = "{$year}-invoice-" . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        // Determine the branch and warehouse
        $branch = Branch::where('name', '=', 'Ecommerce-store')->first();
        if (!$branch) {
            return back()->withInput()->with('error', 'The "Ecommerce-store" branch was not found. Please ensure it exists.');
        }
        $branchId = $branch->id;

        DB::beginTransaction();

        try {
            $customerId = Auth::id();
            if (!$customerId) {
                throw new \Exception('User not authenticated. Cannot process sale.');
            }

            // ── The order is priced entirely server-side ──────────────────────
            // Nothing about price, discount, tax, total, or amount paid is read
            // from the request. The browser controls those fields, so trusting
            // them would let any customer set their own total. Quantities come
            // from the server-side session cart; every price is looked up from
            // the database at this moment.
            $sessionCart = session()->get('cart', []);
            if (!is_array($sessionCart) || empty($sessionCart)) {
                throw new \Exception('Your cart is empty.');
            }

            $lines = $this->priceCartServerSide($sessionCart);
            if (empty($lines)) {
                throw new \Exception('None of the items in your cart are available any more.');
            }

            $subtotal       = round(array_sum(array_column($lines, 'total_price')), 2);
            $discountAmount = $this->resolveCouponDiscount($subtotal);
            $taxAmount      = 0.00;   // no storefront tax rule is configured
            $shippingCost   = 0.00;   // no storefront shipping rule is configured
            $finalAmount    = round(max(0, $subtotal - $discountAmount + $taxAmount + $shippingCost), 2);

            // Cash on delivery is the only storefront payment method, so no money
            // has been received yet. updateStatus() records the payment when the
            // order is marked delivered.
            $paidAmount    = 0.00;
            $paymentMethod = 'cash';

            // PRE-FLIGHT STOCK CHECK — verify every cart item before any DB write
            foreach ($lines as $line) {
                $available = InventoryStock::where('product_id', $line['product_id'])
                    ->where('variant_id', $line['variant_id'])
                    ->sum('quantity_in_base_unit');

                if ($available < $line['base_qty']) {
                    throw new \Exception(
                        'Sorry, "' . $line['name'] .
                        '" is out of stock. Available: ' . (int)$available . '.'
                    );
                }
            }

            $sale = Sale::create([
                'customer_id'    => $customerId,
                'branch_id'      => $branchId,
                'invoice_number' => $invoiceNo,
                'sale_date'      => now(),
                'total_amount'   => $subtotal,
                'discount_amount'=> $discountAmount,
                'tax_amount'     => $taxAmount,
                'shipping'       => $shippingCost,
                'final_amount'   => $finalAmount,
                'paid_amount'    => $paidAmount,
                'due_amount'     => $finalAmount - $paidAmount,
                'payment_method' => $paymentMethod,
                'sale_origin'    => 'E-commerce',
                'status'         => 'pending',
                // Placed by the customer, not by staff. auth()->id() here is a
                // CUSTOMER id and created_by is a foreign key to users.id.
                'created_by'     => null,
            ]);

            foreach ($lines as $line) {
                $productId   = $line['product_id'];
                $variantId   = $line['variant_id'];
                $unitId      = $line['unit_id'];
                $quantity    = $line['quantity'];
                $unitPrice   = $line['unit_price'];
                $actualPrice = $line['actual_price'];
                $totalPrice  = $line['total_price'];
                $baseQty     = $line['base_qty'];

                SaleItem::create([
                    'sale_id'               => $sale->id,
                    'product_id'            => $productId,
                    'variant_id'            => $variantId,
                    'unit_id'               => $unitId,
                    'quantity'              => $quantity,
                    'unit_price'            => $unitPrice,
                    'total_price'           => $totalPrice,
                    'quantity_in_base_unit' => $baseQty,
                    'discount'              => null,
                    'tax'                   => null,
                ]);

                // Deduct stock greedily from whichever warehouses have available qty
                $stocks = InventoryStock::where('product_id', $productId)
                    ->where('variant_id', $variantId)
                    ->where('quantity_in_base_unit', '>', 0)
                    ->orderBy('quantity_in_base_unit', 'desc')
                    ->lockForUpdate()
                    ->get();

                $remaining = $baseQty;
                foreach ($stocks as $stockRow) {
                    if ($remaining <= 0) break;
                    $deduct = min($remaining, $stockRow->quantity_in_base_unit);
                    $stockRow->decrement('quantity_in_base_unit', $deduct);
                    StockLedger::create([
                        'product_id'                   => $productId,
                        'variant_id'                   => $variantId,
                        'warehouse_id'                 => $stockRow->warehouse_id,
                        'ref_type'                     => 'sale',
                        'ref_id'                       => $sale->id,
                        'quantity_change_in_base_unit' => $deduct,
                        'unit_cost'                    => $actualPrice,
                        'direction'                    => 'out',
                        'created_by'                   => null, // storefront order, no staff user
                    ]);
                    $remaining -= $deduct;
                }
            }

            // Record payment if money was actually received at checkout
            // 'in' = money enters the business from the customer
            if ($paidAmount > 0) {
                Payment::create([
                    'entity_type'      => 'customer',
                    'entity_id'        => $customerId,
                    'transaction_type' => 'in',
                    'ref_type'         => 'sale',
                    'ref_id'           => $sale->id,
                    'amount'           => $paidAmount,
                    'payment_method'   => $paymentMethod,
                    'created_by'       => null, // storefront order, no staff user
                    'note'             => 'E-commerce payment for ' . $invoiceNo,
                ]);
            }

            // Update customer balance for any outstanding due amount
            $dueAmount = $finalAmount - $paidAmount;
            if ($dueAmount > 0) {
                Customer::where('id', $customerId)->increment('balance', $dueAmount);
            }

            DB::commit();

            Session::forget(['cart', 'coupon_code', 'coupon_discount', 'coupon_percentage']);

            $sale = Sale::with(['customer', 'branch', 'items.product', 'items.variant', 'items.unit'])->find($sale->id);

            $sale->currency_symbol = Setting::first()?->currency_symbol ?? '$';
            try {
                Mail::to($sale->customer->email)->send(new InvoiceSentMail($sale));
            } catch (\Exception $mailEx) {
                \Log::warning('Checkout invoice email failed: ' . $mailEx->getMessage());
            }

            // SMS confirmation
            if ($sale->customer->phone) {
                try {
                    app(SmsService::class)->sendOrderPlaced(
                        $sale->customer->phone,
                        $sale->invoice_number,
                        $sale->final_amount
                    );
                } catch (\Exception $smsEx) {
                    \Log::warning('Checkout SMS failed: ' . $smsEx->getMessage());
                }
            }

            // Redirect to the new thank you page, passing invoice number and total amount
            return redirect()->route('store.thankyou', [
                'invoiceNumber' => $sale->invoice_number,
                'totalAmount' => $sale->final_amount
            ])->with('success', 'Sale recorded successfully and cart cleared!');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Checkout process failed: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return back()->withInput()->with('error', 'Something went wrong during checkout. Please try again. Error: ' . $e->getMessage());
        }
    }

    /**
     * Turn the session cart into authoritative order lines.
     *
     * Quantity is the only value taken from the cart. Every price is read from
     * the database here, at checkout time, so neither a tampered session nor a
     * tampered form can change what the customer is charged.
     *
     * @return array<int, array<string, mixed>>
     */
    private function priceCartServerSide(array $sessionCart): array
    {
        $lines = [];

        foreach ($sessionCart as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($quantity < 1) {
                continue;
            }

            $variantId = $item['variant_id'] ?? null;
            $variant   = $variantId ? ProductVariant::find($variantId) : null;

            // A cart line naming a variant that no longer exists is not priceable.
            if ($variantId && !$variant) {
                \Log::warning('Skipping unknown variant during checkout.', ['item' => $item]);
                continue;
            }

            $productId = $variant ? $variant->product_id : ($item['id'] ?? null);
            $product   = $productId ? Product::find($productId) : null;

            if (!$product) {
                \Log::warning('Skipping unknown product during checkout.', ['item' => $item]);
                continue;
            }

            // Authoritative price: the current discounted price from the database.
            $priceSource = $variant ?: $product;
            $unitPrice   = round((float) $priceSource->discounted_price, 2);
            $actualPrice = round((float) $priceSource->actual_price, 2);

            $unit    = isset($item['unit_id']) ? Unit::find($item['unit_id']) : null;
            $baseQty = $quantity * ($unit->conversion_factor ?? 1);

            $lines[] = [
                'product_id'   => $product->id,
                'variant_id'   => $variant?->id,
                'unit_id'      => $unit?->id,
                'name'         => $product->name,
                'quantity'     => $quantity,
                'base_qty'     => $baseQty,
                'unit_price'   => $unitPrice,
                'actual_price' => $actualPrice,
                'total_price'  => round($unitPrice * $quantity, 2),
            ];
        }

        return $lines;
    }

    /**
     * Recompute the coupon discount against the server-calculated subtotal.
     *
     * Only the coupon *code* is carried in the session. The percentage and the
     * resulting amount are always resolved from the database, and the coupon is
     * re-checked here in case it expired between being applied and checkout.
     */
    private function resolveCouponDiscount(float $subtotal): float
    {
        $code = session('coupon_code');

        if (!$code) {
            return 0.00;
        }

        $rule = DiscountRule::where('coupon_code', $code)
            ->where('type', 'coupon')
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->first();

        if (!$rule) {
            Session::forget(['coupon_code', 'coupon_discount', 'coupon_percentage']);

            return 0.00;
        }

        $percentage = min(100, max(0, (float) $rule->discount));

        return round($subtotal * $percentage / 100, 2);
    }

    public function orders()
    {
        $orders = Sale::where('customer_id', auth('customer')->id())
            ->where('sale_origin', 'E-commerce')
            ->with(['items.product', 'items.variant'])
            ->latest('sale_date')
            ->paginate(10);

        $setting = Setting::first();

        return view('store.orders', compact('orders', 'setting'));
    }

    public function orderDetail($id)
    {
        $order = Sale::where('customer_id', auth('customer')->id())
            ->where('sale_origin', 'E-commerce')
            ->with(['items.product', 'items.variant'])
            ->findOrFail($id);

        $setting = Setting::first();

        return view('store.order-detail', compact('order', 'setting'));
    }

    public function thankYou(Request $request){
        // Retrieve data from the redirect (query parameters or session flash data)
        $invoiceNumber = $request->query('invoiceNumber');
        $totalAmount = $request->query('totalAmount');
        // You might want to fetch the setting here if not already available globally
        $setting = Setting::first();

        return view('store.thankyou', compact('invoiceNumber', 'totalAmount', 'setting'));
    }
}