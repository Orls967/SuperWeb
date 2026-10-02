<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Logistics\Domain\Models\HsTariff;

/**
 * SIMULASI perhitungan pungutan impor (bukan nasihat kepabeanan):
 *   BM      = nilai pabean x tarif BM
 *   PPN     = (nilai pabean + BM) x tarif PPN
 *   PPh 22  = (nilai pabean + BM) x tarif PPh 22 (API 2,5% / non-API 7,5% secara default)
 * Pembulatan HALF_UP ke rupiah per baris barang.
 */
class CustomsDutyCalculator
{
    /**
     * @return array{bm: int, ppn: int, pph22: int, total: int}
     */
    public function forLine(int $valueIdr, HsTariff $tariff, bool $hasApi): array
    {
        $value = BigDecimal::of($valueIdr);
        $bm = $this->round($value->multipliedBy($tariff->bm_bp)->dividedBy(10_000, 6, RoundingMode::HalfUp));
        $base = $value->plus($bm);
        $ppn = $this->round($base->multipliedBy($tariff->ppn_bp)->dividedBy(10_000, 6, RoundingMode::HalfUp));
        $pphBp = $hasApi ? $tariff->pph22_api_bp : $tariff->pph22_non_api_bp;
        $pph = $this->round($base->multipliedBy($pphBp)->dividedBy(10_000, 6, RoundingMode::HalfUp));

        return ['bm' => $bm, 'ppn' => $ppn, 'pph22' => $pph, 'total' => $bm + $ppn + $pph];
    }

    private function round(BigDecimal $amount): int
    {
        return (int) $amount->toScale(0, RoundingMode::HalfUp)->toInt();
    }
}
