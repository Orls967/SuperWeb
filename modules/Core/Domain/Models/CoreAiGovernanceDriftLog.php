<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CoreAiGovernanceDriftLog extends Model
{
    protected $table = 'core_ai_governance_drift_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'data_drift_score' => 'float',
        'concept_drift_score' => 'float',
        'requires_retraining' => 'boolean',
        'checked_at' => 'datetime',
    ];
}
