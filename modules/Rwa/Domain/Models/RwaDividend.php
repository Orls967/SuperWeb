<?php

declare(strict_types=1);

namespace Modules\Rwa\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RwaDividend extends Model
{
    protected $table = 'rwa_dividends';

    protected $fillable = [
        'rwa_asset_id',
        'total_revenue_pool_idr',
        'distributed_idr',
        'rounding_reserve_idr',
        'distribution_date',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'total_revenue_pool_idr' => 'integer',
        'distributed_idr' => 'integer',
        'rounding_reserve_idr' => 'integer',
        'distribution_date' => 'date',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(RwaAsset::class, 'rwa_asset_id');
    }
}
