<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CanaryObservation extends Model
{
    use HasFactory;

    protected $table = 'plt_canary_observations';

    protected $fillable = [
        'deployment_id',
        'sample_window_start',
        'sample_window_end',
        'baseline_requests',
        'baseline_errors',
        'canary_requests',
        'canary_errors',
        'z_score',
        'relative_risk_bps',
        'recommendation',
    ];

    protected $casts = [
        'sample_window_start' => 'datetime',
        'sample_window_end' => 'datetime',
        'baseline_requests' => 'integer',
        'baseline_errors' => 'integer',
        'canary_requests' => 'integer',
        'canary_errors' => 'integer',
        'z_score' => 'integer',
        'relative_risk_bps' => 'integer',
    ];

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(ChangeDeployment::class, 'deployment_id');
    }
}
