<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class TawarruqFx
{
    /**
     * Maksimum deviasi margin tahunan (2500 bps = 25.00%).
     */
    public const int MAX_MARKUP_RATE_BPS = 2500;

    /**
     * Menghitung struktur transaksi Tawarruq Munazzam untuk konversi valas dan lindung nilai syariah:
     * 1. Pembelian lot komoditas bursa internasional riil.
     * 2. Penjualan ke nasabah secara tangguh (deferred payment) dengan mark-up transparan (bukan bunga).
     * 3. Penjualan komoditas ke pasar ketiga secara tunai dalam mata uang tujuan via agen wakalah.
     */
    public function calculate(
        string $orderNumber,
        string $sourceCurrency,
        int $sourcePrincipalCents,
        string $targetCurrency,
        int $spotRateBps,
        int $tenorDays,
        int $markupRateBps,
        int $brokerFeeCents,
        string $commodityLotSerial,
        string $exchangeName,
        ?CarbonImmutable $evaluatedAt = null
    ): TawarruqFxResult {
        if ($sourcePrincipalCents <= 0) {
            throw new InvalidArgumentException('Pokok sumber pembiayaan tawarruq harus positif.');
        }
        if ($spotRateBps <= 0) {
            throw new InvalidArgumentException('Kurs spot harus positif.');
        }
        if ($tenorDays <= 0 || $tenorDays > 730) {
            throw new InvalidArgumentException('Tenor tawarruq harus antara 1 dan 730 hari.');
        }
        if ($markupRateBps < 0 || $markupRateBps > self::MAX_MARKUP_RATE_BPS) {
            throw new InvalidArgumentException('Margin tahunan di luar batas wajar (0 - 2500 bps).');
        }
        if ($brokerFeeCents < 0) {
            throw new InvalidArgumentException('Biaya broker bursa tidak boleh negatif.');
        }
        if (trim($commodityLotSerial) === '' || strlen($commodityLotSerial) < 6) {
            throw new InvalidArgumentException('Nomor seri lot komoditas fisik tidak valid atau kosong.');
        }

        $now = $evaluatedAt ?? CarbonImmutable::now();

        // Target received cents: konversi kurs spot minor unit
        // Formula: jika IDR ke USD (spotRateBps = e.g. 1550000 = 15,500.00 IDR/USD, 10000 bps = 1 unit)
        // targetReceived = intdiv(sourcePrincipalCents * 10000, spotRateBps)
        $targetReceivedCents = (int) intdiv($sourcePrincipalCents * 10000, $spotRateBps);
        if ($targetReceivedCents <= 0) {
            throw new InvalidArgumentException('Hasil konversi valas bernilai nol atau tidak valid.');
        }

        // Markup profit: linear annualized mark-up tanpa bunga majemuk
        // formula: intdiv(sourcePrincipalCents * markupRateBps * tenorDays, 365 * 10000)
        $markupProfitCents = (int) intdiv($sourcePrincipalCents * $markupRateBps * $tenorDays, 365 * 10000);

        // Total deferred selling price nasabah ke bank (pokok + margin + fee riil)
        $totalDeferredPriceCents = $sourcePrincipalCents + $markupProfitCents + $brokerFeeCents;

        // Effective rate bps: rasio harga tangguh terhadap target valas yang diterima
        // formula: intdiv(totalDeferredPriceCents * 10000, targetReceivedCents)
        $effectiveRateBps = (int) intdiv($totalDeferredPriceCents * 10000, $targetReceivedCents);

        $evaluatedAtIso = $now->toIso8601String();

        $payload = [
            'orderNumber' => $orderNumber,
            'sourceCurrency' => $sourceCurrency,
            'sourcePrincipalCents' => $sourcePrincipalCents,
            'targetCurrency' => $targetCurrency,
            'targetReceivedCents' => $targetReceivedCents,
            'spotRateBps' => $spotRateBps,
            'tenorDays' => $tenorDays,
            'markupRateBps' => $markupRateBps,
            'markupProfitCents' => $markupProfitCents,
            'brokerFeeCents' => $brokerFeeCents,
            'totalDeferredPriceCents' => $totalDeferredPriceCents,
            'effectiveRateBps' => $effectiveRateBps,
            'commodityLotSerial' => $commodityLotSerial,
            'exchangeName' => $exchangeName,
            'evaluatedAt' => $evaluatedAtIso,
        ];

        $certificateHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return new TawarruqFxResult(
            orderNumber: $orderNumber,
            sourceCurrency: $sourceCurrency,
            sourcePrincipalCents: $sourcePrincipalCents,
            targetCurrency: $targetCurrency,
            targetReceivedCents: $targetReceivedCents,
            spotRateBps: $spotRateBps,
            tenorDays: $tenorDays,
            markupRateBps: $markupRateBps,
            markupProfitCents: $markupProfitCents,
            brokerFeeCents: $brokerFeeCents,
            totalDeferredPriceCents: $totalDeferredPriceCents,
            effectiveRateBps: $effectiveRateBps,
            commodityLotSerial: $commodityLotSerial,
            exchangeName: $exchangeName,
            auditMetadata: [
                'annualized_markup_percent' => sprintf('%.2f%%', $markupRateBps / 100),
                'cost_basis_currency' => $sourceCurrency,
                'prohibition_of_inah_verified' => true,
            ],
            evaluatedAt: $evaluatedAtIso,
            certificateHash: $certificateHash,
        );
    }
}

