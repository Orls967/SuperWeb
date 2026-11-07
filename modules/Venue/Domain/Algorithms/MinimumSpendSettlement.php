<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

/**
 * Algoritma V4: MinimumSpendSettlement
 *
 * Menghitung penyelesaian tagihan meja VIP:
 * Tagihan = max(belanja, minimum spend). Deposit di-capture lebih dulu, sisanya dibebankan.
 * Kekurangan minimum spend = pendapatan "minimum spend shortfall".
 * No-show → deposit hangus sebesar % (fixture 50%) dan sisanya dikembalikan.
 */
final class MinimumSpendSettlement
{
    public static function calculate(
        int $minimumSpendIdr,
        int $depositHeldIdr,
        int $actualSpendIdr,
        bool $isNoShow = false,
        int $noShowForfeitureBps = 5000,
    ): MinimumSpendCertificate {
        if ($isNoShow) {
            $forfeited = intdiv($depositHeldIdr * $noShowForfeitureBps, 10000);
            $refunded = $depositHeldIdr - $forfeited;

            $signature = MinimumSpendCertificateVerifier::sign(
                $minimumSpendIdr,
                $depositHeldIdr,
                $actualSpendIdr,
                true,
                0,
                0,
                0,
                0,
                $forfeited,
                $refunded
            );

            $cert = new MinimumSpendCertificate(
                minimumSpendIdr: $minimumSpendIdr,
                depositHeldIdr: $depositHeldIdr,
                actualSpendIdr: $actualSpendIdr,
                isNoShow: true,
                totalBillIdr: 0,
                capturedDepositIdr: 0,
                additionalChargeIdr: 0,
                shortfallIdr: 0,
                forfeitedDepositIdr: $forfeited,
                refundedDepositIdr: $refunded,
                hmacSignature: $signature
            );

            MinimumSpendCertificateVerifier::verify($cert);

            return $cert;
        }

        $totalBill = max($actualSpendIdr, $minimumSpendIdr);
        $shortfall = max(0, $minimumSpendIdr - $actualSpendIdr);
        $captured = min($depositHeldIdr, $totalBill);
        $additional = max(0, $totalBill - $depositHeldIdr);

        $signature = MinimumSpendCertificateVerifier::sign(
            $minimumSpendIdr,
            $depositHeldIdr,
            $actualSpendIdr,
            false,
            $totalBill,
            $captured,
            $additional,
            $shortfall,
            0,
            0
        );

        $cert = new MinimumSpendCertificate(
            minimumSpendIdr: $minimumSpendIdr,
            depositHeldIdr: $depositHeldIdr,
            actualSpendIdr: $actualSpendIdr,
            isNoShow: false,
            totalBillIdr: $totalBill,
            capturedDepositIdr: $captured,
            additionalChargeIdr: $additional,
            shortfallIdr: $shortfall,
            forfeitedDepositIdr: 0,
            refundedDepositIdr: 0,
            hmacSignature: $signature
        );

        MinimumSpendCertificateVerifier::verify($cert);

        return $cert;
    }
}

