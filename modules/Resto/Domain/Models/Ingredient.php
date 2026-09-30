<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Resto\Domain\Enums\BaseUnit;
use Modules\Resto\Domain\Enums\IngredientCategory;
use Modules\Shared\Domain\Traits\HasUuid;

class Ingredient extends Model
{
    use HasUuid;

    protected $table = 'resto_ingredients';

    protected $fillable = [
        'uuid',
        'sku',
        'name',
        'base_unit',
        'category',
        'is_perishable',
        'shelf_life_hours',
        'min_stock_base_unit',
    ];

    protected $casts = [
        'base_unit' => BaseUnit::class,
        'category' => IngredientCategory::class,
        'is_perishable' => 'boolean',
        'shelf_life_hours' => 'integer',
        'min_stock_base_unit' => 'string',
    ];

    public function conversions(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'ingredient_id');
    }

    public function costs(): HasMany
    {
        return $this->hasMany(IngredientCost::class, 'ingredient_id');
    }

    public function costForOutlet(int $outletId): ?IngredientCost
    {
        return $this->costs()->where('outlet_id', $outletId)->first();
    }
}
