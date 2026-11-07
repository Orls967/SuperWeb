<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningMetalsTradingPosition extends Model
{
    use HasUuids;

    protected $table = 'min_metals_hedge_positions';

    protected $fillable = [
        'id',
        'contract_id',
        'position_code',
        'valuation_date',
        'position_type',
        'open_lots',
        'metric_tons',
        'entry_price_usd_cents_per_ton',
        'current_lme_settlement_cents_per_ton',
        'unrealized_pnl_usd_cents',
        'unrealized_pnl_idr',
        'variation_margin_required_idr',
        'margin_call_triggered',
        'status',
        'metadata',
    ];

    protected $casts = [
        'valuation_date' => 'immutable_date',
        'open_lots' => 'float',
        'metric_tons' => 'float',
        'entry_price_usd_cents_per_ton' => 'integer',
        'current_lme_settlement_cents_per_ton' => 'integer',
        'unrealized_pnl_usd_cents' => 'integer',
        'unrealized_pnl_idr' => 'integer',
        'variation_margin_required_idr' => 'integer',
        'margin_call_triggered' => 'boolean',
        'metadata' => 'array',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(MiningMetalsTradingContract::class, 'contract_id');
    }
}
