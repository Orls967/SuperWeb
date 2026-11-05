<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

/**
 * Verifikasi sertifikat screening syariah (PR2):
 *  1. Rasio utang = debt * 10000 / assets (HalfUp)
 *  2. Rasio non-halal = nonHalal * 10000 / revenue (HalfUp)
 *  3. Kepatuhan konsisten dengan batas 4500 bps, 1000 bps, dan larangan sektor
 */
final class ShariahScreeningCertificateVerifier
{
    public function verify(ShariahScreeningResult $result): true
    {
        $expectedDebtBps = BigDecimal::of($result->interestBearingDebtIdr)
            ->multipliedBy(10000)
            ->dividedBy($result->totalAssetsIdr, 0, RoundingMode::HalfUp)
            ->toInt();

        if ($result->debtRatioBps !== $expectedDebtBps) {
            throw new InvalidCertificateException("Rasio utang ({$result->debtRatioBps}) tidak sama dengan hasil kalkulasi ({$expectedDebtBps})");
        }

        $expectedRevBps = BigDecimal::of($result->nonHalalRevenueIdr)
            ->multipliedBy(10000)
            ->dividedBy($result->totalRevenueIdr, 0, RoundingMode::HalfUp)
            ->toInt();

        if ($result->nonHalalRevenueRatioBps !== $expectedRevBps) {
            throw new InvalidCertificateException("Rasio non-halal ({$result->nonHalalRevenueRatioBps}) tidak sama dengan hasil kalkulasi ({$expectedRevBps})");
        }

        $expectedCompliant = ($expectedDebtBps <= ShariahScreening::MAX_DEBT_RATIO_BPS)
            && ($expectedRevBps <= ShariahScreening::MAX_NON_HALAL_REVENUE_BPS)
            && $result->sectorAllowed;

        if ($result->isCompliant !== $expectedCompliant) {
            throw new InvalidCertificateException('Status kepatuhan syariah tidak selaras dengan batasan rasio dan sektor');
        }

        return true;
    }
}
