<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

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
        'status',
    ];

    protected $casts = [
        'total_kwh_accumulated' => 'float',
    ];
}
