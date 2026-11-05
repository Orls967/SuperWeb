<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SybMustahikGraduation extends Model
{
    protected $table = 'syb_mustahik_graduations';

    protected $fillable = [
        'graduation_code',
        'party_ref',
        'current_stage',
        'target_stage',
        'omzet_baseline_idr',
        'omzet_current_idr',
        'growth_rate_bps',
        'field_officer_verifier',
        'amil_supervisor_verifier',
        'verified_at',
        'status',
    ];

    protected $casts = [
        'omzet_baseline_idr' => 'integer',
        'omzet_current_idr' => 'integer',
        'growth_rate_bps' => 'integer',
        'verified_at' => 'datetime',
    ];
}

