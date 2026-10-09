<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Cycle count + penyesuaian via approval (41.5). */
class CycleCount extends Model
{
    use HasUuids;

    protected $table = 'wms_cycle_counts';

    protected $fillable = [
        'number', 'warehouse_id', 'status', 'system_qty', 'counted_qty', 'variance_qty',
        'variance_value_idr', 'accuracy_percent', 'approval_id', 'counted_by_user_id',
    ];

    protected $casts = [
        'system_qty' => 'decimal:6', 'counted_qty' => 'decimal:6', 'variance_qty' => 'decimal:6',
        'variance_value_idr' => 'integer', 'accuracy_percent' => 'decimal:4',
        'approval_id' => 'integer', 'counted_by_user_id' => 'integer',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(CycleCountLine::class, 'count_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
