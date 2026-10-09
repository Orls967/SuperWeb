<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabSpecimen extends Model
{
    protected $table = 'hsp_lab_specimens';

    protected $fillable = [
        'specimen_barcode',
        'patient_id',
        'encounter_id',
        'sample_type',
        'collected_at',
        'transport_temp_c',
        'custody_hash',
        'status',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class, 'specimen_id');
    }
}
