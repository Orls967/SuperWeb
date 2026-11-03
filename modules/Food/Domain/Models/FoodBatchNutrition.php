<?php

declare(strict_types=1);

namespace Modules\Food\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodBatchNutrition extends Model
{
    protected $table = 'food_batch_nutrition';

    protected $fillable = [
        'batch_id',
        'food_product_id',
        'calories_kcal',
        'protein_grams',
        'fat_grams',
        'carbs_grams',
        'sodium_mg',
        'serving_size_grams',
    ];

    protected $casts = [
        'batch_id' => 'integer',
        'food_product_id' => 'integer',
        'calories_kcal' => 'integer',
        'protein_grams' => 'decimal:1',
        'fat_grams' => 'decimal:1',
        'carbs_grams' => 'decimal:1',
        'sodium_mg' => 'integer',
        'serving_size_grams' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(FoodProduct::class, 'food_product_id');
    }
}
