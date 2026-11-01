<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmartMeter extends Model
{
    protected $table = 'egy_smart_meters';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'meter_serial_number',
        'consumer_property_type',
        'consumer_property_id',
        'grid_node_id',
        'total_kwh_accumulated',
        'tariff_category',
        'modulus',
        'device_token',
        'status',
    ];

    protected $casts = [
        'total_kwh_accumulated' => 'float',
        'modulus' => 'integer',
    ];

    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class, 'meter_id');
    }
}
