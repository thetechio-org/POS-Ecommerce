<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Unit;
use App\Models\Supplier;
use App\Models\ProductVariant;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Validator;

class ProductController extends Controller
{
    public function index(){
        $title = 'Products List';
        $products = Product::paginate(10);
        return view('admin.product.list', compact('products', 'title'));
    }

    public function create(){
        $title = 'Add Product';
        $categories = Category::whereNotNull('parent_id')
            ->orWhereDoesntHave('children')
            ->get();
        $units = Unit::all();
        $suppliers = Supplier::all();
        return view('admin.product.form', compact('title', 'categories', 'units', 'suppliers'));
    }

    public function store(Request $request){
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'base_unit_id' => 'required|exists:units,id',
            'has_variants' => 'required|boolean',
            'sku' => 'required|unique:products,sku',
            'brand' => 'nullable|string|max:255',
            'low_stock' => 'nullable|numeric',
            'actual_price' => 'required|numeric|min:0',
            'product_img' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'supplier_ids' => 'nullable|array',
            'supplier_ids.*' => 'exists:suppliers,id',
        ]);

        $product = new Product();
        $product->fill($validatedData);
        $product->default_display_unit_id = $validatedData['base_unit_id'];

        // Handle main product image
        if ($request->hasFile('product_img')) {
            $imagePath = $request->file('product_img')->store('products', 'public');
            $product->product_img = $imagePath;
        }

        $product->save();

        // Auto-generate product barcode
        $product->barcode = 'PRD-' . str_pad($product->id, 5, '0', STR_PAD_LEFT); // e.g. PRD-00001
        $product->save();

        // Sync suppliers
        if ($request->filled('supplier_ids')) {
            $product->suppliers()->sync($request->supplier_ids);
        }

        // Save product variants
        if ($request->has('variants')) {
            foreach ($request->variants as $index => $variantData) {
                $variantImagePath = null;

                if ($request->hasFile("variants.$index.product_img")) {
                    $variantImagePath = $request->file("variants.$index.product_img")
                        ->store('products/variants', 'public');
                }

                $variant = $product->variants()->create([
                    'variant_name' => $variantData['variant_name'],
                    'color' => $variantData['color'],
                    'size' => $variantData['size'],
                    'sku' => $variantData['sku'],
                    'actual_price' => $variantData['actual_price'] ?? 0,
                    'low_stock' => $variantData['low_stock'] ?? 0,
                    'product_img' => $variantImagePath,
                ]);

                // Auto-generate variant barcode
                $variant->barcode = 'VAR-' . str_pad($product->id, 5, '0', STR_PAD_LEFT) . '-' . str_pad($variant->id, 3, '0', STR_PAD_LEFT);
                $variant->save();
            }
        }

        return redirect()->route('products.list')->with('success', 'Product created successfully.');
    }

    public function edit($id) {
        $title = 'Edit Product';
        $categories = Category::whereNotNull('parent_id')
            ->orWhereDoesntHave('children')
            ->get();
        $units = Unit::all();
        $product = Product::with('suppliers')->findOrFail($id); // Load attached suppliers
        $suppliers = Supplier::all(); // Load all suppliers

        return view('admin.product.form', compact('product', 'title', 'categories', 'units', 'suppliers'));
    }

    public function update(Request $request, $id){
        $product = Product::findOrFail($id);

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'base_unit_id' => 'required|exists:units,id',
            'has_variants' => 'required|boolean',
            'sku' => [
                'required',
                Rule::unique('products')->ignore($id)->whereNull('deleted_at'),
            ],
            'barcode' => [
                'nullable',
                Rule::unique('products')->ignore($id)->whereNull('deleted_at'),
            ],
            'brand' => 'nullable|string|max:255',
            'low_stock' => 'nullable|numeric',
            'actual_price' => 'required|numeric|min:0',
            'product_img' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'supplier_ids' => 'nullable|array',
            'supplier_ids.*' => 'exists:suppliers,id',
        ]);

        $product->fill($validatedData);

        // Upload product image
        if ($request->hasFile('product_img')) {
            try {
                $imagePath = $request->file('product_img')->store('products', 'public');
                $product->product_img = $imagePath;
            } catch (\Exception $e) {
                return back()->withInput()->withErrors(['product_img' => 'Error uploading image: ' . $e->getMessage()]);
            }
        }

        $product->save();

        // Generate barcode if missing
        if (empty($product->barcode)) {
            $product->barcode = 'PRD-' . str_pad($product->id, 5, '0', STR_PAD_LEFT);
            $product->save();
        }

        // Sync suppliers
        if ($request->filled('supplier_ids')) {
            $product->suppliers()->sync($request->supplier_ids);
        }

        // Handle variants
        if ($request->has_variants && $request->has('variants')) {
            $incomingVariants = collect($request->input('variants'));
            $existingVariants = $product->variants()->get()->keyBy('sku');

            $existingSkus = $existingVariants->keys();
            $incomingSkus = $incomingVariants->pluck('sku');

            foreach ($incomingVariants as $index => $variantData) {
                $sku = $variantData['sku'];
                if (!$sku) continue;

                $variant = $existingVariants->get($sku);

                if ($variant) {
                    // Update
                    $needsUpdate = (
                        $variant->variant_name !== $variantData['variant_name'] ||
                        $variant->barcode !== ($variantData['barcode'] ?? null) ||

                        $variant->color !== ($variantData['color'] ?? null) ||
                        $variant->size !== ($variantData['size'] ?? null) ||

                        $variant->actual_price != ($variantData['actual_price'] ?? 0) ||
                        $variant->low_stock != ($variantData['low_stock'] ?? 0)
                    );

                    if ($needsUpdate) {
                        $variant->update([
                            'variant_name' => $variantData['variant_name'],
                            'barcode' => $variantData['barcode'] ?? $variant->barcode,
                            'actual_price' => $variantData['actual_price'] ?? 0,
                            'low_stock' => $variantData['low_stock'] ?? 0,
                            'color' => $variantData['color'] ?? $variant->color,
                            'size' => $variantData['size'] ?? $variant->size,
                            
                        ]);
                    }

                    // Image
                    if ($request->hasFile("variants.{$index}.product_img")) {
                        $image = $request->file("variants.{$index}.product_img");
                        $path = $image->store('products/variants', 'public');
                        $variant->update(['product_img' => $path]);
                    }

                } else {
                    // New variant
                    if (!ProductVariant::where('sku', $sku)->exists()) {
                        $newVariant = new ProductVariant([
                            'variant_name' => $variantData['variant_name'],
                            'sku' => $sku,
                            'actual_price' => $variantData['actual_price'] ?? 0,
                            'low_stock' => $variantData['low_stock'] ?? 0,
                        ]);

                        // Image
                        if ($request->hasFile("variants.{$index}.product_img")) {
                            $image = $request->file("variants.{$index}.product_img");
                            $path = $image->store('products/variants', 'public');
                            $newVariant->product_img = $path;
                        }

                        $product->variants()->save($newVariant);

                        // Auto-generate barcode
                        $newVariant->barcode = 'VAR-' . str_pad($product->id, 5, '0', STR_PAD_LEFT) . '-' . str_pad($newVariant->id, 3, '0', STR_PAD_LEFT);
                        $newVariant->save();
                    }
                }
            }

            // Optional: Remove variants not in the request
            $product->variants()->whereNotIn('sku', $incomingSkus->toArray())->delete();
        }

        return redirect()->route('products.list')->with('success', 'Product updated successfully.');
    }

    public function destroy($id){
        $product = Product::findOrFail($id);
        if (!empty($product->product_img) && Storage::disk('public')->exists($product->product_img)) {
            Storage::disk('public')->delete($product->product_img);
        }
        $product->variants()->withTrashed()->forceDelete();
        $product->delete();
        return redirect()->route('products.list')->with('success', 'Product deleted successfully.');
    }

    public function viewVariants($id){
        $title = 'Product Varients';
        $product = Product::with('variants')->findOrFail($id);
        return view('admin.product.variants', compact('product', 'title'));
    }

    public function search(Request $request)
    {
        $query = $request->input('q');
        $barcode = $request->input('barcode');

        $products = Product::with(['variants:id,product_id,variant_name,actual_price', 'baseUnit:id,name', 'inventoryStocks:id,product_id,quantity_in_base_unit'])
            ->where(function ($q) use ($query, $barcode) {
                if ($barcode) {
                    $q->where('barcode', $barcode);
                } else {
                    $q->where('name', 'like', '%' . $query . '%')
                      ->orWhere('sku', 'like', '%' . $query . '%')
                      ->orWhere('barcode', $query);
                }
            })
            ->limit(20)
            ->get()
            ->map(function ($product) {
                $totalStock = $product->inventoryStocks->sum('quantity_in_base_unit');
                return [
                    'id'           => $product->id,
                    'name'         => $product->name,
                    'unit'         => $product->baseUnit->name ?? '',
                    'unit_id'      => $product->base_unit_id,
                    'price'        => (float) $product->actual_price,
                    'stock'        => (int) $totalStock,
                    'has_variants' => (bool) $product->has_variants,
                    'variants'     => $product->variants->map(fn($v) => [
                        'id'    => $v->id,
                        'name'  => $v->variant_name,
                        'price' => (float) ($v->actual_price ?? $product->actual_price),
                    ]),
                ];
            });

        return response()->json($products);
    }

}

