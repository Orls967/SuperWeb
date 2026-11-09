<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuaranteeLetter extends Model
{
    protected $table = 'hsp_guarantee_letters';

    protected $fillable = [
        'guarantee_number',
        'patient_id',
        'encounter_id',
        'payer_name',
        'policy_number',
        'guarantee_type',
        'approved_coverage_limit_idr',
        'utilized_amount_idr',
        'deductible_idr',
        'status',
        'valid_until',
        'auth_code',
        'notes',
    ];

    protected $casts = [
        'approved_coverage_limit_idr' => 'integer',
        'utilized_amount_idr' => 'integer',
        'deductible_idr' => 'integer',
        'valid_until' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class, 'encounter_id');
    }

    public function remainingCoverageLimit(): int
    {
        return max(0, $this->approved_coverage_limit_idr - $this->utilized_amount_idr);
    }
}
