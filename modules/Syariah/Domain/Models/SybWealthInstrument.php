<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Katalog Instrumen Pasar Modal Syariah (D162-3).
 */
final class SybWealthInstrument extends Model
{
    protected $table = 'syb_wealth_instruments';

    protected $fillable = [
        'ticker_symbol',
        'name',
        'instrument_type',
        'interest_bearing_debt_idr',
        'total_assets_idr',
        'debt_ratio_bps',
        'non_halal_revenue_idr',
        'total_revenue_idr',
        'non_halal_revenue_ratio_bps',
        'sector_code',
        'is_shariah_compliant',
        'last_screened_at',
    ];

    protected $casts = [
        'interest_bearing_debt_idr' => 'integer',
        'total_assets_idr' => 'integer',
        'debt_ratio_bps' => 'integer',
        'non_halal_revenue_idr' => 'integer',
        'total_revenue_idr' => 'integer',
        'non_halal_revenue_ratio_bps' => 'integer',
        'is_shariah_compliant' => 'boolean',
        'last_screened_at' => 'datetime',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(SybWealthOrder::class, 'instrument_id');
    }
}
