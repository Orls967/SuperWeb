<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CoreEcosystemHealthScore extends Model
{
    protected $table = 'core_ecosystem_health_scores';

    protected $fillable = [
        'entity_code',
        'financial_pillar_score',
        'talent_pillar_score',
        'esg_pillar_score',
        'risk_pillar_score',
        'composite_health_score',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'financial_pillar_score' => 'integer',
        'talent_pillar_score' => 'integer',
        'esg_pillar_score' => 'integer',
        'risk_pillar_score' => 'integer',
        'composite_health_score' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
