<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;

/**
 * Neither sale screen has a price-override field, so the prices and totals in the
 * posted form are only ever a mirror of what the server already knows. They are
 * recomputed here rather than trusted, which keeps the recorded totals consistent
 * with the line items even when the form is tampered with.
 */
beforeEach(function () {
    $unit      = Unit::create(['name' => 'Piece', 'base_unit' => 'Piece', 'conversion_factor' => 1]);
    $category  = Category::create(['name' => 'Test']);
    $warehouse = Warehouse::create(['name' => 'WH1', 'location' => 'Karachi']);

    $this->branch = Branch::create([
        'name'         => 'Main',
        'location'     => 'Karachi',
        'contact'      => '021',
        'warehouse_id' => $warehouse->id,
    ]);

    $this->product = Product::create([
        'name'                    => 'Laptop',
        'category_id'             => $category->id,
        'base_unit_id'            => $unit->id,
        'default_display_unit_id' => $unit->id,
        'has_variants'            => false,
        'sku'                     => 'LT1',
        'actual_price'            => 1000,
    ]);

    InventoryStock::create([
        'product_id'            => $this->product->id,
        'variant_id'            => null,
        'warehouse_id'          => $warehouse->id,
        'quantity_in_base_unit' => 100,
    ]);

    $this->customer = Customer::create([
        'name' => 'Walk In', 'phone' => '03001234567', 'balance' => 0,
    ]);

    $this->staff = User::create([
        'name'     => 'Cashier',
        'email'    => 'cashier@test.com',
        'password' => 'secret123',
        'role_id'  => Role::create(['name' => 'Admin'])->id,
        'status'   => 'Active',
    ]);

    $this->unitId = $unit->id;
});

function saleCart(int $productId, int $unitId, int $qty, float $forgedPrice): string
{
    return json_encode(["product-{$productId}" => [
        'type'         => 'product',
        'id'           => $productId,
        'name'         => 'Laptop',
        'actual_price' => $forgedPrice,
        'qty'          => $qty,
        'unit_id'      => $unitId,
    ]]);
}

it('prices lines from the database rather than from the posted cart', function () {
    $this->actingAs($this->staff)->post(route('sales.checkout.process'), [
        'customer_id'    => $this->customer->id,
        'branch_id'      => $this->branch->id,
        'cart_data'      => saleCart($this->product->id, $this->unitId, 3, 1),
        'payment_method' => 'cash',
        'amount_paid'    => 0,
        'subtotal'       => '3.00',
        'total_payable'  => '3.00',
        'balance_due'    => '0',
    ]);

    $sale = Sale::latest('id')->with('items')->first();

    expect($sale)->not->toBeNull()
        ->and((float) $sale->items->first()->unit_price)->toBe(1000.0)
        ->and((float) $sale->total_amount)->toBe(3000.0)
        ->and((float) $sale->final_amount)->toBe(3000.0);
});

it('derives the balance due instead of believing the posted one', function () {
    $this->actingAs($this->staff)->post(route('sales.checkout.process'), [
        'customer_id'    => $this->customer->id,
        'branch_id'      => $this->branch->id,
        'cart_data'      => saleCart($this->product->id, $this->unitId, 2, 1000),
        'payment_method' => 'cash',
        'amount_paid'    => 500,
        'balance_due'    => '0',      // claims nothing is owed on a Rs. 2000 sale
        'total_payable'  => '500',
    ]);

    $sale = Sale::latest('id')->first();

    expect((float) $sale->final_amount)->toBe(2000.0)
        ->and((float) $sale->paid_amount)->toBe(500.0)
        ->and((float) $sale->due_amount)->toBe(1500.0)
        ->and((float) $this->customer->fresh()->balance)->toBe(1500.0);
});

it('applies a percentage discount and tax the way the screen does', function () {
    // 2 x 1000 = 2000, less 10% = 1800, plus 5% tax = 1890, plus 100 shipping.
    $this->actingAs($this->staff)->post(route('sales.checkout.process'), [
        'customer_id'    => $this->customer->id,
        'branch_id'      => $this->branch->id,
        'cart_data'      => saleCart($this->product->id, $this->unitId, 2, 1000),
        'payment_method' => 'cash',
        'amount_paid'    => 0,
        'discount_type'  => 'percentage',
        'discountRate'   => 10,
        'taxrate'        => 5,
        'shipping'       => 100,
    ]);

    $sale = Sale::latest('id')->first();

    expect((float) $sale->total_amount)->toBe(2000.0)
        ->and((float) $sale->discount_amount)->toBe(200.0)
        ->and((float) $sale->tax_amount)->toBe(90.0)
        ->and((float) $sale->shipping)->toBe(100.0)
        ->and((float) $sale->final_amount)->toBe(1990.0);
});

it('never lets a discount push the total below zero', function () {
    $this->actingAs($this->staff)->post(route('sales.checkout.process'), [
        'customer_id'    => $this->customer->id,
        'branch_id'      => $this->branch->id,
        'cart_data'      => saleCart($this->product->id, $this->unitId, 1, 1000),
        'payment_method' => 'cash',
        'amount_paid'    => 0,
        'discount_type'  => 'fixed',
        'discountRate'   => 999999,
    ]);

    $sale = Sale::latest('id')->first();

    expect((float) $sale->discount_amount)->toBe(1000.0)
        ->and((float) $sale->final_amount)->toBe(0.0);
});

it('rejects a sale with no customer or no branch', function () {
    $this->actingAs($this->staff)
        ->post(route('sales.checkout.process'), [
            'cart_data'      => saleCart($this->product->id, $this->unitId, 1, 1000),
            'payment_method' => 'cash',
            'amount_paid'    => 0,
        ])
        ->assertSessionHasErrors(['customer_id', 'branch_id']);

    expect(Sale::count())->toBe(0);
});
