<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Shared\Domain\Traits\HasUuid;

class Property extends Model
{
    use HasUuid;

    protected $table = 'mall_properties';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'address',
        'city',
        'total_floors',
        'gla_sqm',
        'is_active',
    ];

    protected $casts = [
        'total_floors' => 'integer',
        'gla_sqm' => 'float',
        'is_active' => 'boolean',
    ];

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class, 'property_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'property_id');
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class, 'property_id');
    }
}
