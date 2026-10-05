<?php

declare(strict_types=1);

namespace Modules\Partner\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerScorecard extends Model
{
    use HasUuids;

    protected $table = 'ptn_scorecards';

    protected $fillable = [
        'partner_id', 'period', 'score', 'sla_compliance_percent', 'penalties_idr', 'remediation_plan',
    ];

    protected $casts = [
        'score' => 'integer',
        'sla_compliance_percent' => 'float',
        'penalties_idr' => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}
