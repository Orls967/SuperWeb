<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PriceWaterfallLog extends Model
{
    public $timestamps = false;

    protected $table = 'pric_price_waterfall_logs';

    protected $fillable = [
        'sku',
        'channel',
        'base_hpp_idr',
        'demand_surge_delta_idr',
        'contract_override_delta_idr',
        'guardrail_clamp_delta_idr',
        'final_price_idr',
        'applied_driver',
        'created_at',
    ];

    protected $casts = [
        'base_hpp_idr' => 'integer',
        'demand_surge_delta_idr' => 'integer',
        'contract_override_delta_idr' => 'integer',
        'guardrail_clamp_delta_idr' => 'integer',
        'final_price_idr' => 'integer',
        'created_at' => 'datetime',
    ];
}
