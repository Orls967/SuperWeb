<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CoreAiGovernanceDriftLog extends Model
{
    protected $table = 'core_ai_governance_drift_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'log_code',
        'model_name',
        'data_drift_score',
        'concept_drift_score',
        'requires_retraining',
        'checked_at',
    ];

    protected $casts = [
        'data_drift_score' => 'float',
        'concept_drift_score' => 'float',
        'requires_retraining' => 'boolean',
        'checked_at' => 'datetime',
    ];
}
