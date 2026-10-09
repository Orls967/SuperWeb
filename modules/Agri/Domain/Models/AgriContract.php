<?php

namespace Modules\Agri\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgriContract extends Model
{
    use HasUuids;

    protected $table = 'agri_contracts';

    protected $fillable = [
        'contract_number',
        'farmer_id',
        'commodity',
        'planting_date',
        'expected_harvest_date',
        'target_yield_kg',
        'seed_advance_value_idr',
        'fertilizer_advance_value_idr',
        'total_advance_deductible_idr',
        'guaranteed_floor_price_idr_per_kg',
        'status',
    ];

    protected $casts = [
        'planting_date' => 'date',
        'expected_harvest_date' => 'date',
        'target_yield_kg' => 'decimal:2',
        'seed_advance_value_idr' => 'integer',
        'fertilizer_advance_value_idr' => 'integer',
        'total_advance_deductible_idr' => 'integer',
        'guaranteed_floor_price_idr_per_kg' => 'integer',
    ];

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(AgriFarmer::class, 'farmer_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(AgriCollectionBatch::class, 'contract_id');
    }
}
