<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Encounter extends Model
{
    protected $table = 'hsp_encounters';

    protected $guarded = [];

    protected $casts = [
        'admitted_at' => 'datetime',
        'discharged_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(HospitalOrder::class, 'encounter_id');
    }

    public function telemetries(): HasMany
    {
        return $this->hasMany(VitalsTelemetry::class, 'encounter_id');
    }
}
