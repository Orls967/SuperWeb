<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PhaseMetricSnapshot extends Model
{
    protected $table = 'plt_phase_metrics';

    protected $fillable = [
        'phase_id',
        'b1_loc',
        'b2_loc',
        'b3_loc',
        'decision_density',
        'duplicate_ratio',
        'orphan_count',
        'status',
        'certificate_hash',
        'breakdown',
    ];

    protected $casts = [
        'decision_density' => 'float',
        'duplicate_ratio' => 'float',
        'breakdown' => 'array',
    ];
}
