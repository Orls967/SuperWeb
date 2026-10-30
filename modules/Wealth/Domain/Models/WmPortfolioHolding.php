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
        'asset_type',
        'asset_identifier',
        'units_scale4',
        'nav_per_unit_scale4',
        'market_value_idr',
        'target_weight_bps',
        'current_weight_bps',
    ];

    protected $casts = [
        'profile_id' => 'integer',
        'units_scale4' => 'integer',
        'nav_per_unit_scale4' => 'integer',
        'market_value_idr' => 'integer',
        'target_weight_bps' => 'integer',
        'current_weight_bps' => 'integer',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(WmProfile::class, 'profile_id');
    }
}
