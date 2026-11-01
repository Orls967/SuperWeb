<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MiningRoyaltyReturn extends Model
{
    protected $table = 'min_royalty_returns';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'return_uuid',
        'site_id',
        'commodity',
        'period_local',
        'total_production_kg',
        'benchmark_price_usd_cents_per_ton',
        'exchange_rate_idr_per_usd_1e6',
        'royalty_rate_bps',
        'calculated_royalty_idr',
        'finalized_royalty_idr',
        'delta_royalty_idr',
        'status',
    ];

    protected $casts = [
        'total_production_kg' => 'integer',
        'benchmark_price_usd_cents_per_ton' => 'integer',
        'exchange_rate_idr_per_usd_1e6' => 'integer',
        'royalty_rate_bps' => 'integer',
        'calculated_royalty_idr' => 'integer',
        'finalized_royalty_idr' => 'integer',
        'delta_royalty_idr' => 'integer',
    ];

    public function site()
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }
}
