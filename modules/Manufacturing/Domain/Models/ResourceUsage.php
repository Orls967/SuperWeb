<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pemakaian energi/air/limbah per order produksi (dasar ESG). */
class ResourceUsage extends Model
{
    protected $table = 'mfg_resource_usages';

    protected $fillable = [
        'production_order_id', 'work_center_id', 'resource', 'qty', 'unit',
        'cost_idr', 'recorded_at', 'recorded_by_user_id',
    ];

    protected $casts = [
        'qty' => 'decimal:6', 'cost_idr' => 'integer',
        'recorded_at' => 'datetime', 'recorded_by_user_id' => 'integer',
    ];

    public const RESOURCES = ['electricity_kwh', 'water_liter', 'waste_kg'];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }
}
