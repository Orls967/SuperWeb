<?php

declare(strict_types=1);

namespace Modules\Agency\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudCheck extends Model
{
    use HasUuids;

    protected $table = 'agy_fraud_checks';

    protected $fillable = [
        'agent_id', 'check_type', 'risk_score', 'decision', 'reason', 'metrics',
    ];

    protected $casts = [
        'risk_score' => 'integer',
        'metrics' => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }
}
