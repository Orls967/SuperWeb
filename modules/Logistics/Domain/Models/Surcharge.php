<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class Surcharge extends LogisticsEntity
{
    protected $table = 'lgx_surcharges';

    public const CODE_FUEL = 'FUEL';

    public const CODE_BAF = 'BAF';

    public const CODE_PEAK = 'PEAK';

    public const CODE_DG = 'DG';

    public const CODE_REEFER = 'REEFER';

    public const CODE_REMOTE_AREA = 'REMOTE_AREA';

    public const CODE_INSURANCE = 'INSURANCE';

    public const CODE_COD_FEE = 'COD_FEE';

    public const CODE_THC = 'THC';

    public const CODE_DOC_FEE = 'DOC_FEE';

    protected $fillable = [
        'code',
        'name',
        'type', // 'percentage' | 'flat'
        'rate',
        'flat_amount_idr',
        'min_amount_idr',
        'applies_to_service_level',
        'is_active',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'flat_amount_idr' => 'integer',
        'min_amount_idr' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Calculate surcharge amount based on base freight and context (declared value, cod amount, etc.)
     *
     * @param  array{declared_value_idr?: int, cod_amount_idr?: int, container_count?: int}  $context
     */
    public function calculate(BigDecimal $baseFreightIdr, array $context = []): BigDecimal
    {
        if (! $this->is_active) {
            return BigDecimal::zero();
        }

        if ($this->type === 'flat') {
            $multiplier = 1;
            if ($this->code === self::CODE_THC && isset($context['container_count'])) {
                $multiplier = max(1, (int) $context['container_count']);
            }

            return BigDecimal::of($this->flat_amount_idr)->multipliedBy(BigDecimal::of($multiplier));
        }

        // Percentage-based surcharges
        $rateDecimal = BigDecimal::of($this->rate);

        $base = match ($this->code) {
            self::CODE_INSURANCE => BigDecimal::of($context['declared_value_idr'] ?? 0),
            self::CODE_COD_FEE => BigDecimal::of($context['cod_amount_idr'] ?? 0),
            default => $baseFreightIdr,
        };

        $amount = $base->multipliedBy($rateDecimal)->toScale(0, RoundingMode::HalfUp);

        if ($this->min_amount_idr > 0 && $amount->compareTo(BigDecimal::of($this->min_amount_idr)) < 0) {
            $amount = BigDecimal::of($this->min_amount_idr);
        }

        return $amount;
    }
}
