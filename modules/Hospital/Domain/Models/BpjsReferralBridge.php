<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BpjsReferralBridge extends Model
{
    protected $table = 'hsp_bpjs_referral_bridging';

    protected $fillable = [
        'referral_code',
        'patient_id',
        'tele_consult_id',
        'encounter_id',
        'referral_direction',
        'referral_type',
        'sisrute_reference_number',
        'origin_faskes_code',
        'origin_faskes_name',
        'destination_faskes_code',
        'destination_faskes_name',
        'diagnosis_icd10',
        'clinical_summary',
        'transportation_type',
        'target_polyclinic_or_ward',
        'approval_status',
        'bridged_at',
    ];

    protected $casts = [
        'bridged_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function teleConsult(): BelongsTo
    {
        return $this->belongsTo(TeleConsult::class, 'tele_consult_id');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class, 'encounter_id');
    }
}
