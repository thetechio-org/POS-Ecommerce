<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DiscountRule;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\Warehouse;

/**
 * The storefront checkout must never take a price, a total, or an amount paid
 * from the request. Every figure is looked up or derived on the server.
 */
beforeEach(function () {
    $unit      = Unit::create(['name' => 'Piece', 'base_unit' => 'Piece', 'conversion_factor' => 1]);
    $category  = Category::create(['name' => 'Test']);
    $warehouse = Warehouse::create(['name' => 'WH1', 'location' => 'Karachi']);

    // CheckoutController looks this branch up by name and refuses to run without it.
    Branch::create([
        'name'         => 'Ecommerce-store',
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
        'actual_price'            => 150000,
    ]);

    InventoryStock::create([
        'product_id'            => $this->product->id,
        'variant_id'            => null,
        'warehouse_id'          => $warehouse->id,
        'quantity_in_base_unit' => 50,
    ]);

    $this->customer = Customer::create([
        'name'     => 'Test Customer',
        'phone'    => '03001234567',
        'email'    => 'customer@test.com',
        'password' => 'secret123',
        'balance'  => 0,
    ]);
});

function cartWith(int $productId, int $qty, float $forgedPrice): array
{
    return [$productId => [
        'id'           => $productId,
        'name'         => 'Laptop',
        'stock'        => 50,
        'price'        => $forgedPrice,
        'actual_price' => $forgedPrice,
        'quantity'     => $qty,
        'variant_id'   => null,
    ]];
}

it('charges the database price even when the posted cart and totals say otherwise', function () {
    $this->actingAs($this->customer, 'customer')
        ->withSession(['cart' => cartWith($this->product->id, 2, 1.00)])
        ->post(route('store.checkout.process'), [
            'cart_data'              => json_encode([['id' => $this->product->id, 'quantity' => 2, 'price' => 1]]),
            'total_payable'          => '1.00',
            'amount_paid'            => '1.00',
            'coupon_discount_amount' => '999999',
            'tax_amount'             => '0',
            'shipping'               => '0',
            'payment_method'         => 'cash',
        ]);

    $sale = Sale::latest('id')->with('items')->first();

    expect($sale)->not->toBeNull()
        ->and((float) $sale->final_amount)->toBe(300000.0)
        ->and((float) $sale->total_amount)->toBe(300000.0)
        ->and((float) $sale->discount_amount)->toBe(0.0)
        ->and((float) $sale->items->first()->unit_price)->toBe(150000.0);
});

it('records nothing as paid when the order is placed', function () {
    $this->actingAs($this->customer, 'customer')
        ->withSession(['cart' => cartWith($this->product->id, 1, 1.00)])
        ->post(route('store.checkout.process'), [
            'amount_paid'    => '150000',
            'total_payable'  => '150000',
            'payment_method' => 'cash',
        ]);

    $sale = Sale::latest('id')->first();

    // Cash on delivery: the money arrives later, and updateStatus() records it.
    expect((float) $sale->paid_amount)->toBe(0.0)
        ->and((float) $sale->due_amount)->toBe(150000.0);
});

it('applies a coupon at its real percentage, not at the amount the browser asked for', function () {
    DiscountRule::create([
        'name'        => 'Ten Off',
        'type'        => 'coupon',
        'coupon_code' => 'SAVE10',
        'discount'    => 10,
        'target_ids'  => json_encode([]),
        'start_date'  => now()->subDay(),
        'end_date'    => now()->addDay(),
    ]);

    $this->actingAs($this->customer, 'customer')
        ->withSession([
            'cart'        => cartWith($this->product->id, 1, 150000),
            'coupon_code' => 'SAVE10',
        ])
        ->post(route('store.checkout.process'), [
            'coupon_discount_amount' => '149999',   // the browser asks for a 99.9% discount
            'total_payable'          => '1.00',
            'amount_paid'            => '1.00',
            'payment_method'         => 'cash',
        ]);

    $sale = Sale::latest('id')->first();

    expect((float) $sale->discount_amount)->toBe(15000.0)
        ->and((float) $sale->final_amount)->toBe(135000.0);
});

it('does not write a customer id into created_by, which is a foreign key to users', function () {
    $this->actingAs($this->customer, 'customer')
        ->withSession(['cart' => cartWith($this->product->id, 1, 150000)])
        ->post(route('store.checkout.process'), ['payment_method' => 'cash']);

    expect(Sale::latest('id')->first()->created_by)->toBeNull();
});

it('refuses to place an order for more stock than exists', function () {
    $this->actingAs($this->customer, 'customer')
        ->withSession(['cart' => cartWith($this->product->id, 999, 150000)])
        ->post(route('store.checkout.process'), ['payment_method' => 'cash']);

    expect(Sale::count())->toBe(0)
        ->and((float) InventoryStock::sum('quantity_in_base_unit'))->toBe(50.0);
});
