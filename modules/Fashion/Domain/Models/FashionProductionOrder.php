<?php

declare(strict_types=1);

namespace Modules\Fashion\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FashionProductionOrder extends Model
{
    protected $table = 'fsh_production_orders';

    protected $fillable = [
        'buy_plan_id',
        'sample_id',
        'po_number',
        'factory_name',
        'total_ordered_units',
        'total_cost_idr',
        'status',
    ];

    protected $casts = [
        'total_ordered_units' => 'integer',
        'total_cost_idr' => 'integer',
    ];

    public function buyPlan(): BelongsTo
    {
        return $this->belongsTo(FashionBuyPlan::class, 'buy_plan_id');
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(FashionSample::class, 'sample_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FashionProductionAllocation::class, 'production_order_id');
    }
}
