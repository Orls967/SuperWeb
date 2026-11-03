<?php

declare(strict_types=1);

namespace Modules\Food\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodBrand extends Model
{
    protected $table = 'food_brands';

    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(FoodProduct::class, 'brand_id');
    }
}
