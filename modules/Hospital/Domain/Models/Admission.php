<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Admisi Rawat Inap (Append-Only Lifecycle).
 */
class Admission extends Model
{
    protected $table = 'hsp_admissions';

    protected $fillable = [
        'admission_no',
        'patient_id',
        'encounter_id',
        'bed_id',
        'entitled_class',
        'current_class',
        'is_upgraded',
        'upgrade_absorbed_by_hospital',
        'status',
        'deposit_idr',
        'total_billed_idr',
        'admitted_at',
        'discharged_at',
    ];

    protected $casts = [
        'is_upgraded' => 'boolean',
        'upgrade_absorbed_by_hospital' => 'boolean',
        'deposit_idr' => 'integer',
        'total_billed_idr' => 'integer',
        'admitted_at' => 'datetime',
        'discharged_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(HospitalBed::class, 'bed_id');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class, 'encounter_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(AdmissionTransition::class, 'admission_id');
    }

    /**
     * @param  array<string, mixed>  $assessmentPayload
     */
    public function recordClinicalAssessment(string $assessmentKey, array $assessmentPayload): void
    {
        $meta = is_array($this->metadata) ? $this->metadata : (json_decode($this->metadata ?? '[]', true) ?: []);
        $meta[$assessmentKey] = $assessmentPayload;
        $this->metadata = $meta;
        $this->save();
    }

    public function codeBlueEvents(): HasMany
    {
        return $this->hasMany(CodeBlueEvent::class, 'admission_id');
    }
}
