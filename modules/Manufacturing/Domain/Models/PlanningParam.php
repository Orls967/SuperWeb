<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Parameter perencanaan: stok pengaman, lead time, MOQ, lot sizing. */
class PlanningParam extends Model
{
    use HasUuids;

    protected $table = 'mfg_planning_params';

    protected $fillable = [
        'material_id', 'safety_stock', 'reorder_point', 'lead_time_days', 'moq',
        'lot_sizing', 'fixed_order_qty', 'period_weeks', 'ordering_cost_idr',
        'holding_cost_per_unit_year_idr',
    ];

    protected $casts = [
        'safety_stock' => 'decimal:6', 'reorder_point' => 'decimal:6',
        'lead_time_days' => 'integer', 'moq' => 'decimal:6',
        'fixed_order_qty' => 'decimal:6', 'period_weeks' => 'integer',
        'ordering_cost_idr' => 'integer', 'holding_cost_per_unit_year_idr' => 'integer',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
