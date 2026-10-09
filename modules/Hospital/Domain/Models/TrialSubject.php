<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrialSubject extends Model
{
    protected $table = 'hsp_trial_subjects';

    protected $fillable = [
        'subject_code',
        'trial_id',
        'patient_id',
        'anonymized_hash',
        'informed_consent_hash',
        'randomized_arm',
        'status',
    ];

    public function trial(): BelongsTo
    {
        return $this->belongsTo(ClinicalTrial::class, 'trial_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
