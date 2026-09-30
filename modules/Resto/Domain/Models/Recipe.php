<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    protected $table = 'resto_recipes';

    protected $fillable = [
        'menu_item_id',
        'sub_recipe_name',
        'yield_qty',
        'yield_unit',
        'expected_portions',
        'waste_percent',
        'instructions',
        'version',
        'is_active',
    ];

    protected $casts = [
        'yield_qty' => 'string',
        'expected_portions' => 'string',
        'waste_percent' => 'string',
        'version' => 'integer',
        'is_active' => 'boolean',
    ];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RecipeLine::class, 'recipe_id');
    }

    public function isSubRecipe(): bool
    {
        return ! empty($this->sub_recipe_name);
    }

    public function yieldQuantity(): BigDecimal
    {
        return BigDecimal::of($this->yield_qty ?: '1');
    }

    public function expectedPortions(): BigDecimal
    {
        return BigDecimal::of($this->expected_portions ?: '1');
    }

    public function wastePercent(): BigDecimal
    {
        return BigDecimal::of($this->waste_percent ?: '0');
    }
}
