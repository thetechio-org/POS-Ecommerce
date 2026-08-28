<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\DiscountRule;


class StoreController extends Controller
{
    public function landing(){
        $categories = Category::all();

        // Fetch active discount rules only once
        $now = now();
        $discountRules = DiscountRule::where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->get();

        $products = Product::latest()
            ->take(8)
            ->with([
                'inventoryStocks',
                'baseUnit',
                'variants.inventoryStocks',
                'variants.product.baseUnit'
            ])
            ->get()
            ->map(function ($product) use ($discountRules) {
                // Calculate stock
                $conversionFactor = $product->baseUnit->conversion_factor ?? 1;
                $baseQuantity = $product->inventoryStocks->sum('quantity_in_base_unit') ?? 0;
                $product->stock_quantity = $baseQuantity / $conversionFactor;
                $product->in_stock = $product->stock_quantity > 0;

                // Default price
                $product->final_price = $product->actual_price;
                $product->has_discount = false;

                // Find applicable discount rule
                $applicableRule = $discountRules->first(function ($rule) use ($product) {
                    $targetIds = json_decode($rule->target_ids ?? '[]');
                    if ($rule->type === 'product') {
                        return in_array($product->id, $targetIds);
                    } elseif ($rule->type === 'category') {
                        return in_array($product->category_id, $targetIds);
                    }
                    return false;
                });

                // Apply discount if found
                if ($applicableRule) {
                    $product->final_price = $product->actual_price - ($product->actual_price * ($applicableRule->discount / 100));
                    $product->has_discount = true;
                }

                // Variants
                foreach ($product->variants as $variant) {
                    $variantConversionFactor = $variant->product->baseUnit->conversion_factor ?? 1;
                    $variantQuantity = $variant->inventoryStocks->sum('quantity_in_base_unit') ?? 0;
                    $variant->stock_quantity = $variantQuantity / $variantConversionFactor;
                    $variant->in_stock = $variant->stock_quantity > 0;

                    $variant->final_price = $variant->actual_price;
                    $variant->has_discount = false;

                    if ($applicableRule) {
                        $variant->final_price = $variant->actual_price - ($variant->actual_price * ($applicableRule->discount / 100));
                        $variant->has_discount = true;
                    }
                }

                return $product;
            });

        // Extra collections the landing page composes its sections from.
        $decorate = fn ($collection) => $collection->map(fn ($p) => $this->decorate($p, $discountRules));

        $bestSellers = $decorate(
            Product::with(['inventoryStocks', 'baseUnit', 'category'])
                ->withSum('saleItems as sold_qty', 'quantity')
                ->orderByDesc('sold_qty')
                ->take(8)
                ->get()
        );

        // One representative image per browsable category, for the category tiles.
        $showcaseCategories = Category::whereNotNull('parent_id')
            ->withCount('products')
            ->having('products_count', '>', 0)
            ->take(8)
            ->get()
            ->map(function ($category) {
                $category->cover = Product::where('category_id', $category->id)
                    ->whereNotNull('product_img')->value('product_img');

                return $category;
            });

        $brands = Product::whereNotNull('brand')
            ->distinct()->orderBy('brand')->pluck('brand')->take(10);

        return view('store.landing', compact(
            'categories', 'products', 'bestSellers', 'showcaseCategories', 'brands'
        ));
    }

    /**
     * Attach the stock and discounted-price figures a product card needs.
     * The listing queries all need the same treatment, so it lives in one place.
     */
    private function decorate(Product $product, $discountRules): Product
    {
        $conversionFactor = $product->baseUnit->conversion_factor ?? 1;
        $baseQuantity = $product->inventoryStocks->sum('quantity_in_base_unit') ?? 0;
        $product->stock_quantity = $conversionFactor > 0 ? $baseQuantity / $conversionFactor : $baseQuantity;
        $product->in_stock = $product->stock_quantity > 0;

        $product->final_price = $product->actual_price;
        $product->has_discount = false;

        $rule = $discountRules->first(function ($rule) use ($product) {
            $targetIds = json_decode($rule->target_ids ?? '[]', true) ?: [];

            return ($rule->type === 'product'  && in_array($product->id, $targetIds))
                || ($rule->type === 'category' && in_array($product->category_id, $targetIds));
        });

        if ($rule) {
            $product->final_price = round($product->actual_price - ($product->actual_price * $rule->discount / 100), 2);
            $product->has_discount = true;
        }

        return $product;
    }

    public function shop(Request $request){
        $categoryId = $request->query('category');
        $search     = $request->query('q');

        // 🟡 Load active discount rules once
        $now = now();
        $discountRules = DiscountRule::where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->get();

        $productsQuery = Product::with([
            'inventoryStocks',
            'baseUnit',
            'variants.inventoryStocks',
            'variants.product.baseUnit'
        ])->whereNull('deleted_at')->latest();

        if ($categoryId) {
            $productsQuery->where('category_id', $categoryId);
        }

        if ($search) {
            $productsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        $products = $productsQuery->paginate(12)->withQueryString();

        $categories = Category::withCount('products')
            ->whereNotNull('parent_id')
            ->get();
    
        // 🔁 Transform each product to calculate stock + discount
        $products->getCollection()->transform(function ($product) use ($discountRules) {
            // 🧮 Stock calculation
            $conversionFactor = $product->baseUnit->conversion_factor ?? 1;
            $baseQuantity = $product->inventoryStocks->sum('quantity_in_base_unit') ?? 0;
            $product->stock_quantity = $baseQuantity / $conversionFactor;
            $product->in_stock = $product->stock_quantity > 0;
        
            // 💸 Default pricing
            $product->final_price = $product->actual_price;
            $product->has_discount = false;
        
            // 🔍 Check if a discount rule applies
            $applicableRule = $discountRules->first(function ($rule) use ($product) {
                $targetIds = json_decode($rule->target_ids ?? '[]');
                if ($rule->type === 'product') {
                    return in_array($product->id, $targetIds);
                } elseif ($rule->type === 'category') {
                    return in_array($product->category_id, $targetIds);
                }
                return false;
            });
        
            // ✅ Apply discount
            if ($applicableRule) {
                $product->final_price = $product->actual_price - ($product->actual_price * ($applicableRule->discount / 100));
                $product->has_discount = true;
            }
        
            // ♻️ Variant stock and discount
            foreach ($product->variants as $variant) {
                $variantConversion = $variant->product->baseUnit->conversion_factor ?? 1;
                $variantQty = $variant->inventoryStocks->sum('quantity_in_base_unit') ?? 0;
                $variant->stock_quantity = $variantQty / $variantConversion;
                $variant->in_stock = $variant->stock_quantity > 0;
            
                $variant->final_price = $variant->actual_price;
                $variant->has_discount = false;
            
                if ($applicableRule) {
                    $variant->final_price = $variant->actual_price - ($variant->actual_price * ($applicableRule->discount / 100));
                    $variant->has_discount = true;
                }
            }
        
            return $product;
        });
    
        return view('store.shop', compact('products', 'categories', 'search', 'categoryId'));
    }

    // public function product($id)
    // {
    //     // Fetch active discount rules
    //     $now = now();
    //     $discountRules = DiscountRule::where('start_date', '<=', $now)
    //         ->where('end_date', '>=', $now)
    //         ->get();

    //     $product = Product::with([
    //             'inventoryStocks',
    //             'baseUnit',
    //             'displayUnit', // Ensure this is loaded if you use it for base product
    //             'category',    // Ensure this is loaded for category discounts
    //             'variants.inventoryStocks',
    //             'variants.product.baseUnit'
    //         ])->find($id);

    //     if (!$product) {
    //         abort(404, 'Product not found');
    //     }

    //     // Apply calculations to the single product as done in the landing method
    //     $conversionFactor = $product->baseUnit->conversion_factor ?? 1;
    //     $baseQuantity = $product->inventoryStocks->sum('quantity_in_base_unit') ?? 0;
    //     $product->stock_quantity = $baseQuantity / $conversionFactor;
    //     $product->in_stock = $product->stock_quantity > 0;

    //     // Default price
    //     $product->final_price = $product->actual_price;
    //     $product->has_discount = false;

    //     // Find applicable discount rule for the product
    //     $applicableRule = $discountRules->first(function ($rule) use ($product) {
    //         $targetIds = json_decode($rule->target_ids ?? '[]');
    //         if ($rule->type === 'product') {
    //             return in_array($product->id, $targetIds);
    //         } elseif ($rule->type === 'category') {
    //             return in_array($product->category_id, $targetIds);
    //         }
    //         return false;
    //     });

    //     // Apply discount if found
    //     if ($applicableRule) {
    //         $product->final_price = $product->actual_price - ($product->actual_price * ($applicableRule->discount / 100));
    //         $product->has_discount = true;
    //     }

    //     // Variants
    //     foreach ($product->variants as $variant) {
    //         $variantConversionFactor = $variant->product->baseUnit->conversion_factor ?? 1;
    //         $variantQuantity = $variant->inventoryStocks->sum('quantity_in_base_unit') ?? 0;
    //         $variant->stock_quantity = $variantQuantity / $variantConversionFactor;
    //         $variant->in_stock = $variant->stock_quantity > 0;

    //         // Variants price logic should be based on their own actual_price, not product's base price
    //         // Assuming ProductVariant model has an actual_price field
    //         $variant->final_price = $variant->actual_price;
    //         $variant->has_discount = false;

    //         if ($applicableRule) {
    //             // Apply same discount percentage to variant's actual_price
    //             $variant->final_price = $variant->actual_price - ($variant->actual_price * ($applicableRule->discount / 100));
    //             $variant->has_discount = true;
    //         }
    //     }

    //     return view('store.product', compact('product')); // Make sure this path is correct
    // }

    public function product($productId)
    {
        // Fetch active discount rules
        $now = now();
        $discountRules = DiscountRule::where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->get();

        // Eager load necessary relationships to avoid N+1 query problems
        // and prepare for stock and discount calculations.
        $product = Product::with([
            'variants.inventoryStocks', // For variant stock calculation
            'variants.product.baseUnit', // For variant conversion factor (if variants link back to product for base unit)
            'inventoryStocks', // For base product stock calculation
            'baseUnit', // For base product conversion factor
            'displayUnit', // If used for base product display
            'category', // For category-based discounts
        ])->findOrFail($productId);

        // --- Apply calculations to the main product ---
        $conversionFactor = $product->baseUnit->conversion_factor ?? 1;
        $baseQuantity = $product->inventoryStocks->sum('quantity_in_base_unit') ?? 0;
        $product->stock_quantity = $baseQuantity / $conversionFactor;
        $product->in_stock = $product->stock_quantity > 0;

        // Default price for the main product
        $product->final_price = $product->actual_price;
        $product->has_discount = false;

        // Find applicable discount rule for the main product
        $applicableRule = $discountRules->first(function ($rule) use ($product) {
            $targetIds = json_decode($rule->target_ids ?? '[]');
            if ($rule->type === 'product') {
                return in_array($product->id, $targetIds);
            } elseif ($rule->type === 'category') {
                return in_array($product->category_id, $targetIds);
            }
            return false;
        });

        // Apply discount to the main product if found
        if ($applicableRule) {
            $product->final_price = $product->actual_price - ($product->actual_price * ($applicableRule->discount / 100));
            $product->has_discount = true;
        }

        // --- Process Variants ---
        foreach ($product->variants as $variant) {
            // Calculate stock for each variant
            // Note: Assuming variant's inventoryStocks refer to its own stock,
            // and variant->product->baseUnit is used for its conversion factor.
            $variantConversionFactor = $variant->product->baseUnit->conversion_factor ?? 1;
            $variantQuantity = $variant->inventoryStocks->sum('quantity_in_base_unit') ?? 0;
            $variant->stock_quantity = $variantQuantity / $variantConversionFactor;
            $variant->in_stock = $variant->stock_quantity > 0;

            // Variants price logic should be based on their own actual_price
            $variant->final_price = $variant->actual_price;
            $variant->has_discount = false;

            // Apply discount to variant if the main product or its category has an applicable rule
            if ($applicableRule) {
                $variant->final_price = $variant->actual_price - ($variant->actual_price * ($applicableRule->discount / 100));
                $variant->has_discount = true;
            }
        }

        // Get distinct colors and sizes for dropdown population
        // This assumes 'color' and 'size' columns exist on product_variants table
        $colors = $product->variants->pluck('color')->filter()->unique()->sort()->values();
        $sizes = $product->variants->pluck('size')->filter()->unique()->sort()->values();

        // Pass the product (with calculated stock and prices), its variants,
        // and distinct attributes to the view.
        return view('store.product', compact('product', 'colors', 'sizes'));
    }


    public function getProductVariant(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'color' => 'nullable|string',
            'size' => 'nullable|string',
        ]);

        $productId = $request->input('product_id');
        $color = $request->input('color');
        $size = $request->input('size');

        // Start with all variants for the given product
        // Eager load inventoryStocks and product.baseUnit for stock calculation
        $query = Product::findOrFail($productId)
            ->variants()
            ->with(['inventoryStocks', 'product.baseUnit']);

        // Filter by color if provided
        if ($color) {
            $query->where('color', $color);
        } else {
            // If color is not selected, ensure it's null in variants
            $query->whereNull('color');
        }

        // Filter by size if provided
        if ($size) {
            $query->where('size', $size);
        } else {
            // If size is not selected, ensure it's null in variants
            $query->whereNull('size');
        }

        // Attempt to find the specific variant
        $variant = $query->first();

        if ($variant) {
            // Recalculate stock for the found variant
            $variantConversionFactor = $variant->product->baseUnit->conversion_factor ?? 1;
            $variantQuantity = $variant->inventoryStocks->sum('quantity_in_base_unit') ?? 0;
            $variant->stock_quantity = $variantQuantity / $variantConversionFactor;
            $variant->in_stock = $variant->stock_quantity > 0;

            // Fetch active discount rules to apply to the variant
            $now = now();
            $discountRules = DiscountRule::where('start_date', '<=', $now)
                ->where('end_date', '>=', $now)
                ->get();

            // Find applicable discount rule for the parent product or its category
            $parentProduct = Product::with('category')->find($productId);
            $applicableRule = $discountRules->first(function ($rule) use ($parentProduct) {
                $targetIds = json_decode($rule->target_ids ?? '[]');
                if ($rule->type === 'product') {
                    return in_array($parentProduct->id, $targetIds);
                } elseif ($rule->type === 'category') {
                    return in_array($parentProduct->category_id, $targetIds);
                }
                return false;
            });

            $variantFinalPrice = $variant->actual_price;
            $variantHasDiscount = false;

            if ($applicableRule) {
                $variantFinalPrice = $variant->actual_price - ($variant->actual_price * ($applicableRule->discount / 100));
                $variantHasDiscount = true;
            }

            return response()->json([
                'success' => true,
                'variant' => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'actual_price' => $variant->actual_price,
                    'final_price' => $variantFinalPrice, // The discounted price
                    'has_discount' => $variantHasDiscount,
                    'stock_quantity' => $variant->stock_quantity, // Actual calculated stock
                    'is_in_stock' => $variant->in_stock, // Based on calculated stock
                    'product_img' => $variant->product_img ? asset('storage/' . $variant->product_img) : null,
                    'variant_name' => $variant->variant_name // For display
                ]
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Variant not found or out of stock.',
                'is_in_stock' => false
            ], 404);
        }
    }

}
