<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WearableAdherence extends Model
{
    protected $table = 'hsp_wearable_adherences';

    protected $fillable = [
        'patient_id',
        'record_date',
        'daily_steps',
        'sleep_hours',
        'adherence_score',
        'pts_rewarded',
        'proof_hash',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
