<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Metallurgy;

use InvalidArgumentException;

final class MassBalanceReconcilerCertificateVerifier
{
    /**
     * Memvalidasi hukum kekekalan massa (Feed = Konsentrat + Tailing) dan integritas sertifikat rekonsiliasi.
     *
     * @throws InvalidArgumentException jika kekekalan massa dilanggar atau sertifikat rusak
     */
    public function verify(MassBalanceReconcilerResult $result): bool
    {
        $cert = $result->certificate;

        if (! isset($cert['canonical_hash'], $cert['conservation_law_verified'], $cert['verified_at'])) {
            throw new InvalidArgumentException('Sertifikat rekonsiliasi neraca massa tidak lengkap.');
        }

        // 1. Hukum kekekalan massa wajib mutlak
        if ($result->reconciledFeedKg !== ($result->reconciledConcentrateKg + $result->reconciledTailingKg)) {
            throw new InvalidArgumentException(
                "Pelanggaran kekekalan massa: Feed ({$result->reconciledFeedKg}) != Konsentrat ({$result->reconciledConcentrateKg}) + Tailing ({$result->reconciledTailingKg})"
            );
        }

        // 2. Recovery BPS
        $expectedRecovery = intdiv($result->reconciledConcentrateKg * 10000, $result->reconciledFeedKg);
        if ($expectedRecovery !== $result->recoveryBps) {
            throw new InvalidArgumentException(
                "Perhitungan recovery BPS tidak valid: {$result->recoveryBps} != {$expectedRecovery}"
            );
        }

        // 3. Hash kanonikal
        $payload = sprintf(
            '%d|%d|%d|%d|%d|%d|%d|%d|%s|%s',
            $result->rawFeedKg,
            $result->rawConcentrateKg,
            $result->rawTailingKg,
            $result->reconciledFeedKg,
            $result->reconciledConcentrateKg,
            $result->reconciledTailingKg,
            $result->discrepancyKg,
            $result->recoveryBps,
            $result->requiresInvestigation ? '1' : '0',
            $result->requiresApproval ? '1' : '0'
        );

        $expectedHash = hash('sha256', $payload);
        if (! hash_equals($expectedHash, (string) $cert['canonical_hash'])) {
            throw new InvalidArgumentException('Integritas sertifikat neraca massa rusak: hash tidak cocok.');
        }

        return true;
    }
}
