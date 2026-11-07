<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

use InvalidArgumentException;

/**
 * H5 Hotel\ServiceRecoveryCompensator [Fase 91]
 *
 * Kelebihan durasi perbaikan fasilitas/gangguan di atas SLA:
 *   <= 2 jam -> 10% tarif malam
 *   2-6 jam  -> 25% tarif malam
 *   > 6 jam  -> 50% tarif malam + sertifikat malam gratis (free night certificate)
 * Maksimal kompensasi uang adalah 1 tarif malam.
 * Voucher kompensasi dicatat ke folio sebagai contra-revenue.
 */
final class ServiceRecoveryCompensator
{
    /**
     * @param int $nightRateIdr Tarif kamar per malam (IDR)
     * @param float $excessHours Durasi keterlambatan pemulihan di atas SLA (jam)
     * @return array{
     *     compensation_amount_idr: int,
     *     compensation_percent_bps: int,
     *     grants_free_night: bool,
     *     contra_revenue_account: string,
     *     certificate: ServiceRecoveryCertificate
     * }
     */
    public static function compensate(
        int $nightRateIdr,
        float $excessHours
    ): array {
        if ($nightRateIdr <= 0) {
            throw new InvalidArgumentException("Tarif malam kamar harus positif.");
        }
        if ($excessHours < 0.0) {
            throw new InvalidArgumentException("Durasi keterlambatan SLA tidak boleh negatif.");
        }

        if ($excessHours <= 2.0) {
            $bps = 1000; // 10%
            $freeNight = false;
        } elseif ($excessHours <= 6.0) {
            $bps = 2500; // 25%
            $freeNight = false;
        } else {
            $bps = 5000; // 50%
            $freeNight = true;
        }

        // Hitung nominal kompensasi
        $amount = intdiv($nightRateIdr * $bps, 10000);

        // Cap maksimal 1 tarif malam
        if ($amount > $nightRateIdr) {
            $amount = $nightRateIdr;
        }

        $certificate = new ServiceRecoveryCertificate(
            nightRateIdr: $nightRateIdr,
            excessHours: $excessHours,
            compensationAmountIdr: $amount,
            compensationPercentBps: $bps,
            grantsFreeNight: $freeNight,
            timestamp: time()
        );

        ServiceRecoveryCertificateVerifier::verify($certificate);

        return [
            'compensation_amount_idr' => $amount,
            'compensation_percent_bps' => $bps,
            'grants_free_night' => $freeNight,
            'contra_revenue_account' => 'htl:service_recovery_contra_revenue:IDR',
            'certificate' => $certificate,
        ];
    }
}
