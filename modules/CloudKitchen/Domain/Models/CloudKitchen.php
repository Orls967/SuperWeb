<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CloudKitchen extends Model
{
    protected $table = 'resto_ck_kitchens';

    protected $fillable = [
        'kitchen_code',
        'name',
        'type',
        'hourly_capacity',
        'current_hourly_load',
        'service_radius_km',
        'is_active',
    ];

    protected $casts = [
        'hourly_capacity' => 'integer',
        'current_hourly_load' => 'integer',
        'service_radius_km' => 'float',
        'is_active' => 'boolean',
    ];

    public function orders()
    {
        return $this->hasMany(CateringOrder::class, 'kitchen_id');
    }
}
