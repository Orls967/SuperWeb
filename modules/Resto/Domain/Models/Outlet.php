<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Resto\Domain\Enums\OutletType;
use Modules\Shared\Domain\Traits\HasUuid;

class Outlet extends Model
{
    use HasUuid;

    protected $table = 'resto_outlets';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'type',
        'address',
        'city',
        'phone',
        'mall_unit_ref',
        'seats',
        'opens_at',
        'closes_at',
        'is_active',
    ];

    protected $casts = [
        'type' => OutletType::class,
        'seats' => 'integer',
        'is_active' => 'boolean',
    ];

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(RestoStaffAssignment::class, 'outlet_id');
    }

    public function ingredientCosts(): HasMany
    {
        return $this->hasMany(IngredientCost::class, 'outlet_id');
    }

    public function menuItemOverrides(): HasMany
    {
        return $this->hasMany(MenuItemOutlet::class, 'outlet_id');
    }

    public function isCentralKitchen(): bool
    {
        return $this->type === OutletType::CENTRAL_KITCHEN;
    }
}
