<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLedger;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;

/**
 * Storefront orders are placed unpaid (cash on delivery), so the money and the
 * stock both move as the order changes status. These paths only became reachable
 * once checkout stopped marking every order as paid on creation.
 */
beforeEach(function () {
    $unit     = Unit::create(['name' => 'Piece', 'base_unit' => 'Piece', 'conversion_factor' => 1]);
    $category = Category::create(['name' => 'Test']);

    $this->warehouse = Warehouse::create(['name' => 'WH1', 'location' => 'Karachi']);
    $this->branch    = Branch::create([
        'name' => 'Ecommerce-store', 'location' => 'Karachi',
        'contact' => '021', 'warehouse_id' => $this->warehouse->id,
    ]);

    $this->product = Product::create([
        'name' => 'Laptop', 'category_id' => $category->id,
        'base_unit_id' => $unit->id, 'default_display_unit_id' => $unit->id,
        'has_variants' => false, 'sku' => 'LT1', 'actual_price' => 1000,
    ]);

    $this->stock = InventoryStock::create([
        'product_id' => $this->product->id, 'variant_id' => null,
        'warehouse_id' => $this->warehouse->id, 'quantity_in_base_unit' => 40,
    ]);

    $this->customer = Customer::create([
        'name' => 'Buyer', 'phone' => '03001234567', 'balance' => 1000,
    ]);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@test.com', 'password' => 'secret123',
        'role_id' => Role::create(['name' => 'Admin'])->id, 'status' => 'Active',
    ]);
});

function placeOrder($test, float $due, float $paid): Sale
{
    $sale = Sale::create([
        'customer_id'    => $test->customer->id,
        'branch_id'      => $test->branch->id,
        'invoice_number' => 'INV-' . uniqid(),
        'sale_date'      => now(),
        'total_amount'   => 1000, 'final_amount' => 1000,
        'paid_amount'    => $paid, 'due_amount' => $due,
        'payment_method' => 'cash', 'sale_origin' => 'E-commerce',
        'status'         => 'pending', 'created_by' => null,
    ]);

    SaleItem::create([
        'sale_id'               => $sale->id,
        'product_id'            => $test->product->id,
        'variant_id'            => null,
        'quantity'              => 10,
        'unit_price'            => 100,
        'total_price'           => 1000,
        'quantity_in_base_unit' => 10,
    ]);

    return $sale;
}

function advance($test, Sale $sale, string $status)
{
    return $test->actingAs($test->admin)
        ->put(route('orders.updateStatus', $sale), ['status' => $status]);
}

it('records the payment and clears the customer balance on delivery', function () {
    $sale = placeOrder($this, due: 1000, paid: 0);

    advance($this, $sale, 'confirmed');
    advance($this, $sale->fresh(), 'shipped');
    advance($this, $sale->fresh(), 'delivered');

    $sale = $sale->fresh();
    $payment = Payment::where('ref_type', 'sale')->where('ref_id', $sale->id)->first();

    expect($sale->status)->toBe('delivered')
        ->and((float) $sale->paid_amount)->toBe(1000.0)
        ->and((float) $sale->due_amount)->toBe(0.0)
        ->and($payment)->not->toBeNull()
        ->and($payment->entity_type)->toBe('customer')   // NOT NULL — the insert fails without it
        ->and($payment->transaction_type)->toBe('in')
        ->and((float) $this->customer->fresh()->balance)->toBe(0.0);
});

it('puts the stock back when an order is cancelled', function () {
    $sale = placeOrder($this, due: 1000, paid: 0);

    expect((float) $this->stock->fresh()->quantity_in_base_unit)->toBe(40.0);

    advance($this, $sale, 'cancelled');

    // The ledger and the stock table must agree — for a long time the ledger row
    // was written but the matching stock update was discarded.
    $ledger = StockLedger::where('ref_type', 'cancelled_order_return')
        ->where('ref_id', $sale->id)->first();

    expect($sale->fresh()->status)->toBe('cancelled')
        ->and((float) $this->stock->fresh()->quantity_in_base_unit)->toBe(50.0)
        ->and($ledger)->not->toBeNull()
        ->and((float) $ledger->quantity_change_in_base_unit)->toBe(10.0)
        ->and($ledger->direction)->toBe('in');
});

it('stops the customer owing anything for a cancelled order', function () {
    $sale = placeOrder($this, due: 1000, paid: 0);

    advance($this, $sale, 'cancelled');

    expect((float) $sale->fresh()->due_amount)->toBe(0.0)
        ->and((float) $this->customer->fresh()->balance)->toBe(0.0);
});

it('records a refund when a paid order is cancelled', function () {
    $sale = placeOrder($this, due: 0, paid: 1000);

    advance($this, $sale, 'cancelled');

    $refund = Payment::where('ref_type', 'sales_return')->where('ref_id', $sale->id)->first();

    expect($refund)->not->toBeNull()
        ->and($refund->entity_type)->toBe('customer')
        ->and($refund->transaction_type)->toBe('out')
        ->and((float) $refund->amount)->toBe(1000.0);
});

it('only lets Admins and Managers move an order along', function () {
    $cashier = User::create([
        'name' => 'Cashier', 'email' => 'cashier@test.com', 'password' => 'secret123',
        'role_id' => Role::create(['name' => 'Cashier'])->id, 'status' => 'Active',
    ]);

    $sale = placeOrder($this, due: 1000, paid: 0);

    $this->actingAs($cashier)
        ->put(route('orders.updateStatus', $sale), ['status' => 'confirmed'])
        ->assertForbidden();

    expect($sale->fresh()->status)->toBe('pending');
});
