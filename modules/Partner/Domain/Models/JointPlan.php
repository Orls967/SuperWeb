<?php

declare(strict_types=1);

namespace Modules\Partner\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JointPlan extends Model
{
    use HasUuids;

    protected $table = 'ptn_joint_plans';

    protected $fillable = [
        'partner_id', 'title', 'period', 'budget_idr', 'target_revenue_idr',
        'internal_pic', 'partner_pic', 'kpi_targets', 'status',
    ];

    protected $casts = [
        'budget_idr' => 'integer',
        'target_revenue_idr' => 'integer',
        'kpi_targets' => 'array',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}
