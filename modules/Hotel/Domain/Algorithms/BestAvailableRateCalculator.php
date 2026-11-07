<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

use InvalidArgumentException;

/**
 * H2 Hotel\BestAvailableRateCalculator [Fase 91]
 *
 * BAR = dasar * faktor okupansi * faktor lead time * faktor event
 * Faktor Okupansi:
 *   < 50%  -> 9.000 bps
 *   50-79% -> 10.000 bps
 *   80-89% -> 11.500 bps
 *   >= 90% -> 13.000 bps
 * Faktor Lead Time:
 *   <= 3 hari -> 10.500 bps (+5%)
 *   4-30 hari -> 10.000 bps (0%)
 *   > 30 hari -> 9.500 bps (-5%)
 * Faktor Event:
 *   Event kota aktif -> 11.500 bps (+15%)
 *   Normal           -> 10.000 bps (0%)
 * Pembulatan bertahap ke bawah, lalu ke kelipatan Rp 1.000.
 * Dijepit pada rentang [floor, ceiling].
 * Contract/corporate rate bersifat immutable dan menang mutlak.
 */
final class BestAvailableRateCalculator
{
    /**
     * @param  int  $baseRateIdr  Tarif dasar kamar (IDR)
     * @param  float  $occupancyRate  Rasio okupansi (0.0 sampai 1.0)
     * @param  int  $leadDays  Jumlah hari menjelang check-in
     * @param  bool  $isEventActive  Apakah ada festival/event besar kota
     * @param  int|null  $contractRateIdr  Tarif khusus kontrak/korporat (jika ada)
     * @param  int  $floorRateIdr  Batas bawah (floor) tarif
     * @param  int  $ceilingRateIdr  Batas atas (ceiling) tarif
     * @return array{
     *     rate_idr: int,
     *     applied_occupancy_bps: int,
     *     applied_lead_bps: int,
     *     applied_event_bps: int,
     *     is_contract_override: bool,
     *     certificate: BarRateCertificate
     * }
     */
    public static function calculate(
        int $baseRateIdr,
        float $occupancyRate,
        int $leadDays,
        bool $isEventActive = false,
        ?int $contractRateIdr = null,
        int $floorRateIdr = 450_000,
        int $ceilingRateIdr = 3_000_000
    ): array {
        if ($baseRateIdr <= 0) {
            throw new InvalidArgumentException('Tarif dasar harus positif.');
        }
        if ($floorRateIdr > $ceilingRateIdr) {
            throw new InvalidArgumentException('Floor rate tidak boleh melampaui ceiling rate.');
        }

        if ($contractRateIdr !== null) {
            $certificate = new BarRateCertificate(
                baseRateIdr: $baseRateIdr,
                finalRateIdr: $contractRateIdr,
                floorRateIdr: $floorRateIdr,
                ceilingRateIdr: $ceilingRateIdr,
                isContractOverride: true,
                occupancyBps: 10000,
                leadBps: 10000,
                eventBps: 10000,
                timestamp: time()
            );
            BarRateCertificateVerifier::verify($certificate);

            return [
                'rate_idr' => $contractRateIdr,
                'applied_occupancy_bps' => 10000,
                'applied_lead_bps' => 10000,
                'applied_event_bps' => 10000,
                'is_contract_override' => true,
                'certificate' => $certificate,
            ];
        }

        // 1. Faktor Okupansi
        if ($occupancyRate < 0.50) {
            $occBps = 9000;
        } elseif ($occupancyRate < 0.80) {
            $occBps = 10000;
        } elseif ($occupancyRate < 0.90) {
            $occBps = 11500;
        } else {
            $occBps = 13000;
        }

        // 2. Faktor Lead Time
        if ($leadDays <= 3) {
            $leadBps = 10500;
        } elseif ($leadDays <= 30) {
            $leadBps = 10000;
        } else {
            $leadBps = 9500;
        }

        // 3. Faktor Event
        $eventBps = $isEventActive ? 11500 : 10000;

        // Perkalian bertahap pembulatan ke bawah (integer division)
        $v1 = intdiv($baseRateIdr * $occBps, 10000);
        $v2 = intdiv($v1 * $leadBps, 10000);
        $v3 = intdiv($v2 * $eventBps, 10000);

        // Pembulatan ke kelipatan Rp 1.000 ke bawah
        $rounded = intdiv($v3, 1000) * 1000;

        // Clamping floor & ceiling
        if ($rounded < $floorRateIdr) {
            $finalRate = $floorRateIdr;
        } elseif ($rounded > $ceilingRateIdr) {
            $finalRate = $ceilingRateIdr;
        } else {
            $finalRate = $rounded;
        }

        $certificate = new BarRateCertificate(
            baseRateIdr: $baseRateIdr,
            finalRateIdr: $finalRate,
            floorRateIdr: $floorRateIdr,
            ceilingRateIdr: $ceilingRateIdr,
            isContractOverride: false,
            occupancyBps: $occBps,
            leadBps: $leadBps,
            eventBps: $eventBps,
            timestamp: time()
        );

        BarRateCertificateVerifier::verify($certificate);

        return [
            'rate_idr' => $finalRate,
            'applied_occupancy_bps' => $occBps,
            'applied_lead_bps' => $leadBps,
            'applied_event_bps' => $eventBps,
            'is_contract_override' => false,
            'certificate' => $certificate,
        ];
    }
}
