<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ChurnPrediction extends Model
{
    protected $table = 'tlx_churn_predictions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'usage_decline_pct' => 'float',
        'support_tickets_count' => 'integer',
        'churn_risk_score' => 'float',
    ];
}
