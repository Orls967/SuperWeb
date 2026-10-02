<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Fee penanganan COD: persentase dari dana COD dengan batas minimum, tidak pernah melebihi dana COD.
 */
class CodFeeCalculator
{
    public function feeFor(int $amountIdr): int
    {
        if ($amountIdr <= 0) {
            return 0;
        }

        $rate = BigDecimal::of((string) config('logistics.cod_fee_rate', 0.03));
        $min = (int) config('logistics.cod_fee_min_idr', 5_000);

        $fee = (int) BigDecimal::of($amountIdr)->multipliedBy($rate)->toScale(0, RoundingMode::HalfUp)->toInt();

        return min($amountIdr, max($min, $fee));
    }
}
