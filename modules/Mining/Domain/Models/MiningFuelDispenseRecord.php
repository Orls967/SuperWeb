<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningFuelDispenseRecord extends Model
{
    use HasUuids;

    protected $table = 'min_fuel_dispense_records';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'dispense_code',
        'storage_id',
        'equipment_id',
        'volume_ml',
        'equipment_engine_hours',
        'operator_ref',
        'flowmeter_start_reading',
        'flowmeter_end_reading',
        'dispensed_at',
    ];

    protected $casts = [
        'volume_ml' => 'integer',
        'equipment_engine_hours' => 'integer',
        'dispensed_at' => 'datetime',
    ];

    public function storage(): BelongsTo
    {
        return $this->belongsTo(MiningFuelStorage::class, 'storage_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(MiningEquipment::class, 'equipment_id');
    }
}

