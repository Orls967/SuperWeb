<?php

declare(strict_types=1);

namespace Modules\Wms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Wms\Domain\Models\Warehouse;

/**
 * Proposal ROI otomasi gudang (J3, simulasi) dengan persetujuan ApprovalEngine.
 */
class RoboticsRoiProposal extends Model
{
    protected $table = 'wms_robotics_roi_proposals';

    protected $fillable = [
        'warehouse_id',
        'proposal_code',
        'title',
        'investment_minor',
        'cash_flows_minor',
        'discount_rate_num',
        'discount_rate_den',
        'npv_minor',
        'payback_months',
        'recommendation',
        'status',
        'parameter_label',
        'justification',
        'certificate_hash',
        'created_by_user_id',
        'approved_by_user_id',
        'approved_at',
    ];

    protected $casts = [
        'investment_minor' => 'integer',
        'cash_flows_minor' => 'array',
        'discount_rate_num' => 'integer',
        'discount_rate_den' => 'integer',
        'npv_minor' => 'integer',
        'payback_months' => 'integer',
        'created_by_user_id' => 'integer',
        'approved_by_user_id' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
