<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitConversion extends Model
{
    protected $table = 'resto_unit_conversions';

    protected $fillable = [
        'ingredient_id',
        'from_unit',
        'to_base_factor',
        'label',
    ];

    protected $casts = [
        'to_base_factor' => 'string',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    /**
     * Convert an amount from from_unit to base unit factor using BigDecimal.
     */
    public function toBase(BigDecimal|string|int|float $amount): BigDecimal
    {
        $amt = $amount instanceof BigDecimal ? $amount : BigDecimal::of((string) $amount);
        $factor = BigDecimal::of($this->to_base_factor);

        return $amt->multipliedBy($factor);
    }

    /**
     * Convert an amount from base unit to from_unit factor using BigDecimal.
     */
    public function fromBase(BigDecimal|string|int|float $baseAmount, int $scale = 6): BigDecimal
    {
        $amt = $baseAmount instanceof BigDecimal ? $baseAmount : BigDecimal::of((string) $baseAmount);
        $factor = BigDecimal::of($this->to_base_factor);

        return $amt->dividedBy($factor, $scale, RoundingMode::HalfUp);
    }
}
