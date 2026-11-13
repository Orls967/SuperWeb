<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot utilisasi bin & analisis dead stock per gudang/zona (X41-4).
 */
class UtilizationSnapshot extends Model
{
    protected $table = 'wms_utilization_snapshots';

    protected $fillable = [
        'warehouse_id',
        'zone_id',
        'snapshot_date',
        'total_bins',
        'occupied_bins',
        'utilization_bps',
        'dead_stock_sku_count',
        'slotting_mismatch_count',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'zone_id' => 'integer',
        'snapshot_date' => 'date',
        'total_bins' => 'integer',
        'occupied_bins' => 'integer',
        'utilization_bps' => 'integer',
        'dead_stock_sku_count' => 'integer',
        'slotting_mismatch_count' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
