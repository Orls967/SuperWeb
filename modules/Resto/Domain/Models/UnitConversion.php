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
     * Convert quantity from from_unit to base unit factor using BigDecimal.
     */
    public function toBase(BigDecimal|string|int $quantity): BigDecimal
    {
        $amt = $quantity instanceof BigDecimal ? $quantity : BigDecimal::of((string) $quantity);
        $factor = BigDecimal::of($this->to_base_factor);

        return $amt->multipliedBy($factor);
    }

    /**
     * Convert quantity from base unit to from_unit factor using BigDecimal.
     */
    public function fromBase(BigDecimal|string|int $baseQuantity, int $scale = 6): BigDecimal
    {
        $amt = $baseQuantity instanceof BigDecimal ? $baseQuantity : BigDecimal::of((string) $baseQuantity);
        $factor = BigDecimal::of($this->to_base_factor);

        return $amt->dividedBy($factor, $scale, RoundingMode::HalfUp);
    }
}
