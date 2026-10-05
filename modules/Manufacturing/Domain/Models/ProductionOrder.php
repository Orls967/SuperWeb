<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Order produksi: planned → released → in_progress → completed → closed|cancelled. */
class ProductionOrder extends Model
{
    use HasUuids;

    protected $table = 'mfg_production_orders';

    protected $fillable = [
        'number', 'material_id', 'plant_id', 'planned_order_id', 'routing_id', 'kind',
        'qty', 'qty_completed', 'status', 'release_date', 'due_date',
        'scrap_tolerance_percent', 'notes', 'created_by_user_id',
        'released_at', 'started_at', 'completed_at', 'closed_at',
    ];

    protected $casts = [
        'qty' => 'decimal:6', 'qty_completed' => 'decimal:6',
        'scrap_tolerance_percent' => 'decimal:4',
        'release_date' => 'date', 'due_date' => 'date',
        'created_by_user_id' => 'integer',
        'released_at' => 'datetime', 'started_at' => 'datetime',
        'completed_at' => 'datetime', 'closed_at' => 'datetime',
    ];

    /** Transisi status yang sah. */
    private const ALLOWED = [
        'planned' => ['released', 'cancelled'],
        'released' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => ['closed'],
        'closed' => [],
        'cancelled' => [],
    ];

    public function canTransitionTo(string $to): bool
    {
        return in_array($to, self::ALLOWED[$this->status] ?? [], true);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class, 'routing_id');
    }

    public function plannedOrder(): BelongsTo
    {
        return $this->belongsTo(PlannedOrder::class, 'planned_order_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(MaterialIssue::class, 'production_order_id');
    }

    public function operationReports(): HasMany
    {
        return $this->hasMany(OperationReport::class, 'production_order_id')->orderBy('sequence');
    }

    public function fgReceipts(): HasMany
    {
        return $this->hasMany(FgReceipt::class, 'production_order_id');
    }

    public function reworks(): HasMany
    {
        return $this->hasMany(ReworkRecord::class, 'production_order_id');
    }

    public function orderCost(): HasOne
    {
        return $this->hasOne(OrderCost::class, 'order_id');
    }
}
