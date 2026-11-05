<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuaranteeLetter extends Model
{
    protected $table = 'ins_lh_guarantee_letters';

    /** @var list<string> */
    protected $fillable = [
        'gl_number',
        'health_policy_id',
        'hospital_encounter_ref',
        'hospital_party_id',
        'diagnosis_icd10',
        'approved_ceiling_idr',
        'status',
        'issued_at',
        'valid_until',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'health_policy_id' => 'integer',
            'approved_ceiling_idr' => 'integer',
            'issued_at' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    /** @return BelongsTo<HealthPolicy, $this> */
    public function healthPolicy(): BelongsTo
    {
        return $this->belongsTo(HealthPolicy::class, 'health_policy_id');
    }

    /** @return HasMany<HealthClaim, $this> */
    public function claims(): HasMany
    {
        return $this->hasMany(HealthClaim::class, 'guarantee_letter_id');
    }
}
