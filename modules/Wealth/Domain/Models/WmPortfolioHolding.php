<?php

declare(strict_types=1);

namespace Modules\Wealth\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WmPortfolioHolding extends Model
{
    protected $table = 'wm_portfolio_holdings';

    protected $fillable = [
        'profile_id',
        'asset_code',
        'asset_type',
        'asset_identifier',
        'units',
        'units_scale4',
        'average_cost_nav',
        'current_nav',
        'nav_per_unit_scale4',
        'current_market_value_idr',
        'market_value_idr',
        'target_weight_bps',
        'current_weight_bps',
        'last_mtm_at',
    ];

    protected $casts = [
        'profile_id' => 'integer',
        'units' => 'integer',
        'units_scale4' => 'integer',
        'average_cost_nav' => 'integer',
        'current_nav' => 'integer',
        'nav_per_unit_scale4' => 'integer',
        'current_market_value_idr' => 'integer',
        'market_value_idr' => 'integer',
        'target_weight_bps' => 'integer',
        'current_weight_bps' => 'integer',
        'last_mtm_at' => 'datetime',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(WmProfile::class, 'profile_id');
    }
}
