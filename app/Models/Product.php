<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'name',
        'category_id',
        'base_unit_id',
        'default_display_unit_id',
        'has_variants',
        'sku',
        'barcode',
        'brand',
        'track_expiry',
        'tax_rate',
        'actual_price',
        'low_stock',
        'product_img',
    ];

    public function category() {
        return $this->belongsTo(Category::class);
    }

    public function baseUnit() {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function displayUnit() {
        return $this->belongsTo(Unit::class, 'default_display_unit_id');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function inventoryStock()
    {
        return $this->hasOne(InventoryStock::class, 'product_id');
    }

    public function inventoryStocks()
    {
        return $this->hasMany(InventoryStock::class, 'product_id');
    }


    public function getTotalStockAttribute()
    {
        return $this->inventoryStocks()->sum('quantity_in_base_unit');
    }

    public function getIsLowStockAttribute()
    {
        $threshold = $this->low_stock ?? 5;
        return $this->total_stock <= $threshold;
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'product_supplier');
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }


    public function getDiscountedPriceAttribute()
    {
        $price = $this->actual_price;
        $today = now()->toDateString();

        // Static cache: runs once per request regardless of how many products are loaded (N+1 fix)
        static $rules = null;
        if ($rules === null) {
            $rules = \App\Models\DiscountRule::where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->get();
        }

        foreach ($rules as $rule) {
            $ids = json_decode($rule->target_ids, true);
        
            if ($rule->type == 'category' && in_array($this->category_id, $ids)) {
                return $this->applyPercentageDiscount($price, $rule->discount);
            }
        
            if ($rule->type == 'product' && in_array($this->id, $ids)) {
                return $this->applyPercentageDiscount($price, $rule->discount);
            }
        }
    
        return $price;
    }
    
    private function applyPercentageDiscount($price, $discount)
    {
        return round($price - ($price * $discount / 100), 2);
    }

}

