<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SybSocialImpactMetric extends Model
{
    protected $table = 'syb_social_impact_metrics';

    protected $fillable = [
        'period_year',
        'total_beneficiaries',
        'jobs_created',
        'mustahik_to_muzakki_count',
        'avg_income_increase_bps',
        'micro_npf_ratio_bps',
        'notes',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'total_beneficiaries' => 'integer',
        'jobs_created' => 'integer',
        'mustahik_to_muzakki_count' => 'integer',
        'avg_income_increase_bps' => 'integer',
        'micro_npf_ratio_bps' => 'integer',
    ];
}

