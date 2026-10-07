<?php

declare(strict_types=1);

namespace Modules\Rwa\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RwaDividend extends Model
{
    protected $table = 'rwa_dividends';

    protected $fillable = [
        'rwa_asset_id',
        'total_revenue_pool_idr',
        'distributed_idr',
        'rounding_reserve_idr',
        'distribution_date',
        'idempotency_key',
    ];

    protected $casts = [
        'total_revenue_pool_idr' => 'integer',
        'distributed_idr' => 'integer',
        'rounding_reserve_idr' => 'integer',
        'distribution_date' => 'datetime',
    ];

    public function asset()
    {
        return $this->belongsTo(RwaAsset::class, 'rwa_asset_id');
    }
}
