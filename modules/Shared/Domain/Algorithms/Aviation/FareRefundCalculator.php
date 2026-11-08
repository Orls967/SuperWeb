<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

/**
 * A4: Aviation\FareRefundCalculator
 *
 * Perhitungan pengembalian dana tiket penumpang:
 * - Persen refund dasar menurut kelas x pita waktu (bps):
 *   FLEX: [10000, 9000, 7000, 5000]
 *   STD:  [8000, 6000, 3000, 0]
 *   PROMO:[5000, 0, 0, 0]
 *   Pita waktu: >= 72 jam, 24-72 jam, < 24 jam, no-show.
 * - Biaya admin 100.000 dikurangkan dari bagian dasar (tidak di bawah 0).
 * - Pajak dikembalikan penuh (100%).
 * - Dibatalkan maskapai -> dasar 100% + pajak 100% tanpa biaya admin.
 */
final class FareRefundCalculator
{
    private const ADMIN_FEE_MINOR = 100000;

    /**
     * @param  string  $fareClass  FLEX, STD, PROMO
     * @param  int|null  $hoursBeforeFlight  null jika no-show
     */
    public function calculate(
        string $fareClass,
        int $baseFareMinor,
        int $taxMinor,
        ?int $hoursBeforeFlight,
        bool $isCancelledByAirline = false
    ): FareRefundResult {
        if ($isCancelledByAirline) {
            $baseRefund = $baseFareMinor;
            $adminFee = 0;
            $taxRefund = $taxMinor;
            $totalRefund = $baseRefund + $taxRefund;

            $cert = new FareRefundCertificate(
                fareClass: $fareClass,
                baseFareMinor: $baseFareMinor,
                taxMinor: $taxMinor,
                hoursBeforeFlight: $hoursBeforeFlight,
                isCancelledByAirline: true,
                refundPercentageBps: 10000,
                baseRefundMinor: $baseRefund,
                adminFeeMinor: $adminFee,
                taxRefundMinor: $taxRefund,
                totalRefundMinor: $totalRefund
            );

            $res = new FareRefundResult(
                baseRefundMinor: $baseRefund,
                adminFeeMinor: $adminFee,
                taxRefundMinor: $taxRefund,
                totalRefundMinor: $totalRefund,
                refundPercentageBps: 10000,
                certificate: $cert
            );

            FareRefundCertificateVerifier::verify($res);

            return $res;
        }

        // Tentukan pita waktu
        $bpsTable = [
            'FLEX' => [10000, 9000, 7000, 5000],
            'STD' => [8000, 6000, 3000, 0],
            'PROMO' => [5000, 0, 0, 0],
        ];

        $classUpper = strtoupper($fareClass);
        if (! isset($bpsTable[$classUpper])) {
            $classUpper = 'STD';
        }

        $rates = $bpsTable[$classUpper];

        if ($hoursBeforeFlight === null) {
            // No-show
            $bps = $rates[3];
        } elseif ($hoursBeforeFlight >= 72) {
            $bps = $rates[0];
        } elseif ($hoursBeforeFlight >= 24) {
            $bps = $rates[1];
        } else {
            $bps = $rates[2];
        }

        $grossBaseRefund = (int) floor(($baseFareMinor * $bps) / 10000);
        $adminFee = ($grossBaseRefund > 0) ? min(self::ADMIN_FEE_MINOR, $grossBaseRefund) : 0;
        $netBaseRefund = max(0, $grossBaseRefund - $adminFee);
        $taxRefund = $taxMinor;
        $totalRefund = $netBaseRefund + $taxRefund;

        $cert = new FareRefundCertificate(
            fareClass: $classUpper,
            baseFareMinor: $baseFareMinor,
            taxMinor: $taxMinor,
            hoursBeforeFlight: $hoursBeforeFlight,
            isCancelledByAirline: false,
            refundPercentageBps: $bps,
            baseRefundMinor: $netBaseRefund,
            adminFeeMinor: $adminFee,
            taxRefundMinor: $taxRefund,
            totalRefundMinor: $totalRefund
        );

        $res = new FareRefundResult(
            baseRefundMinor: $netBaseRefund,
            adminFeeMinor: $adminFee,
            taxRefundMinor: $taxRefund,
            totalRefundMinor: $totalRefund,
            refundPercentageBps: $bps,
            certificate: $cert
        );

        FareRefundCertificateVerifier::verify($res);

        return $res;
    }
}
