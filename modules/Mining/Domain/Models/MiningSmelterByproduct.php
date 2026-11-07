<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningSmelterByproduct extends Model
{
    use HasUuids;

    protected $table = 'min_smelter_byproducts';

    protected $fillable = [
        'id',
        'furnace_run_id',
        'byproduct_code',
        'byproduct_type',
        'generated_quantity_tons',
        'dispatched_quantity_tons',
        'stock_remaining_tons',
        'unit_price_idr_per_ton',
        'total_revenue_idr',
        'buyer_party_code',
        'utilization_application',
        'environmental_manifest_number',
        'status',
        'metadata',
    ];

    protected $casts = [
        'generated_quantity_tons' => 'float',
        'dispatched_quantity_tons' => 'float',
        'stock_remaining_tons' => 'float',
        'unit_price_idr_per_ton' => 'integer',
        'total_revenue_idr' => 'integer',
        'metadata' => 'array',
    ];

    public function furnaceRun(): BelongsTo
    {
        return $this->belongsTo(MiningSmelterFurnaceRun::class, 'furnace_run_id');
    }
}
