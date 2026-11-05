<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

use RuntimeException;

final class RestructuringScheduleCertificateVerifier
{
    /**
     * Verifikasi keabsahan sertifikat anti-riba restrukturisasi syariah.
     */
    public static function verify(RestructuringScheduleResult $result): bool
    {
        // 1. Guard Anti-Riba: Margin baru DILARANG melebihi margin lama
        if ($result->revisedMarginMinor > $result->originalMarginMinor) {
            throw new RuntimeException('Riba violation: Revised margin exceeds original margin.');
        }

        // 2. Guard Konsistensi Amortisasi
        if ($result->totalRestructuredDebtMinor !== ($result->revisedPrincipalMinor + $result->revisedMarginMinor)) {
            throw new RuntimeException('Integrity violation: Total restructured debt does not equal principal plus margin.');
        }

        // 3. Verifikasi Hash Kriptografis
        $expectedPayload = json_encode([
            'financing_ref' => $result->financingRef,
            'original_principal' => $result->originalPrincipalMinor,
            'original_margin' => $result->originalMarginMinor,
            'haircut_bps' => $result->marginHaircutBps,
            'revised_principal' => $result->revisedPrincipalMinor,
            'revised_margin' => $result->revisedMarginMinor,
            'new_tenor' => $result->newTenorMonths,
            'grace_period' => $result->gracePeriodMonths,
            'schedule_count' => count($result->scheduleTable),
        ], JSON_THROW_ON_ERROR);

        $expectedHash = hash('sha256', $expectedPayload);

        if (! hash_equals($expectedHash, $result->certificateHash)) {
            throw new RuntimeException('Certificate signature mismatch: Restructuring schedule has been tampered with.');
        }

        return true;
    }
}
