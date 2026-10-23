<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PatientMedicalRecord extends Model
{
    protected $table = 'hsp_patient_records';

    protected $fillable = [
        'patient_mrn',
        'national_id_nik',
        'full_name',
        'icd10_diagnosis_code',
        'prescription_details',
        'treating_doctor_id',
    ];

    /**
     * Cast sensitive medical & PII fields using AES-256-GCM encryption
     *
     * @var array<string, string>
     */
    protected $casts = [
        'national_id_nik' => 'encrypted',
        'icd10_diagnosis_code' => 'encrypted',
        'prescription_details' => 'encrypted',
        'treating_doctor_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
