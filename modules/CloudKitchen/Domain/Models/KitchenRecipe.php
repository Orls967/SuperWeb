<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenRecipe extends Model
{
    protected $table = 'ckt_recipes';

    protected $fillable = [
        'recipe_code',
        'name',
        'brand_name',
        'selling_price_idr',
        'estimated_prep_minutes',
        'is_active',
    ];

    protected $casts = [
        'selling_price_idr' => 'integer',
        'estimated_prep_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class, 'parent_recipe_id');
    }
}
