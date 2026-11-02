<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeIngredient extends Model
{
    protected $table = 'ckt_recipe_ingredients';

    protected $fillable = [
        'parent_recipe_id',
        'child_recipe_id',
        'ingredient_code',
        'quantity_required',
        'unit',
        'cost_per_unit_idr',
    ];

    protected $casts = [
        'parent_recipe_id' => 'integer',
        'child_recipe_id' => 'integer',
        'quantity_required' => 'integer',
        'cost_per_unit_idr' => 'integer',
    ];

    public function parentRecipe(): BelongsTo
    {
        return $this->belongsTo(KitchenRecipe::class, 'parent_recipe_id');
    }

    public function childRecipe(): BelongsTo
    {
        return $this->belongsTo(KitchenRecipe::class, 'child_recipe_id');
    }
}
