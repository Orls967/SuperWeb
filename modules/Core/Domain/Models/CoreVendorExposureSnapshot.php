<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cross-module vendor group credit exposure snapshot (W8-D57).
 */
class CoreVendorExposureSnapshot extends Model
{
    protected $table = 'core_vendor_exposure_snapshots';

    protected $fillable = [
        'snapshot_code',
        'vendor_cluster_code',
        'total_exposure_idr',
        'group_credit_limit_idr',
        'active_tranche_count',
        'is_limit_breached',
        'module_breakdown',
        'integrity_sha256',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'total_exposure_idr' => 'integer',
            'group_credit_limit_idr' => 'integer',
            'active_tranche_count' => 'integer',
            'is_limit_breached' => 'boolean',
            'module_breakdown' => 'array',
            'calculated_at' => 'datetime',
        ];
    }
}
