<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Resto\Domain\Enums\RecipeLineType;

class RecipeLine extends Model
{
    protected $table = 'resto_recipe_lines';

    protected $fillable = [
        'recipe_id',
        'line_type',
        'ingredient_id',
        'sub_recipe_id',
        'qty_base_unit',
        'note',
    ];

    protected $casts = [
        'line_type' => RecipeLineType::class,
        'qty_base_unit' => 'string',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    public function subRecipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'sub_recipe_id');
    }

    public function quantity(): BigDecimal
    {
        return BigDecimal::of($this->qty_base_unit ?: '0');
    }
}
