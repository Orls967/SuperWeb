<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'national_id_nik',
        'nik_bidx',
        'user_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'encrypted_allergies' => 'array',
        'encrypted_chronic_diagnoses' => 'array',
        'national_id_nik' => 'encrypted',
    ];

    public function setNikBlindIndexAttribute(?string $value): void
    {
        $this->attributes['nik_bidx'] = $value;
        $this->attributes['nik_blind_index'] = $value;
    }

    public function getNikBlindIndexAttribute(): ?string
    {
        return $this->attributes['nik_bidx'] ?? $this->attributes['nik_blind_index'] ?? null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class, 'patient_id');
    }

    public function outpatientEncounters(): HasMany
    {
        return $this->hasMany(OutpatientEncounter::class, 'patient_id');
    }
}
