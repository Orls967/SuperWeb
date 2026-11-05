<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class InsuranceCommandSnapshot extends Model
{
    protected $table = 'ins_command_snapshots';

    protected $fillable = [
        'snapshot_date',
        'gross_written_premium_idr',
        'net_earned_premium_idr',
        'claims_incurred_idr',
        'loss_ratio_bps',
        'solvency_rbc_bps',
        'total_technical_reserve_idr',
        'reinsurance_recoverable_idr',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'gross_written_premium_idr' => 'integer',
        'net_earned_premium_idr' => 'integer',
        'claims_incurred_idr' => 'integer',
        'loss_ratio_bps' => 'integer',
        'solvency_rbc_bps' => 'integer',
        'total_technical_reserve_idr' => 'integer',
        'reinsurance_recoverable_idr' => 'integer',
    ];
}
