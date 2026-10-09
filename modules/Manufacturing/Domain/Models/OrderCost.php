<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Snapshot biaya aktual per order produksi (Fase 38.2). */
class OrderCost extends Model
{
    use HasUuids;

    protected $table = 'mfg_order_costs';

    protected $fillable = [
        'order_id', 'material_idr', 'labor_idr', 'machine_idr', 'overhead_idr',
        'subcontract_idr', 'byproduct_credit_idr', 'total_idr', 'unit_cost_idr', 'computed_at',
    ];

    protected $casts = [
        'material_idr' => 'integer', 'labor_idr' => 'integer', 'machine_idr' => 'integer',
        'overhead_idr' => 'integer', 'subcontract_idr' => 'integer',
        'byproduct_credit_idr' => 'integer', 'total_idr' => 'integer', 'unit_cost_idr' => 'integer',
        'computed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'order_id');
    }
}
