<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

use InvalidArgumentException;

/**
 * Algoritma V8: SponsorExposureValuator
 *
 * Menilai eksposur sponsor merek terbobot (weighted impressions) dari footfall zona
 * turnstile dan durasi/rotasi penayangan LED screen, mengonversi nilai eksposur ekuivalen CPM,
 * serta menentukan rasio pencairan termin pembayaran (milestone payout) berbasis KPI kontrak.
 */
final class SponsorExposureValuator
{
    public function valuate(SponsorExposureInput $input): SponsorExposureResult
    {
        if ($input->targetImpressions <= 0) {
            throw new InvalidArgumentException('Target impresi sponsor harus lebih besar dari 0.');
        }

        if ($input->cpmRateIdr < 0 || $input->milestoneBudgetAmountIdr < 0) {
            throw new InvalidArgumentException('Tarif CPM dan anggaran milestone tidak boleh negatif.');
        }

        // 1. Hitung Impresi Terbobot per Zona
        $deliveredImpressions = 0;
        foreach ($input->zoneDeliveries as $deliv) {
            $footfall = (int) ($deliv['footfall'] ?? 0);
            $dwellBps = (int) ($deliv['dwell_weight_bps'] ?? 10_000); // 10000 = 1.0x
            $screenBps = (int) ($deliv['screen_share_bps'] ?? 10_000);

            // weighted = footfall * (dwellBps/10000) * (screenBps/10000)
            $step1 = intdiv($footfall * $dwellBps, 10_000);
            $zoneImpression = intdiv($step1 * $screenBps, 10_000);

            $deliveredImpressions += max(0, $zoneImpression);
        }

        // 2. Nilai Eksposur Ekuivalen CPM IDR
        $exposureValueIdr = (int) (intdiv($deliveredImpressions, 1_000) * $input->cpmRateIdr);

        // 3. Rasio Pencapaian Kontrak
        $achievementBps = (int) intdiv($deliveredImpressions * 10_000, $input->targetImpressions);

        // 4. Penentuan Payout Ratio:
        // >= 100% (10000 bps) -> 100%
        // 70% s/d 99% -> pro-rata sesuai achievementBps
        // < 70% -> 0% (penalti wanprestasi under-delivery)
        $payoutRatioBps = 0;
        if ($achievementBps >= 10_000) {
            $payoutRatioBps = 10_000;
        } elseif ($achievementBps >= 7_000) {
            $payoutRatioBps = $achievementBps;
        }

        $milestonePayoutIdr = (int) intdiv($input->milestoneBudgetAmountIdr * $payoutRatioBps, 10_000);

        // 5. Terbitkan Sertifikat Komputasi
        $signature = SponsorExposureVerifier::sign(
            delivered: $deliveredImpressions,
            target: $input->targetImpressions,
            achievement: $achievementBps,
            payoutRatio: $payoutRatioBps,
            exposureVal: $exposureValueIdr,
            milestonePayout: $milestonePayoutIdr
        );

        $certificate = new SponsorExposureCertificate(
            deliveredImpressions: $deliveredImpressions,
            targetImpressions: $input->targetImpressions,
            achievementBps: $achievementBps,
            payoutRatioBps: $payoutRatioBps,
            exposureValueIdr: $exposureValueIdr,
            milestonePayoutIdr: $milestonePayoutIdr,
            hmacSignature: $signature
        );

        return new SponsorExposureResult(
            deliveredImpressions: $deliveredImpressions,
            achievementBps: $achievementBps,
            payoutRatioBps: $payoutRatioBps,
            exposureValueIdr: $exposureValueIdr,
            milestonePayoutIdr: $milestonePayoutIdr,
            certificate: $certificate
        );
    }
}
