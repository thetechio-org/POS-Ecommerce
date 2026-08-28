<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\DiscountRule;
use Session;
use Carbon\Carbon;

class CartController extends Controller
{
    public function add(Request $request){
        $cart = session()->get('cart', []);
        $id = $request->product_id;
        $qty = (int) ($request->quantity ?? 1);

        if ($qty < 1) {
            return redirect()->back()->with('error', 'Please choose a quantity of at least 1.');
        }

        $product = Product::findOrFail($id);

        // --- Variant information ---
        $variantId = $request->variant_id;
        $variant   = $variantId ? $product->variants()->find($variantId) : null;

        if ($variantId && !$variant) {
            return redirect()->back()->with('error', 'That product option is no longer available.');
        }

        $variantName  = $variant?->variant_name;
        $variantImg   = $variant?->product_img;
        $variantSize  = $variant?->size;
        $variantColor = $variant?->color;

        // Price and stock are read from the database, never from the request.
        // The product page posts both as hidden inputs, which a customer can edit.
        $priceSource    = $variant ?: $product;
        $price          = round((float) $priceSource->discounted_price, 2);
        $availableStock = (int) $priceSource->inventoryStocks()->sum('quantity_in_base_unit');

        $currentCartQuantity = isset($cart[$id]) ? $cart[$id]['quantity'] : 0;
        $newDesiredQuantity = $currentCartQuantity + $qty;

        if ($newDesiredQuantity > $availableStock) {
            return redirect()->back()->with('error', "Cannot add {$qty} units. Only {$availableStock} of {$product->name} in stock. You already have {$currentCartQuantity} in your cart. You can add " . ($availableStock - $currentCartQuantity) . " more.");
        }

        $cartKey = $id; // Default to product ID
        if ($variantId) {
            $cartKey = "{$id}-{$variantId}";
            $currentCartQuantity = isset($cart[$cartKey]) ? $cart[$cartKey]['quantity'] : 0;
            $newDesiredQuantity = $currentCartQuantity + $qty;

            // Re-check stock for the specific variant
            if ($newDesiredQuantity > $availableStock) {
                return redirect()->back()->with('error', "Cannot add {$qty} units. Only {$availableStock} of {$product->name} " . ($variantName ? "({$variantName})" : "") . " in stock. You already have {$currentCartQuantity} in your cart. You can add " . ($availableStock - $currentCartQuantity) . " more.");
            }
        }


        $cart[$cartKey] = [ // Use the potentially new $cartKey
            'id' => $id,
            'name' => $product->name,
            'stock' => $availableStock,
            'price' => $price,
            'actual_price' => round((float) $priceSource->actual_price, 2),
            'quantity' => $newDesiredQuantity,
            'image' => $product->product_img, // ✅ Product's main image
            // --- New: Add variant information ---
            'variant_id' => $variantId,
            'variant_name' => $variantName,
            'variant_img' => $variantImg,
            'variant_size' => $variantSize,
            'variant_color' => $variantColor,
        ];

        session()->put('cart', $cart);

        return redirect()->back()->with('success', 'Product added to cart!');
    }

    public function view(){
        $cart = session()->get('cart', []);
        $setting = Setting::first();

        // Calculate subtotal from cart items
        $subtotal = 0;
        foreach ($cart as $item) {
            // Ensure you're using the correct price and quantity keys
            $subtotal += ($item['price'] * $item['quantity']);
        }

        // Retrieve current coupon info from session for initial page load
        $couponDiscount = session('coupon_discount', 0);
        $appliedCouponCode = session('coupon_code', null);

        // Pass all necessary data to the view
        return view('store.cart', compact('cart', 'setting', 'subtotal', 'couponDiscount', 'appliedCouponCode'));
    }

    public function update(Request $request){
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:0',
        ]);

        $productId = $request->input('product_id');
        $newQuantity = (int) $request->input('quantity');

        $cart = Session::get('cart', []);

        $product = Product::find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        // Use the total_stock accessor from the Product model
        $availableStock = $product->inventoryStock->quantity_in_base_unit;

        $message = '';

        if ($newQuantity > 0) {
            if ($newQuantity > $availableStock) {
                // If the requested quantity exceeds available stock, adjust it to the maximum available
                $adjustedQuantity = $availableStock;
                // If current quantity in cart is more than available, remove it
                if (isset($cart[$productId]) && $cart[$productId]['quantity'] > $availableStock) {
                    $adjustedQuantity = $availableStock;
                }
                if ($adjustedQuantity < 0) $adjustedQuantity = 0; // Ensure not negative

                return response()->json([
                    'success' => false,
                    'message' => "Only {$availableStock} of {$product->name} in stock. Quantity adjusted.",
                    'new_quantity' => $adjustedQuantity, // Send back the adjusted quantity
                    'item_total' => number_format(($product->actual_price ?? 0) * $adjustedQuantity, 2),
                    // Pass the whole cart and adjusted quantity for this product to calculateCartSubtotal
                    'cart_subtotal' => number_format($this->calculateCartSubtotal($cart, $productId, $adjustedQuantity), 2),
                    'cart_grand_total' => number_format($this->calculateCartSubtotal($cart, $productId, $adjustedQuantity), 2),
                ], 400); // Use 400 Bad Request for client-side adjustment
            }

            $cart[$productId] = [
                'id' => $productId,
                'name' => $product->name,
                'stock' => $availableStock, // Use the actual product total stock
                'price' => $product->actual_price,
                'quantity' => $newQuantity,
                'image' => $product->product_img,
            ];
            $message = 'Cart updated successfully.';
        } else { // newQuantity is 0, means removing the item
            if (isset($cart[$productId])) {
                unset($cart[$productId]);
                $message = 'Product removed from cart.';
            } else {
                $message = 'Product not in cart.';
            }
        }

        Session::put('cart', $cart);

        // Recalculate totals after cart modification, using the potentially modified $cart
        $cartSubtotal = $this->calculateCartSubtotal($cart);
        $cartGrandTotal = $cartSubtotal;

        return response()->json([
            'success' => true,
            'message' => $message,
            'new_quantity' => $newQuantity,
            'item_total' => number_format(($product->actual_price ?? 0) * $newQuantity, 2),
            'cart_subtotal' => number_format($cartSubtotal, 2),
            'cart_grand_total' => number_format($cartGrandTotal, 2),
        ]);
    }

    public function remove(Request $request) {
        $cart = session()->get('cart', []);
        if(isset($cart[$request->product_id])) {
            unset($cart[$request->product_id]);
            session()->put('cart', $cart);
            return redirect()->back()->with('success', 'Item removed!');
        }
        return redirect()->back()->with('error', 'Item not found in cart!');
    }

    private function calculateCartSubtotal(array $cart, $productId = null, $quantity = null){
        $subtotal = 0;
        $tempCart = $cart;

        if ($productId !== null && $quantity !== null && isset($tempCart[$productId])) {
            $tempCart[$productId]['quantity'] = $quantity;
        }

        foreach ($tempCart as $item) {
            $subtotal += ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
        }
        return $subtotal;
    }

    public function applyCoupon(Request $request){
        $couponCode = $request->input('coupon_code');

        // The subtotal is calculated from the server-side cart. Taking it from the
        // request would let a crafted value produce any discount the caller wanted.
        $cartSubtotal = $this->calculateCartSubtotal(Session::get('cart', []));

        if (!$couponCode) {
            return response()->json(['success' => false, 'message' => 'Coupon code is required.']);
        }

        $couponRule = DiscountRule::where('coupon_code', $couponCode)
                                  ->where('type', 'coupon')
                                  ->where('start_date', '<=', Carbon::now())
                                  ->where('end_date', '>=', Carbon::now())
                                  ->first();

        if (!$couponRule) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired coupon code.']);
        }

        // Check if a coupon is already applied and if it's the same one
        if (session('coupon_code') === $couponCode) {
            return response()->json(['success' => false, 'message' => 'Coupon already applied.']);
        }

        // Calculate the discount amount
        $discountPercentage = min(100, max(0, (float) $couponRule->discount));
        $discountAmount = round($cartSubtotal * $discountPercentage / 100, 2);

        // Store coupon details in session
        session(['coupon_code' => $couponCode]);
        session(['coupon_discount' => $discountAmount]);
        session(['coupon_percentage' => $discountPercentage]); // Store percentage too if useful

        return response()->json([
            'success' => true,
            'message' => 'Coupon applied successfully!',
            'discount_amount' => $discountAmount,
            'coupon_code' => $couponCode
        ]);
    }

    public function removeCoupon(Request $request){
        // Remove coupon details from session
        session()->forget('coupon_code');
        session()->forget('coupon_discount');
        session()->forget('coupon_percentage');

        return response()->json(['success' => true, 'message' => 'Coupon removed successfully.']);
    }

}