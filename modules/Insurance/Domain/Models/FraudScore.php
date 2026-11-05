<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FraudScore extends Model
{
    protected $table = 'ins_fraud_scores';

    /** @var list<string> */
    protected $fillable = [
        'assessment_key',
        'target_type',
        'target_ref',
        'risk_score',
        'risk_level',
        'ring_fraud_detected',
        'ring_cluster_nodes',
        'score_breakdown',
        'recommended_action',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'risk_score' => 'integer',
            'ring_fraud_detected' => 'boolean',
            'ring_cluster_nodes' => 'array',
            'score_breakdown' => 'array',
        ];
    }

    /** @return HasOne<SiuInvestigation, $this> */
    public function investigation(): HasOne
    {
        return $this->hasOne(SiuInvestigation::class, 'fraud_assessment_id');
    }
}
