<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MiningShovelSlot extends Model
{
    protected $table = 'min_shovel_slots';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'equipment_id',
        'area_id',
        'slot_code',
        'hour_window',
        'capacity_trucks_per_hour',
    ];

    protected $casts = [
        'hour_window' => 'integer',
        'capacity_trucks_per_hour' => 'integer',
    ];

    public function equipment()
    {
        return $this->belongsTo(MiningEquipment::class, 'equipment_id');
    }

    public function area()
    {
        return $this->belongsTo(MiningArea::class, 'area_id');
    }
}
