<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitalsTelemetry extends Model
{
    protected $table = 'hsp_vitals_telemetries';

    protected $fillable = [
        'encounter_id',
        'spo2_percent',
        'heart_rate_bpm',
        'temp_c',
        'code_blue_triggered',
        'proof_hash',
        'recorded_at',
    ];

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
