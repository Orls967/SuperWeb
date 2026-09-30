<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientCost extends Model
{
    protected $table = 'resto_ingredient_costs';

    protected $fillable = [
        'ingredient_id',
        'outlet_id',
        'moving_avg_cost_per_base_unit',
        'last_purchase_cost',
    ];

    protected $casts = [
        'moving_avg_cost_per_base_unit' => 'string',
        'last_purchase_cost' => 'string',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function movingAvgCost(): BigDecimal
    {
        return BigDecimal::of($this->moving_avg_cost_per_base_unit ?: '0');
    }

    public function lastPurchaseCost(): BigDecimal
    {
        return BigDecimal::of($this->last_purchase_cost ?: '0');
    }
}
