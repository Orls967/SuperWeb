<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Algorithms;

use InvalidArgumentException;

/**
 * M6 CoalPriceAdjuster
 *
 * Menghitung harga jual batubara terkoreksi berdasarkan kualitas aktual
 * (GAR pro-rata, penalti abu, penalti sulfur) serta ambang batas penolakan kargo.
 */
final class CoalPriceAdjuster
{
    public function __construct(
        private readonly CoalPriceAdjusterCertificateVerifier $verifier = new CoalPriceAdjusterCertificateVerifier
    ) {}

    /**
     * Menghitung harga terkoreksi per ton dan total invoice.
     *
     * @param  int  $basePriceCents  Harga kontrak dasar (sen USD per ton)
     * @param  int  $actualGarKcal  Nilai kalori aktual GAR (Gross as Received, kcal/kg)
     * @param  int  $referenceGarKcal  Nilai kalori acuan kontrak (kcal/kg)
     * @param  int  $actualAshPermil  Kadar abu aktual dalam permil (0,1%, e.g. 9,3% = 93 permil)
     * @param  int  $baseAshLimitPermil  Batas toleransi abu tanpa penalti (e.g. 8,0% = 80 permil)
     * @param  int  $actualSulfurPermil  Kadar total sulfur aktual dalam permil (0,1%, e.g. 0,9% = 9 permil)
     * @param  int  $baseSulfurLimitPermil  Batas toleransi sulfur tanpa penalti (e.g. 0,8% = 8 permil)
     * @param  int  $volumeTons  Volume pengapalan dalam metrik ton
     * @param  int  $rejectAshPermil  Batas maksimal abu sebelum kargo ditolak (e.g. 15,0% = 150 permil)
     * @param  int  $rejectSulfurPermil  Batas maksimal sulfur sebelum kargo ditolak (e.g. 2,0% = 20 permil)
     * @param  int  $ashPenaltyRateCents  Tarif penalti abu (sen USD per 0,1% kelebihan)
     * @param  int  $sulfurPenaltyRateCents  Tarif penalti sulfur (sen USD per 0,1% kelebihan)
     */
    public function adjust(
        int $basePriceCents,
        int $actualGarKcal,
        int $referenceGarKcal,
        int $actualAshPermil,
        int $baseAshLimitPermil,
        int $actualSulfurPermil,
        int $baseSulfurLimitPermil,
        int $volumeTons,
        int $rejectAshPermil = 150,
        int $rejectSulfurPermil = 20,
        int $ashPenaltyRateCents = 5,
        int $sulfurPenaltyRateCents = 100
    ): CoalPriceAdjusterResult {
        if ($basePriceCents <= 0 || $actualGarKcal <= 0 || $referenceGarKcal <= 0 || $volumeTons <= 0) {
            throw new InvalidArgumentException('Parameter harga dasar, kalori, dan volume harus bernilai positif.');
        }

        $isRejected = false;
        $rejectionReason = null;

        if ($actualAshPermil > $rejectAshPermil) {
            $isRejected = true;
            $rejectionReason = sprintf('Kadar abu %0.1f%% melebihi batas penolakan %0.1f%%', $actualAshPermil / 10, $rejectAshPermil / 10);
        } elseif ($actualSulfurPermil > $rejectSulfurPermil) {
            $isRejected = true;
            $rejectionReason = sprintf('Kadar sulfur %0.1f%% melebihi batas penolakan %0.1f%%', $actualSulfurPermil / 10, $rejectSulfurPermil / 10);
        }

        if ($isRejected) {
            $result = new CoalPriceAdjusterResult(
                basePriceCents: $basePriceCents,
                actualGarKcal: $actualGarKcal,
                referenceGarKcal: $referenceGarKcal,
                actualAshPermil: $actualAshPermil,
                baseAshLimitPermil: $baseAshLimitPermil,
                actualSulfurPermil: $actualSulfurPermil,
                baseSulfurLimitPermil: $baseSulfurLimitPermil,
                volumeTons: $volumeTons,
                garAdjustedPriceCents: 0,
                ashPenaltyCents: 0,
                sulfurPenaltyCents: 0,
                finalPriceCentsPerTon: 0,
                totalInvoiceUsdCents: 0,
                isRejected: true,
                rejectionReason: $rejectionReason,
                certificate: [
                    'canonical_hash' => hash('sha256', sprintf(
                        '%d|%d|%d|%d|%d|%d|%d|%d|0|0|0|0|0|1|%s',
                        $basePriceCents,
                        $actualGarKcal,
                        $referenceGarKcal,
                        $actualAshPermil,
                        $baseAshLimitPermil,
                        $actualSulfurPermil,
                        $baseSulfurLimitPermil,
                        $volumeTons,
                        $rejectionReason
                    )),
                    'pricing_rule_verified' => true,
                    'verified_at' => now()->toIso8601String(),
                ]
            );

            $this->verifier->verify($result);

            return $result;
        }

        // 1. Pro-rata GAR (bulat ke bawah ke sen terdekat)
        $garAdjustedPrice = intdiv($basePriceCents * $actualGarKcal, $referenceGarKcal);

        // 2. Penalti abu (per 0,1% kelebihan)
        $excessAsh = max(0, $actualAshPermil - $baseAshLimitPermil);
        $ashPenalty = $excessAsh * $ashPenaltyRateCents;

        // 3. Penalti sulfur (per 0,1% kelebihan)
        $excessSulfur = max(0, $actualSulfurPermil - $baseSulfurLimitPermil);
        $sulfurPenalty = $excessSulfur * $sulfurPenaltyRateCents;

        $finalPricePerTon = max(0, $garAdjustedPrice - $ashPenalty - $sulfurPenalty);
        $totalInvoiceUsd = (int) ($finalPricePerTon * $volumeTons);

        $payload = sprintf(
            '%d|%d|%d|%d|%d|%d|%d|%d|%d|%d|%d|%d|%d|0|',
            $basePriceCents,
            $actualGarKcal,
            $referenceGarKcal,
            $actualAshPermil,
            $baseAshLimitPermil,
            $actualSulfurPermil,
            $baseSulfurLimitPermil,
            $volumeTons,
            $garAdjustedPrice,
            $ashPenalty,
            $sulfurPenalty,
            $finalPricePerTon,
            $totalInvoiceUsd
        );

        $certificate = [
            'canonical_hash' => hash('sha256', $payload),
            'pricing_rule_verified' => true,
            'verified_at' => now()->toIso8601String(),
        ];

        $result = new CoalPriceAdjusterResult(
            basePriceCents: $basePriceCents,
            actualGarKcal: $actualGarKcal,
            referenceGarKcal: $referenceGarKcal,
            actualAshPermil: $actualAshPermil,
            baseAshLimitPermil: $baseAshLimitPermil,
            actualSulfurPermil: $actualSulfurPermil,
            baseSulfurLimitPermil: $baseSulfurLimitPermil,
            volumeTons: $volumeTons,
            garAdjustedPriceCents: $garAdjustedPrice,
            ashPenaltyCents: $ashPenalty,
            sulfurPenaltyCents: $sulfurPenalty,
            finalPriceCentsPerTon: $finalPricePerTon,
            totalInvoiceUsdCents: $totalInvoiceUsd,
            isRejected: false,
            rejectionReason: null,
            certificate: $certificate
        );

        $this->verifier->verify($result);

        return $result;
    }
}
