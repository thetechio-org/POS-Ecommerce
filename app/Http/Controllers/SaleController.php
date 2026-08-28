<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\InventoryStock;
use App\Models\StockLedger;
use App\Models\ProductVariant;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\Category;
use App\Models\Branch;
use App\Models\Unit;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvoiceSentMail;
use App\Mail\OrderStatusUpdated;
use App\Models\DiscountRule;
use Illuminate\Validation\Rule;
use Auth;
use App\Traits\BranchScoped;
use App\Services\SmsService;

class SaleController extends Controller
{
    use BranchScoped;

    public function index(){
        $title = "Sales List";
        $query = Sale::where('sale_origin', 'POS')
                     ->with(['customer', 'items', 'payments', 'branch'])
                     ->withCount('salesReturns')
                     ->latest();
        $this->applyBranchScope($query);
        $sales = $query->paginate(10);
        return view('admin.sale.list', compact('sales', 'title'));
    }

    public function create(Request $request){
        $title = "Add Sale";
        $categories = Category::all();
        $branches = Branch::all();
        $customers = Customer::all();
        $units = Unit::all();
        $setting = Setting::first();

        $search      = $request->input('search');
        $branchId    = $request->input('branch_id');
        $warehouseId = null;
        $products    = collect();

        if ($branchId) {
            $branch      = Branch::find($branchId);
            $warehouseId = $branch?->warehouse_id;
        }

        // AJAX with no branch selected — return placeholder
        if ($request->ajax() && !$warehouseId) {
            return '<div class="col-12 text-center text-muted py-5">
                        <p class="mb-0"><i class="fas fa-store fa-2x mb-2 d-block"></i>Select a branch above to see available products.</p>
                    </div>';
        }

        if ($warehouseId) {
            $now = now();
            $activeDiscounts = \App\Models\DiscountRule::where('start_date', '<=', $now)
                ->where('end_date', '>=', $now)
                ->get();

            // Only return products that have stock > 0 in the selected branch's warehouse
            $query = Product::whereNull('deleted_at')
                ->where(function ($q) use ($warehouseId) {
                    $q->whereHas('inventoryStocks', function ($sq) use ($warehouseId) {
                        $sq->where('warehouse_id', $warehouseId)
                           ->where('quantity_in_base_unit', '>', 0);
                    })->orWhereHas('variants.inventoryStocks', function ($sq) use ($warehouseId) {
                        $sq->where('warehouse_id', $warehouseId)
                           ->where('quantity_in_base_unit', '>', 0);
                    });
                })
                ->with([
                    'baseUnit',
                    'variants.product.baseUnit',
                    'variants.inventoryStocks' => fn($q) => $q->where('warehouse_id', $warehouseId),
                    'inventoryStocks'          => fn($q) => $q->where('warehouse_id', $warehouseId),
                ]);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhere('barcode', 'like', "%{$search}%");
                });
            } else {
                $query->latest()->take(12);
            }

            $products = $query->get()->map(function ($product) use ($activeDiscounts) {
                $conversionFactor        = $product->baseUnit->conversion_factor ?? 1;
                $baseQuantity            = $product->inventoryStocks->sum('quantity_in_base_unit') ?? 0;
                $product->stock_quantity = $baseQuantity / $conversionFactor;
                $product->in_stock       = $product->stock_quantity > 0;
                $product->discounted_price = $product->actual_price;

                foreach ($activeDiscounts as $rule) {
                    $targets = collect(json_decode($rule->target_ids));
                    if (
                        ($rule->type === 'product'  && $targets->contains($product->id)) ||
                        ($rule->type === 'category' && $targets->contains($product->category_id))
                    ) {
                        $product->discounted_price = $product->actual_price - ($product->actual_price * ($rule->discount / 100));
                        break;
                    }
                }

                foreach ($product->variants as $variant) {
                    $variantConversionFactor  = $variant->product->baseUnit->conversion_factor ?? 1;
                    $variantQuantity          = $variant->inventoryStocks->sum('quantity_in_base_unit') ?? 0;
                    $variant->stock_quantity  = $variantQuantity / $variantConversionFactor;
                    $variant->in_stock        = $variant->stock_quantity > 0;
                    $variant->discounted_price = $variant->actual_price;

                    foreach ($activeDiscounts as $rule) {
                        $targets = collect(json_decode($rule->target_ids));
                        if (
                            ($rule->type === 'product'  && $targets->contains($product->id)) ||
                            ($rule->type === 'category' && $targets->contains($product->category_id))
                        ) {
                            $variant->discounted_price = $variant->actual_price - ($variant->actual_price * ($rule->discount / 100));
                            break;
                        }
                    }
                }

                return $product;
            });
        }

        // AJAX: return product card HTML
        if ($request->ajax()) {
            if ($products->isEmpty()) {
                return '<div class="col-12 text-center text-muted py-5">
                            <p class="mb-0">No products with available stock at this branch.</p>
                        </div>';
            }

            $html = '';
            foreach ($products as $product) {
                $isOutOfStock  = !$product->in_stock && $product->variants->count() === 0;
                $productImgSrc = !empty($product->product_img)
                    ? asset('storage/' . $product->product_img)
                    : null;

                $html .= '<div class="col-md-3 mb-2 product-item d-flex">';
                $html .= '<div class="card p-2 text-center h-100 d-flex flex-column justify-content-between w-100 ' . ($isOutOfStock ? 'bg-light text-muted pointer-events-none opacity-50' : '') . '">';

                if ($productImgSrc) {
                    $html .= '<img src="' . $productImgSrc . '" alt="Product Image" style="width:70px;height:70px;object-fit:cover;border-radius:8px;margin:auto;">';
                } else {
                    $html .= '<div style="width:100px;height:100px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;border-radius:8px;margin:auto;">N/A</div>';
                }

                $html .= '<h6 class="mt-2 mb-1">' . htmlspecialchars($product->name) . '</h6>';

                if ($product->variants->count()) {
                    $html .= '<select class="form-control mb-2 variant-selector mt-auto" data-product-id="' . $product->id . '">';
                    $html .= '<option disabled selected>Choose Variant</option>';
                    foreach ($product->variants as $variant) {
                        $disabled    = !$variant->in_stock ? 'disabled' : '';
                        $stockText   = $variant->in_stock ? '(Stock: ' . (int)$variant->stock_quantity . ')' : '(Out of Stock)';
                        $finalPrice  = $variant->discounted_price;
                        $hasDiscount = $finalPrice < $variant->actual_price;
                        $label = htmlspecialchars($variant->variant_name) . ' - ';
                        if ($hasDiscount) {
                            $label .= '<del>' . $setting->currency_symbol . ' ' . number_format($variant->actual_price, 2) . '</del> ';
                            $label .= '<strong style="color:red;">' . $setting->currency_symbol . ' ' . number_format($finalPrice, 2) . '</strong>';
                        } else {
                            $label .= $setting->currency_symbol . ' ' . number_format($variant->actual_price, 2);
                        }
                        $label .= ' ' . $stockText;
                        $html .= '<option ' . $disabled . ' value="variant-' . $variant->id . '" '
                            . 'data-name="' . htmlspecialchars($product->name . ' - ' . $variant->variant_name) . '" '
                            . 'data-price="' . $finalPrice . '" '
                            . 'data-stock="' . (int)$variant->stock_quantity . '" '
                            . 'data-unit-id="' . $product->default_display_unit_id . '">'
                            . $label . '</option>';
                    }
                    $html .= '</select>';
                    $html .= '<button class="btn btn-sm btn-success w-100 add-variant-to-cart mb-2" disabled>Add to Cart</button>';
                } else {
                    $finalPrice  = $product->discounted_price;
                    $hasDiscount = $finalPrice < $product->actual_price;
                    $html .= '<p class="mb-1">';
                    if ($hasDiscount) {
                        $html .= '<del>' . $setting->currency_symbol . ' ' . number_format($product->actual_price, 2) . '</del> ';
                        $html .= '<strong style="color:red;">' . $setting->currency_symbol . ' ' . number_format($finalPrice, 2) . '</strong>';
                    } else {
                        $html .= $setting->currency_symbol . ' ' . number_format($product->actual_price, 2);
                    }
                    $html .= '<br><small>(Stock: ' . (int)$product->stock_quantity . ')</small></p>';

                    if ($product->in_stock) {
                        $html .= '<button '
                            . 'class="btn btn-sm btn-success w-100 mt-auto add-simple-to-cart" '
                            . 'data-id="product-' . $product->id . '" '
                            . 'data-name="' . htmlspecialchars($product->name) . '" '
                            . 'data-price="' . $finalPrice . '" '
                            . 'data-stock="' . (int)$product->stock_quantity . '" '
                            . 'data-unit-id="' . $product->default_display_unit_id . '">'
                            . 'Add to Cart</button>';
                    } else {
                        $html .= '<button class="btn btn-sm btn-secondary w-100 mt-auto" disabled>Out of Stock</button>';
                    }
                }

                $html .= '</div></div>';
            }

            return $html;
        }

        return view('admin.sale.create', compact(
            'categories',
            'units',
            'products',
            'title',
            'customers',
            'branches',
            'setting'
        ));
    }

    public function process(Request $request){
        $request->validate([
            'customer_id'    => ['required', 'exists:customers,id'],
            'branch_id'      => ['required', 'exists:branches,id'],
            'cart_data'      => ['required', 'string'],
            'payment_method' => ['required', 'in:cash,card'],
            'amount_paid'    => ['required', 'numeric', 'min:0'],
            'discount_type'  => ['nullable', 'in:fixed,percentage'],
            'discountRate'   => ['nullable', 'numeric', 'min:0'],
            'taxrate'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'taxRate'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'shipping'       => ['nullable', 'numeric', 'min:0'],
        ]);

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

        $branch = Branch::find($request->branch_id);
        $warehouse_id = $branch->warehouse_id ?? null;

        if (!$warehouse_id) {
            return back()->withInput()->with('error', 'Branch does not have an associated warehouse.');
        }

        DB::beginTransaction();

        try {
            $cart = json_decode($request->cart_data, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($cart) || empty($cart)) {
                throw new \Exception('Invalid cart data.');
            }

            // Price every line from the database and derive the totals here.
            // Neither screen has a price-override field, so the prices and totals
            // in the posted form are only a mirror of what the server already
            // knows — and the browser is free to rewrite them before posting.
            $lines = $this->buildSaleLines($cart);
            if (empty($lines)) {
                throw new \Exception('No valid products in the cart.');
            }

            $totals = $this->computeSaleTotals($lines, $request);

            // PRE-FLIGHT STOCK CHECK — verify every item before any DB write (locked to prevent race conditions)
            foreach ($lines as $line) {
                $available = InventoryStock::where('product_id', $line['product_id'])
                    ->where('variant_id', $line['variant_id'])
                    ->where('warehouse_id', $warehouse_id)
                    ->lockForUpdate()
                    ->value('quantity_in_base_unit') ?? 0;

                if ($available < $line['base_qty']) {
                    throw new \Exception(
                        'Insufficient stock for "' . $line['name'] .
                        '". Available: ' . (int)$available . ', Requested: ' . (int)$line['base_qty'] . '.'
                    );
                }
            }

            // Create the sale
            $sale = Sale::create([
                'customer_id'     => $request->customer_id,
                'branch_id'       => $request->branch_id,
                'invoice_number'  => $invoiceNo,
                'sale_date'       => now(),
                'total_amount'    => $totals['subtotal'],
                'discount_amount' => $totals['discount'],
                'discount_type'   => $totals['discount_type'],
                'tax_amount'      => $totals['tax'],
                'tax_percentage'  => $totals['tax_rate'],
                'shipping'        => $totals['shipping'],
                'final_amount'    => $totals['final'],
                'paid_amount'     => $totals['paid'],
                'due_amount'      => $totals['due'],
                'payment_method'  => $request->payment_method,
                'sale_origin'     => 'POS',
                'created_by'      => auth()->id(),
            ]);

            foreach ($lines as $line) {
                $productId  = $line['product_id'];
                $variantId  = $line['variant_id'];
                $unitId     = $line['unit_id'];
                $quantity   = $line['quantity'];
                $unitPrice  = $line['unit_price'];
                $totalPrice = $line['total_price'];
                $baseQty    = $line['base_qty'];

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

                $stock = InventoryStock::firstOrCreate(
                    [
                        'product_id'   => $productId,
                        'variant_id'   => $variantId,
                        'warehouse_id' => $warehouse_id,
                    ],
                    ['quantity_in_base_unit' => 0]
                );

                $stock->decrement('quantity_in_base_unit', $baseQty);

                StockLedger::create([
                    'product_id'                   => $productId,
                    'variant_id'                   => $variantId,
                    'warehouse_id'                 => $warehouse_id,
                    'ref_type'                     => 'sale',
                    'ref_id'                       => $sale->id,
                    'quantity_change_in_base_unit' => $baseQty,
                    'unit_cost'                    => $unitPrice,
                    'direction'                    => 'out',
                    'created_by'                   => auth()->id(),
                ]);
            }

            // Handle payment — 'in' = money enters the business from customer
            if ($totals['paid'] > 0) {
                Payment::create([
                    'entity_type'      => 'customer',
                    'entity_id'        => $request->customer_id,
                    'transaction_type' => 'in',
                    'ref_type'         => 'sale',
                    'ref_id'           => $sale->id,
                    'amount'           => $totals['paid'],
                    'payment_method'   => $request->payment_method,
                    'created_by'       => auth()->id(),
                    'note'             => null,
                ]);
            }

            // Adjust balance
            $dueAmount = $totals['due'];
            if ($dueAmount > 0) {
                Customer::where('id', $request->customer_id)->increment('balance', $dueAmount);
            } elseif ($dueAmount < 0) {
                Customer::where('id', $request->customer_id)->decrement('balance', abs($dueAmount));
            }

            DB::commit();

            // Send invoice email — non-critical, must not block sale completion
            try {
                $saleForMail = Sale::with(['customer', 'branch', 'items.product', 'items.variant', 'items.unit'])->find($sale->id);
                if ($saleForMail?->customer?->email) {
                    $saleForMail->currency_symbol = Setting::first()?->currency_symbol ?? 'PKR';
                    Mail::to($saleForMail->customer->email)->send(new InvoiceSentMail($saleForMail));
                }
            } catch (\Exception $mailEx) {
                Log::warning('Invoice email failed: ' . $mailEx->getMessage());
            }

            return redirect()->route('sales.list')->with('success', 'Sale recorded successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sale creation failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->withInput()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }
   
    public function destroy($id){
        $sale = Sale::with('items')->findOrFail($id);

        DB::beginTransaction();

        try {
            foreach ($sale->items as $item) {
                $productId = $item->product_id;
                $variantId = $item->variant_id;
                $baseQty   = $item->quantity_in_base_unit;

                // Restore inventory stock
                $stock = InventoryStock::where([
                    'product_id'   => $productId,
                    'variant_id'   => $variantId,
                    'warehouse_id' => null,
                ])->first();

                if ($stock) {
                    $stock->increment('quantity_in_base_unit', $baseQty);
                }

                // Delete stock ledger
                StockLedger::where([
                    'ref_type'     => 'sale',
                    'ref_id'       => $sale->id,
                    'product_id'   => $productId,
                    'variant_id'   => $variantId,
                    'warehouse_id' => null,
                ])->delete();

                // Delete sale item
                $item->delete();
            }

            // Delete payment record(s)
            Payment::where([
                'ref_type' => 'sale',
                'ref_id'   => $sale->id,
            ])->delete();

            // Adjust customer balance
            if ($sale->due_amount > 0) {
                Customer::where('id', $sale->customer_id)->decrement('balance', $sale->due_amount);
            } elseif ($sale->due_amount < 0) {
                Customer::where('id', $sale->customer_id)->increment('balance', abs($sale->due_amount));
            }

            // Delete the sale record
            $sale->delete();

            DB::commit();

            return redirect()->route('sales.list')->with('success', 'Sale deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Sale Deletion Failed', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'sale_id' => $id,
            ]);

            return redirect()->route('sales.list')->with('error', 'An error occurred while deleting the sale.');
        }
    }

    public function invoice($id){
        $title = 'Invoice';
        $sale = Sale::with(['customer', 'warehouse', 'items.unit', 'items.product', 'items.variant'])
                    ->findOrFail($id);

        return view('admin.sale.invoice', compact('sale', 'title')); // passes $purchase to the view
    }

    public function downloadPdf($id){
        $sale = Sale::with(['customer', 'branch', 'items.unit', 'items.product', 'items.variant'])
                    ->findOrFail($id);
        $setting = Setting::first();
        $currencySymbol = $setting->currency_symbol ?? '$';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.sale-invoice', compact('sale', 'setting', 'currencySymbol'));
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download('sale-invoice-' . $sale->invoice_number . '.pdf');
    }

    /**
     * Rebuild sale lines from the posted cart, pricing every line from the database.
     *
     * The POS and sale screens render prices from products.actual_price (or the
     * variant's) and offer no way to override them, so the price is looked up here
     * rather than trusted from the posted cart, which the browser controls.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildSaleLines(array $cart): array
    {
        $lines = [];

        foreach ($cart as $item) {
            $isVariant = ($item['type'] ?? null) === 'variant';
            $variant   = $isVariant ? ProductVariant::find($item['id'] ?? null) : null;

            if ($isVariant && !$variant) {
                \Log::warning('Skipping unknown variant during sale.', ['item' => $item]);
                continue;
            }

            $productId = $variant ? $variant->product_id : ($item['id'] ?? null);
            $product   = $productId ? Product::find($productId) : null;

            if (!$product) {
                \Log::warning('Skipping unknown product during sale.', ['item' => $item]);
                continue;
            }

            $quantity = (float) ($item['qty'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            // Authoritative price, matching what the screen displayed.
            $priceSource = $variant ?: $product;
            $unitPrice   = round((float) $priceSource->actual_price, 2);

            $unit    = isset($item['unit_id']) ? Unit::find($item['unit_id']) : null;
            $baseQty = $quantity * ($unit->conversion_factor ?? 1);

            $lines[] = [
                'product_id'  => $product->id,
                'variant_id'  => $variant?->id,
                'unit_id'     => $unit?->id,
                'name'        => $product->name,
                'quantity'    => $quantity,
                'base_qty'    => $baseQty,
                'unit_price'  => $unitPrice,
                'total_price' => round($unitPrice * $quantity, 2),
            ];
        }

        return $lines;
    }

    /**
     * Recompute a sale's money figures on the server.
     *
     * This mirrors the arithmetic both screens perform in JavaScript, so the
     * cashier sees exactly the numbers that get recorded — the difference is that
     * the recorded totals are derived from the priced lines and the cashier's own
     * discount, tax and shipping entries, never read back from hidden form fields.
     *
     * @return array<string, float|string>
     */
    private function computeSaleTotals(array $lines, Request $request): array
    {
        $subtotal = round(array_sum(array_column($lines, 'total_price')), 2);

        $discountType = $request->input('discount_type') === 'percentage' ? 'percentage' : 'fixed';
        $discountRate = max(0, (float) $request->input('discountRate', 0));

        $discount = $discountType === 'percentage'
            ? round($subtotal * min($discountRate, 100) / 100, 2)
            : round(min($discountRate, $subtotal), 2);   // never discount below zero

        $taxRate = min(100, max(0, (float) ($request->input('taxrate') ?? $request->input('taxRate') ?? 0)));
        $tax     = round(($subtotal - $discount) * $taxRate / 100, 2);

        $shipping = round(max(0, (float) $request->input('shipping', 0)), 2);
        $final    = round($subtotal - $discount + $tax + $shipping, 2);
        $paid     = round(max(0, (float) $request->input('amount_paid', 0)), 2);

        return [
            'subtotal'      => $subtotal,
            'discount'      => $discount,
            'discount_type' => $discountType,
            'tax'           => $tax,
            'tax_rate'      => $taxRate,
            'shipping'      => $shipping,
            'final'         => $final,
            'paid'          => $paid,
            'due'           => round($final - $paid, 2),
        ];
    }

    public function pos(Request $request){
        $title      = "Point of Sale";
        $categories = Category::all();
        $branches   = Branch::all();
        $customers  = Customer::all();
        $units      = Unit::all();
        $setting    = Setting::first();

        $search      = $request->input('search');
        $branchId    = $request->input('branch_id');
        $warehouseId = null;
        $products    = collect();

        if ($branchId) {
            $branch      = Branch::find($branchId);
            $warehouseId = $branch?->warehouse_id;
        }

        // AJAX with no branch selected — return placeholder
        if ($request->ajax() && !$warehouseId) {
            return '<div class="col-12 text-center text-muted py-5">
                        <p class="mb-0"><i class="fas fa-store fa-2x mb-2 d-block"></i>Select a branch above to see available products.</p>
                    </div>';
        }

        if ($warehouseId) {
            $now = now();
            $activeDiscounts = DiscountRule::where('start_date', '<=', $now)
                ->where('end_date', '>=', $now)
                ->get();

            $query = Product::whereNull('deleted_at')
                ->where(function ($q) use ($warehouseId) {
                    $q->whereHas('inventoryStocks', function ($sq) use ($warehouseId) {
                        $sq->where('warehouse_id', $warehouseId)
                           ->where('quantity_in_base_unit', '>', 0);
                    })->orWhereHas('variants.inventoryStocks', function ($sq) use ($warehouseId) {
                        $sq->where('warehouse_id', $warehouseId)
                           ->where('quantity_in_base_unit', '>', 0);
                    });
                })
                ->with([
                    'baseUnit',
                    'variants.product.baseUnit',
                    'variants.inventoryStocks' => fn($q) => $q->where('warehouse_id', $warehouseId),
                    'inventoryStocks'          => fn($q) => $q->where('warehouse_id', $warehouseId),
                ]);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhere('barcode', 'like', "%{$search}%");
                });
            } else {
                $query->latest()->take(12);
            }

            $products = $query->get()->map(function ($product) use ($activeDiscounts) {
                $conversionFactor        = $product->baseUnit->conversion_factor ?? 1;
                $baseQuantity            = $product->inventoryStocks->sum('quantity_in_base_unit') ?? 0;
                $product->stock_quantity = $baseQuantity / $conversionFactor;
                $product->in_stock       = $product->stock_quantity > 0;
                $product->final_price    = $product->actual_price;

                foreach ($activeDiscounts as $rule) {
                    $targets = collect(json_decode($rule->target_ids));
                    if (
                        ($rule->type === 'product'  && $targets->contains($product->id)) ||
                        ($rule->type === 'category' && $targets->contains($product->category_id))
                    ) {
                        $product->final_price = $product->actual_price - ($product->actual_price * ($rule->discount / 100));
                        break;
                    }
                }

                foreach ($product->variants as $variant) {
                    $variantConversionFactor = $variant->product->baseUnit->conversion_factor ?? 1;
                    $variantQuantity         = $variant->inventoryStocks->sum('quantity_in_base_unit') ?? 0;
                    $variant->stock_quantity = $variantQuantity / $variantConversionFactor;
                    $variant->in_stock       = $variant->stock_quantity > 0;
                    $variant->final_price    = $variant->actual_price;

                    foreach ($activeDiscounts as $rule) {
                        $targets = collect(json_decode($rule->target_ids));
                        if (
                            ($rule->type === 'product'  && $targets->contains($product->id)) ||
                            ($rule->type === 'category' && $targets->contains($product->category_id))
                        ) {
                            $variant->final_price = $variant->actual_price - ($variant->actual_price * ($rule->discount / 100));
                            break;
                        }
                    }
                }

                return $product;
            });
        }

        // AJAX: return product card HTML
        if ($request->ajax()) {
            if ($products->isEmpty()) {
                return '<div class="col-12 text-center text-muted py-5">
                            <p class="mb-0">No products with available stock at this branch.</p>
                        </div>';
            }

            $html = '';
            foreach ($products as $product) {
                $isOutOfStock  = !$product->in_stock && $product->variants->count() === 0;
                $productImgSrc = !empty($product->product_img)
                    ? asset('storage/' . $product->product_img)
                    : null;

                $html .= '<div class="col-md-3 mb-2 product-item d-flex">';
                $html .= '<div class="card p-2 text-center h-100 d-flex flex-column justify-content-between w-100 ' . ($isOutOfStock ? 'bg-light text-muted pointer-events-none opacity-50' : '') . '">';

                if ($productImgSrc) {
                    $html .= '<img src="' . $productImgSrc . '" alt="Product Image" style="width:70px;height:70px;object-fit:cover;border-radius:8px;margin:auto;">';
                } else {
                    $html .= '<div style="width:70px;height:70px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;border-radius:8px;margin:auto;">N/A</div>';
                }

                $html .= '<h6 class="mt-2 mb-1">' . htmlspecialchars($product->name) . '</h6>';

                if ($product->variants->count()) {
                    $html .= '<select class="form-control mb-2 variant-selector mt-auto" data-product-id="' . $product->id . '">';
                    $html .= '<option disabled selected>Choose Variant</option>';
                    foreach ($product->variants as $variant) {
                        $disabled    = !$variant->in_stock ? 'disabled' : '';
                        $stockText   = $variant->in_stock ? '(Stock: ' . (int)$variant->stock_quantity . ')' : '(Out of Stock)';
                        $finalPrice  = $variant->final_price;
                        $hasDiscount = $finalPrice < $variant->actual_price;
                        $label = htmlspecialchars($variant->variant_name) . ' - ';
                        if ($hasDiscount) {
                            $label .= '<del>' . $setting->currency_symbol . ' ' . number_format($variant->actual_price, 2) . '</del> ';
                            $label .= '<strong style="color:red;">' . $setting->currency_symbol . ' ' . number_format($finalPrice, 2) . '</strong>';
                        } else {
                            $label .= $setting->currency_symbol . ' ' . number_format($variant->actual_price, 2);
                        }
                        $label .= ' ' . $stockText;
                        $html .= '<option ' . $disabled . ' value="variant-' . $variant->id . '" '
                            . 'data-name="' . htmlspecialchars($product->name . ' - ' . $variant->variant_name) . '" '
                            . 'data-price="' . $finalPrice . '" '
                            . 'data-stock="' . (int)$variant->stock_quantity . '" '
                            . 'data-unit-id="' . $product->default_display_unit_id . '">'
                            . $label . '</option>';
                    }
                    $html .= '</select>';
                    $html .= '<button class="btn btn-sm btn-success w-100 add-variant-to-cart mb-2" disabled>Add to Cart</button>';
                } else {
                    $finalPrice  = $product->final_price;
                    $hasDiscount = $finalPrice < $product->actual_price;
                    $html .= '<p class="mb-1">';
                    if ($hasDiscount) {
                        $html .= '<del>' . $setting->currency_symbol . ' ' . number_format($product->actual_price, 2) . '</del> ';
                        $html .= '<strong style="color:red;">' . $setting->currency_symbol . ' ' . number_format($finalPrice, 2) . '</strong>';
                    } else {
                        $html .= $setting->currency_symbol . ' ' . number_format($product->actual_price, 2);
                    }
                    $html .= '<br><small>(Stock: ' . (int)$product->stock_quantity . ')</small></p>';

                    if ($product->in_stock) {
                        $html .= '<button '
                            . 'class="btn btn-sm btn-success w-100 mt-auto add-simple-to-cart" '
                            . 'data-id="product-' . $product->id . '" '
                            . 'data-name="' . htmlspecialchars($product->name) . '" '
                            . 'data-price="' . $finalPrice . '" '
                            . 'data-stock="' . (int)$product->stock_quantity . '" '
                            . 'data-unit-id="' . $product->default_display_unit_id . '">'
                            . 'Add to Cart</button>';
                    } else {
                        $html .= '<button class="btn btn-sm btn-secondary w-100 mt-auto" disabled>Out of Stock</button>';
                    }
                }

                $html .= '</div></div>';
            }

            return $html;
        }

        return view('pos', compact('categories', 'units', 'products', 'title', 'customers', 'branches', 'setting'));
    }

    public function posProcess(Request $request){
        $request->validate([
            'customer_id'    => ['required', 'exists:customers,id'],
            'branch_id'      => ['required', 'exists:branches,id'],
            'cart_data'      => ['required', 'string'],
            'payment_method' => ['required', 'in:cash,card'],
            'amount_paid'    => ['required', 'numeric', 'min:0'],
            'discount_type'  => ['nullable', 'in:fixed,percentage'],
            'discountRate'   => ['nullable', 'numeric', 'min:0'],
            'taxrate'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'taxRate'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'shipping'       => ['nullable', 'numeric', 'min:0'],
        ]);

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

        $branch = Branch::find($request->branch_id);
        $warehouse_id = $branch->warehouse_id ?? null;

        if (!$warehouse_id) {
            return back()->withInput()->with('error', 'Branch does not have an associated warehouse.');
        }

        DB::beginTransaction();

        try {
            $cart = json_decode($request->cart_data, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($cart) || empty($cart)) {
                throw new \Exception('Invalid cart data.');
            }

            // Price every line from the database and derive the totals here.
            // Neither screen has a price-override field, so the prices and totals
            // in the posted form are only a mirror of what the server already
            // knows — and the browser is free to rewrite them before posting.
            $lines = $this->buildSaleLines($cart);
            if (empty($lines)) {
                throw new \Exception('No valid products in the cart.');
            }

            $totals = $this->computeSaleTotals($lines, $request);

            // PRE-FLIGHT STOCK CHECK — verify every item before any DB write (locked to prevent race conditions)
            foreach ($lines as $line) {
                $available = InventoryStock::where('product_id', $line['product_id'])
                    ->where('variant_id', $line['variant_id'])
                    ->where('warehouse_id', $warehouse_id)
                    ->lockForUpdate()
                    ->value('quantity_in_base_unit') ?? 0;

                if ($available < $line['base_qty']) {
                    throw new \Exception(
                        'Insufficient stock for "' . $line['name'] .
                        '". Available: ' . (int)$available . ', Requested: ' . (int)$line['base_qty'] . '.'
                    );
                }
            }

            // Create the sale
            $sale = Sale::create([
                'customer_id'     => $request->customer_id,
                'branch_id'       => $request->branch_id,
                'invoice_number'  => $invoiceNo,
                'sale_date'       => now(),
                'total_amount'    => $totals['subtotal'],
                'discount_amount' => $totals['discount'],
                'discount_type'   => $totals['discount_type'],
                'tax_amount'      => $totals['tax'],
                'shipping'        => $totals['shipping'],
                'final_amount'    => $totals['final'],
                'paid_amount'     => $totals['paid'],
                'due_amount'      => $totals['due'],
                'payment_method'  => $request->payment_method,
                'sale_origin'     => 'POS',
                'created_by'      => auth()->id(),
            ]);

            foreach ($lines as $line) {
                $productId  = $line['product_id'];
                $variantId  = $line['variant_id'];
                $unitId     = $line['unit_id'];
                $quantity   = $line['quantity'];
                $unitPrice  = $line['unit_price'];
                $totalPrice = $line['total_price'];
                $baseQty    = $line['base_qty'];

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

                $stock = InventoryStock::firstOrCreate(
                    [
                        'product_id'   => $productId,
                        'variant_id'   => $variantId,
                        'warehouse_id' => $warehouse_id,
                    ],
                    ['quantity_in_base_unit' => 0]
                );

                $stock->decrement('quantity_in_base_unit', $baseQty);

                StockLedger::create([
                    'product_id'                   => $productId,
                    'variant_id'                   => $variantId,
                    'warehouse_id'                 => $warehouse_id,
                    'ref_type'                     => 'sale',
                    'ref_id'                       => $sale->id,
                    'quantity_change_in_base_unit' => $baseQty,
                    'unit_cost'                    => $unitPrice,
                    'direction'                    => 'out',
                    'created_by'                   => auth()->id(),
                ]);
            }

            // Handle payment — 'in' = money enters the business from customer
            if ($totals['paid'] > 0) {
                Payment::create([
                    'entity_type'      => 'customer',
                    'entity_id'        => $request->customer_id,
                    'transaction_type' => 'in',
                    'ref_type'         => 'sale',
                    'ref_id'           => $sale->id,
                    'amount'           => $totals['paid'],
                    'payment_method'   => $request->payment_method,
                    'created_by'       => auth()->id(),
                    'note'             => null,
                ]);
            }

            // Adjust balance
            $dueAmount = $totals['due'];
            if ($dueAmount > 0) {
                Customer::where('id', $request->customer_id)->increment('balance', $dueAmount);
            } elseif ($dueAmount < 0) {
                Customer::where('id', $request->customer_id)->decrement('balance', abs($dueAmount));
            }

            DB::commit();
            session([
                'show_invoice' => true,
                'sale_id' => $sale->id,
            ]);

            // Send email — non-critical
            try {
                $saleForMail = Sale::with(['customer', 'branch', 'items.product', 'items.variant', 'items.unit'])->find($sale->id);
                if ($saleForMail?->customer?->email) {
                    $saleForMail->currency_symbol = Setting::first()?->currency_symbol ?? 'PKR';
                    Mail::to($saleForMail->customer->email)->send(new InvoiceSentMail($saleForMail));
                }
            } catch (\Exception $mailEx) {
                Log::warning('POS invoice email failed: ' . $mailEx->getMessage());
            }

            return redirect()->route('pos.index');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('POS sale failed: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    public function orders(){
        $title = "E-commerce Orders List";
        // Fetch only sales that originated from E-commerce
        $orders = Sale::where('sale_origin', 'E-commerce')->latest()->paginate(10);
        return view('admin.orders.list', compact('orders', 'title'));
    }

    public function show(Sale $order){
        if ($order->sale_origin !== 'E-commerce') {
            abort(404, 'Order not found or is not an e-commerce order.');
        }

        $title = "Order Details - " . $order->invoice_number;

        $order->load([
            'customer',
            'branch',
            'items.product',
            'items.variant',
            'items.unit'
        ]);

        $setting = Setting::first();

        return view('admin.orders.show', compact('order', 'title', 'setting'));
    }

    public function updateStatus(Request $request, Sale $order)
    {
        // Ensure only allowed statuses are used
        $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'])],
        ]);

        // Optional: Check if the order is an e-commerce order if this controller only handles them
        if ($order->sale_origin !== 'E-commerce') {
             return redirect()->back()->with('error', 'Cannot update status for a non-e-commerce order through this interface.');
        }

        $oldStatus = $order->status;
        $newStatus = $request->input('status');

        // --- Status Transition Rules ---
        // Prevent changing status from cancelled to anything else
        if ($oldStatus === 'cancelled' && $newStatus !== 'cancelled') {
             return redirect()->back()->with('error', 'Cannot change status from Cancelled.');
        }
        // Prevent changing status from delivered to anything else (except possibly for returns, which should be a separate flow)
        if ($oldStatus === 'delivered' && $newStatus !== 'delivered' && $newStatus !== 'cancelled') { // Allowing cancelled from delivered for strict scenario
             return redirect()->back()->with('error', 'Cannot change status from Delivered.');
        }
        // Specific transition logic (e.g., an order must be confirmed before being shipped)
        if ($newStatus === 'confirmed' && $oldStatus !== 'pending') {
            return redirect()->back()->with('error', 'Order must be Pending to be Confirmed.');
        }
        if ($newStatus === 'shipped' && $oldStatus !== 'confirmed') {
            return redirect()->back()->with('error', 'Order must be Confirmed to be Shipped.');
        }
        if ($newStatus === 'delivered' && $oldStatus !== 'shipped') {
            return redirect()->back()->with('error', 'Order must be Shipped to be Delivered.');
        }
        if ($newStatus === 'pending' && !in_array($oldStatus, ['confirmed'])) { // Allow reverting confirmed to pending if needed
            // This rule needs careful consideration based on your business flow
            // If you can only go forward, remove this 'pending' logic.
             return redirect()->back()->with('error', 'Cannot revert to Pending from ' . ucfirst($oldStatus) . '.');
        }

        DB::beginTransaction(); // Start a database transaction
        try {
            $order->status = $newStatus;
            $order->save();

            // Send email notification to the customer
            if ($order->customer && $order->customer->email) {
                $order->load('customer');
                try {
                    Mail::to($order->customer->email)->send(new OrderStatusUpdated($order, $oldStatus));
                } catch (\Exception $mailEx) {
                    \Log::warning('Order status email failed for order #' . $order->id . ': ' . $mailEx->getMessage());
                }
            }

            // Send SMS notification
            if ($order->customer && $order->customer->phone) {
                try {
                    app(SmsService::class)->sendOrderStatusUpdated(
                        $order->customer->phone,
                        $order->invoice_number,
                        $newStatus
                    );
                } catch (\Exception $smsEx) {
                    \Log::warning('Order SMS failed for order #' . $order->id . ': ' . $smsEx->getMessage());
                }
            }

            // --- Logic for 'confirmed' status ---
            if ($newStatus === 'confirmed' && $oldStatus === 'pending') {
                // No specific actions required yet, as per request.
                // This block can be used for actions like sending a confirmation email.
            }

            // --- Logic for 'shipped' status ---
            if ($newStatus === 'shipped' && $oldStatus === 'confirmed') {
                // No specific actions required yet, as per request.
                // This block can be used for actions like sending shipping notifications.
            }

            // --- Logic for 'delivered' status ---
            if ($newStatus === 'delivered' && $oldStatus !== 'delivered') {
                // If there's any outstanding due amount, create a payment record for it
                if ($order->due_amount > 0) {
                    $amountToPay = $order->due_amount; // Amount that was due

                    Payment::create([
                        'entity_type'      => 'customer', // NOT NULL — the insert fails without it
                        'entity_id'        => $order->customer_id,
                        'amount'           => $amountToPay,
                        'payment_method'   => $order->payment_method,
                        'transaction_type' => 'in', // Money coming IN to the business
                        'ref_type'         => 'sale',
                        'ref_id'           => $order->id,
                        'note'             => 'Final payment upon delivery for order #' . $order->invoice_number,
                        'created_by'       => Auth::id(), // Admin user who marked as delivered
                    ]);

                    // Update order's paid_amount and due_amount to reflect full payment
                    $order->paid_amount += $amountToPay; // Add the new payment to paid_amount
                    $order->due_amount = 0;              // Set due amount to zero
                    $order->save();

                    // The customer owed this from the moment the order was placed;
                    // paying on delivery clears it.
                    Customer::where('id', $order->customer_id)->decrement('balance', $amountToPay);
                }
            }

            // --- Logic for 'cancelled' status ---
            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                // 1. Handle payments: Revert paid amounts (refund) if payment was made
                // Only create a refund payment record if `paid_amount` is greater than 0
                if ($order->paid_amount > 0) {
                    // Create a new payment record for the refund (money going OUT)
                    Payment::create([
                        'entity_type'      => 'customer', // NOT NULL — the insert fails without it
                        'entity_id'        => $order->customer_id,
                        'amount'           => $order->paid_amount, // The amount being refunded
                        'payment_method'   => $order->payment_method, // Refund via the original payment method
                        'transaction_type' => 'out', // Money going OUT from the business (refund)
                        'ref_type'         => 'sales_return', // Custom type for sale refund
                        'ref_id'           => $order->id, // Reference the original sale ID
                        'note'             => 'Refund for cancelled order #' . $order->invoice_number,
                        'created_by'       => Auth::id(), // Admin user who initiated the cancellation/refund
                    ]);
                }

                // A cancelled order is no longer owed, whether or not it was paid.
                if ($order->due_amount > 0) {
                    Customer::where('id', $order->customer_id)->decrement('balance', $order->due_amount);
                }

                $order->due_amount = 0;
                $order->save();

                // 2. Add items back to inventory stock and record it in the ledger.
                //    The quantity restored must be quantity_in_base_unit — that is
                //    what the sale deducted — and the row is created if the branch
                //    warehouse has never stocked this product before, so the ledger
                //    and the stock table can never drift apart.
                $order->load('items');
                $warehouseId = $order->branch->warehouse_id ?? $order->branch->warehouse->id ?? null;

                foreach ($order->items as $item) {
                    $restoreQty = (float) $item->quantity_in_base_unit;

                    if ($restoreQty <= 0) {
                        continue;
                    }

                    if (!$warehouseId) {
                        \Log::error("Cannot restore stock for cancelled order #{$order->id}: branch has no warehouse.");
                        break;
                    }

                    InventoryStock::firstOrCreate(
                        [
                            'product_id'   => $item->product_id,
                            'variant_id'   => $item->variant_id,
                            'warehouse_id' => $warehouseId,
                        ],
                        ['quantity_in_base_unit' => 0]
                    )->increment('quantity_in_base_unit', $restoreQty);

                    StockLedger::create([
                        'product_id'                   => $item->product_id,
                        'variant_id'                   => $item->variant_id,
                        'warehouse_id'                 => $warehouseId,
                        'ref_type'                     => 'cancelled_order_return',
                        'ref_id'                       => $order->id,
                        'quantity_change_in_base_unit' => $restoreQty,
                        'unit_cost'                    => $item->unit_price,
                        'direction'                    => 'in',
                        'created_by'                   => Auth::id(),
                        'created_at'                   => Carbon::now(),
                    ]);
                }
            }

            DB::commit(); // Commit the transaction
            return redirect()->back()->with('success', "Order #{$order->invoice_number} status updated to " . ucfirst($newStatus) . '.');

        } catch (\Exception $e) {
            DB::rollBack(); // Rollback if any error occurs
            \Log::error('Order status update failed for order #' . $order->id . ': ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update order status. Please try again. Error: ' . $e->getMessage());
        }
    }
}
