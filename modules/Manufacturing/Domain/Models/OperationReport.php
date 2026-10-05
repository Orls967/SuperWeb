<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Laporan operasi: durasi, qty baik/scrap/rework. */
class OperationReport extends Model
{
    protected $table = 'mfg_operation_reports';

    protected $fillable = [
        'production_order_id', 'routing_operation_id', 'work_center_id', 'worker_id',
        'sequence', 'started_at', 'finished_at', 'duration_minutes',
        'qty_good', 'qty_scrap', 'qty_rework', 'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime', 'finished_at' => 'datetime',
        'duration_minutes' => 'integer',
        'qty_good' => 'decimal:6', 'qty_scrap' => 'decimal:6', 'qty_rework' => 'decimal:6',
        'sequence' => 'integer',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }
}
