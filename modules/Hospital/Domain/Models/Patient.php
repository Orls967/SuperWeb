<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    protected $table = 'hsp_patients';

    protected $fillable = [
        'mrn',
        'name',
        'date_of_birth',
        'blood_type',
        'encrypted_allergies',
        'encrypted_chronic_diagnoses',
        'passport_hash',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'encrypted_allergies' => 'array',
        'encrypted_chronic_diagnoses' => 'array',
    ];

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class, 'patient_id');
    }
}
