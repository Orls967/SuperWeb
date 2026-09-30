<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateBracket extends LogisticsEntity
{
    protected $table = 'lgx_rate_brackets';

    protected $fillable = [
        'rate_card_id',
        'min_weight_kg',
        'max_weight_kg',
        'rate_per_kg_idr',
        'flat_rate_idr',
        'is_flat',
    ];

    protected $casts = [
        'min_weight_kg' => 'decimal:2',
        'max_weight_kg' => 'decimal:2',
        'rate_per_kg_idr' => 'integer',
        'flat_rate_idr' => 'integer',
        'is_flat' => 'boolean',
    ];

    public function rateCard(): BelongsTo
    {
        return $this->belongsTo(RateCard::class, 'rate_card_id');
    }

    /**
     * Calculate cost for the given chargeable weight.
     */
    public function calculateCost(BigDecimal $chargeableWeightKg): BigDecimal
    {
        if ($this->is_flat) {
            return BigDecimal::of($this->flat_rate_idr);
        }

        return $chargeableWeightKg->multipliedBy(BigDecimal::of($this->rate_per_kg_idr))->toScale(0, RoundingMode::HalfUp);
    }
}
