<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitalsTelemetry extends Model
{
    protected $table = 'hsp_vitals_telemetries';

    protected $guarded = [];

    protected $casts = [
        'spo2_percent' => 'decimal:2',
        'temp_c' => 'decimal:1',
        'code_blue_triggered' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class, 'encounter_id');
    }
}
