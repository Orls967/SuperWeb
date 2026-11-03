<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SkuParameter extends Model
{
    protected $table = 'pric_sku_parameters';

    protected $fillable = [
        'sku',
        'category',
        'base_price_idr',
        'floor_price_idr',
        'ceiling_price_idr',
        'het_price_idr',
        'price_step_idr',
        'elasticity_bps',
        'is_perishable',
        'stock_cover_days',
        'demand_ratio_bps',
        'shelf_life_ratio_bps',
        'season_factor_bps',
        'occupancy_factor_bps',
        'version',
    ];

    protected $casts = [
        'base_price_idr' => 'integer',
        'floor_price_idr' => 'integer',
        'ceiling_price_idr' => 'integer',
        'het_price_idr' => 'integer',
        'price_step_idr' => 'integer',
        'elasticity_bps' => 'integer',
        'is_perishable' => 'boolean',
        'stock_cover_days' => 'integer',
        'demand_ratio_bps' => 'integer',
        'shelf_life_ratio_bps' => 'integer',
        'season_factor_bps' => 'integer',
        'occupancy_factor_bps' => 'integer',
        'version' => 'integer',
    ];
}
