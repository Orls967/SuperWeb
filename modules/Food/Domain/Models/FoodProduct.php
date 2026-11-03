<?php

declare(strict_types=1);

namespace Modules\Food\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodProduct extends Model
{
    protected $table = 'food_products';

    protected $fillable = [
        'brand_id',
        'sku',
        'name',
        'category',
        'standard_package_grams',
    ];

    protected $casts = [
        'brand_id' => 'integer',
        'standard_package_grams' => 'integer',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(FoodBrand::class, 'brand_id');
    }

    public function batchNutrition(): HasMany
    {
        return $this->hasMany(FoodBatchNutrition::class, 'food_product_id');
    }
}
