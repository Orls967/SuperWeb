<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MiningEquipment extends Model
{
    protected $table = 'min_equipment';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'site_id',
        'equipment_code',
        'type',
        'model',
        'capacity_ton',
        'status',
        'engine_hours',
    ];

    protected $casts = [
        'capacity_ton' => 'decimal:2',
        'engine_hours' => 'integer',
    ];
}
