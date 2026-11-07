<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Mining\Domain\Enums\TradingContractStatus;

class MiningMetalsTradingContract extends Model
{
    use HasUuids;

    protected $table = 'min_metals_trading_contracts';

    protected $fillable = [
        'id',
        'contract_number',
        'counterparty_name',
        'metal_symbol',
        'contract_type',
        'contract_quantity_metric_tons',
        'fixed_price_usd_cents_per_ton',
        'pricing_basis',
        'premium_discount_usd_cents_ton',
        'initial_margin_idr',
        'maintenance_margin_idr',
        'delivery_period_start',
        'delivery_period_end',
        'trader_name',
        'status',
        'hmac_signature',
        'metadata',
    ];

    protected $casts = [
        'contract_quantity_metric_tons' => 'float',
        'fixed_price_usd_cents_per_ton' => 'integer',
        'premium_discount_usd_cents_ton' => 'integer',
        'initial_margin_idr' => 'integer',
        'maintenance_margin_idr' => 'integer',
        'delivery_period_start' => 'immutable_date',
        'delivery_period_end' => 'immutable_date',
        'metadata' => 'array',
    ];

    public function positions(): HasMany
    {
        return $this->hasMany(MiningMetalsTradingPosition::class, 'contract_id');
    }

    public function statusEnum(): TradingContractStatus
    {
        return TradingContractStatus::tryFrom((string) $this->status) ?? TradingContractStatus::DRAFT;
    }
}
