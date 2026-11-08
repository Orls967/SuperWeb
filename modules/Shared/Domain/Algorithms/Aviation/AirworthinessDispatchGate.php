<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

use Carbon\CarbonImmutable;

/**
 * A2: Aviation\AirworthinessDispatchGate
 *
 * Verifikasi kelaikan terbang sebelum dispatch:
 * - Sertifikat kalender belum lewat (>= hari evaluasi).
 * - Margin jam (batas - berjalan) >= durasi terbang.
 * - Margin siklus (batas - berjalan) >= jumlah siklus (default 1).
 * - Tidak ada deferral MEL yang kedaluwarsa (< hari evaluasi).
 * - Alasan penolakan diurutkan secara alfabetis.
 */
final class AirworthinessDispatchGate
{
    public function evaluate(
        CarbonImmutable $evalDate,
        CarbonImmutable $certExpiry,
        int $aCheckDueMinutes,
        int $aCheckCurrentMinutes,
        int $cyclesLimit,
        int $cyclesCurrent,
        int $flightDurationMinutes,
        int $flightCycles = 1,
        ?CarbonImmutable $melDeferralExpiry = null
    ): AirworthinessResult {
        $reasons = [];

        // 1. Margin jam A-Check
        $hoursMargin = $aCheckDueMinutes - $aCheckCurrentMinutes;
        if ($hoursMargin < $flightDurationMinutes) {
            $reasons[] = 'A_CHECK_HOURS';
        }

        // 2. Sertifikat kalender
        if ($certExpiry->startOfDay()->lessThan($evalDate->startOfDay())) {
            $reasons[] = 'CERT_EXPIRED';
        }

        // 3. Margin siklus
        $cyclesMargin = $cyclesLimit - $cyclesCurrent;
        if ($cyclesMargin < $flightCycles) {
            $reasons[] = 'CYCLES_EXCEEDED';
        }

        // 4. Deferral MEL
        if ($melDeferralExpiry !== null && $melDeferralExpiry->startOfDay()->lessThan($evalDate->startOfDay())) {
            $reasons[] = 'MEL_EXPIRED';
        }

        sort($reasons);
        $isPermitted = (count($reasons) === 0);

        $certificate = new AirworthinessCertificate(
            evalDate: $evalDate->toDateString(),
            isPermitted: $isPermitted,
            reasons: $reasons,
            hoursMargin: $hoursMargin,
            cyclesMargin: $cyclesMargin
        );

        $result = new AirworthinessResult(
            isPermitted: $isPermitted,
            reasons: $reasons,
            hoursMargin: $hoursMargin,
            cyclesMargin: $cyclesMargin,
            certificate: $certificate
        );

        AirworthinessCertificateVerifier::verify($result);

        return $result;
    }
}
