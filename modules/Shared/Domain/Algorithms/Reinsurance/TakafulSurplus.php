<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Reinsurance;

use InvalidArgumentException;

/**
 * Algoritma Bersertifikat A8: Takaful Surplus Underwriting Allocation.
 *
 * Menghitung surplus underwriting dana kebajikan (Tabarru') dan membagikannya secara
 * matematis presisi tanpa sisa pembulatan ke:
 * 1. Peserta berhak (tabarru' participant refund / hibah)
 * 2. Cadangan kontinjensi dana tabarru' (contingency reserve)
 * 3. Operator takaful (nisbah bagi hasil Mudharabah pengelola)
 */
final class TakafulSurplus
{
    private const string HMAC_SECRET = 'antigravity:takaful:surplus:cert:2026';

    /**
     * @param  int  $netContributionIdr  Total kontribusi iuran bersih tabarru' dalam minor unit (sen)
     * @param  int  $netIncurredClaimsIdr  Total klaim terbayar + cadangan klaim dalam sen
     * @param  int  $technicalReserveIdr  Alokasi cadangan teknis wajib aktuaria dalam sen
     * @param  int  $participantShareBps  Proporsi pembagian ke peserta (basis points, misal 5000 = 50%)
     * @param  int  $contingencyReserveBps  Proporsi retensi cadangan tabarru' (basis points, misal 3000 = 30%)
     * @param  int  $operatorMudharabahBps  Proporsi bagi hasil pengelola (basis points, misal 2000 = 20%)
     */
    public function calculate(
        int $netContributionIdr,
        int $netIncurredClaimsIdr,
        int $technicalReserveIdr,
        int $participantShareBps = 5000,
        int $contingencyReserveBps = 3000,
        int $operatorMudharabahBps = 2000,
    ): TakafulSurplusResult {
        if ($netContributionIdr < 0 || $netIncurredClaimsIdr < 0 || $technicalReserveIdr < 0) {
            throw new InvalidArgumentException('Nilai finansial kontribusi, klaim dan cadangan teknis tidak boleh negatif.');
        }

        if (($participantShareBps + $contingencyReserveBps + $operatorMudharabahBps) !== 10000) {
            throw new InvalidArgumentException('Total proporsi nisbah surplus harus tepat 10.000 bps (100%).');
        }

        $grossSurplus = $netContributionIdr - $netIncurredClaimsIdr - $technicalReserveIdr;

        if ($grossSurplus <= 0) {
            $certificate = $this->generateCertificate(
                $netContributionIdr,
                $netIncurredClaimsIdr,
                $technicalReserveIdr,
                0,
                0,
                0,
                0
            );

            return new TakafulSurplusResult(
                netContributionIdr: $netContributionIdr,
                netIncurredClaimsIdr: $netIncurredClaimsIdr,
                technicalReserveIdr: $technicalReserveIdr,
                grossSurplusIdr: 0,
                participantSurplusShareIdr: 0,
                contingencyReserveShareIdr: 0,
                mudharabahOperatorShareIdr: 0,
                allocations: [
                    'participants' => 0,
                    'contingency' => 0,
                    'operator' => 0,
                ],
                certificate: $certificate,
            );
        }

        $participantShare = (int) intdiv($grossSurplus * $participantShareBps, 10000);
        $contingencyShare = (int) intdiv($grossSurplus * $contingencyReserveBps, 10000);
        $operatorShare = $grossSurplus - $participantShare - $contingencyShare;

        $certificate = $this->generateCertificate(
            $netContributionIdr,
            $netIncurredClaimsIdr,
            $technicalReserveIdr,
            $grossSurplus,
            $participantShare,
            $contingencyShare,
            $operatorShare
        );

        return new TakafulSurplusResult(
            netContributionIdr: $netContributionIdr,
            netIncurredClaimsIdr: $netIncurredClaimsIdr,
            technicalReserveIdr: $technicalReserveIdr,
            grossSurplusIdr: $grossSurplus,
            participantSurplusShareIdr: $participantShare,
            contingencyReserveShareIdr: $contingencyShare,
            mudharabahOperatorShareIdr: $operatorShare,
            allocations: [
                'participants' => $participantShare,
                'contingency' => $contingencyShare,
                'operator' => $operatorShare,
            ],
            certificate: $certificate,
        );
    }

    private function generateCertificate(
        int $netContribution,
        int $netClaims,
        int $reserve,
        int $surplus,
        int $partShare,
        int $contShare,
        int $opShare,
    ): string {
        $canonical = sprintf(
            'TAKAFUL-SURPLUS:contrib=%d:claims=%d:res=%d:surp=%d:part=%d:cont=%d:op=%d',
            $netContribution,
            $netClaims,
            $reserve,
            $surplus,
            $partShare,
            $contShare,
            $opShare
        );

        return hash_hmac('sha256', $canonical, self::HMAC_SECRET);
    }
}
