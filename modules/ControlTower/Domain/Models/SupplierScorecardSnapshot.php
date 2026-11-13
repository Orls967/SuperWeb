<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Snapshot kartu skor kinerja pemasok multi-kriteria (X53-2).
 */
class SupplierScorecardSnapshot extends Model
{
    protected $table = 'sct_supplier_scorecards';

    protected $fillable = [
        'supplier_ref',
        'supplier_name',
        'period',
        'composite_score',
        'otd_score',
        'reliability_score',
        'volume_score',
        'on_time_permil',
        'mean_lead_time_days',
        'sigma_lead_time_days',
        'samples_count',
        'tier',
        'trend_json',
        'action_recommendation',
        'computed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'composite_score' => 'integer',
            'otd_score' => 'integer',
            'reliability_score' => 'integer',
            'volume_score' => 'integer',
            'on_time_permil' => 'integer',
            'mean_lead_time_days' => 'integer',
            'sigma_lead_time_days' => 'integer',
            'samples_count' => 'integer',
            'trend_json' => 'array',
            'computed_at' => 'datetime',
        ];
    }
}
