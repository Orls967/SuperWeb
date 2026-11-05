<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * B4 Islamic\ShariahScreening
 *
 * Screening kepatuhan syariah instrumen keuangan (efek ekuitas/sukuk) standar AAOIFI & OJK (DAFT screening):
 *  1. Rasio utang berbasis bunga / total aset <= 4500 bps (45.00%)
 *  2. Rasio pendapatan non-halal / total pendapatan <= 1000 bps (10.00%)
 *  3. Sektor usaha halal (tidak termasuk alkohol, perjudian, senjata, ribawi konvensional, pornografi, babi)
 *  4. Purifikasi dividen otomatis: dividen x rasio non-halal (HalfUp) dialokasikan ke dana kebajikan.
 *
 * Kompleksitas: O(1).
 */
final class ShariahScreening
{
    public const MAX_DEBT_RATIO_BPS = 4500; // 45.00%
    public const MAX_NON_HALAL_REVENUE_BPS = 1000; // 10.00%

    public const PROHIBITED_SECTORS = [
        'CONVENTIONAL_BANKING',
        'ALCOHOL',
        'GAMBLING',
        'PORNOGRAPHY',
        'PORK',
        'TOBACCO',
        'WEAPONS',
    ];

    /**
     * @param  int  $interestBearingDebtIdr  Total utang berbasis bunga (IDR)
     * @param  int  $totalAssetsIdr  Total aset perusahaan (IDR)
     * @param  int  $nonHalalRevenueIdr  Pendapatan non-halal (IDR)
     * @param  int  $totalRevenueIdr  Total pendapatan perusahaan (IDR)
     * @param  string  $sectorCode  Kode sektor usaha
     */
    public function screen(
        int $interestBearingDebtIdr,
        int $totalAssetsIdr,
        int $nonHalalRevenueIdr,
        int $totalRevenueIdr,
        string $sectorCode = 'GENERAL_COMMERCE'
    ): ShariahScreeningResult {
        if ($totalAssetsIdr <= 0) {
            throw new InvalidArgumentException("Total aset harus positif: {$totalAssetsIdr}");
        }
        if ($totalRevenueIdr <= 0) {
            throw new InvalidArgumentException("Total pendapatan harus positif: {$totalRevenueIdr}");
        }
        if ($interestBearingDebtIdr < 0 || $nonHalalRevenueIdr < 0) {
            throw new InvalidArgumentException('Utang dan pendapatan non-halal tidak boleh negatif');
        }

        // Rasio dalam bps: (debt * 10000) / totalAssets
        $debtRatioBps = BigDecimal::of($interestBearingDebtIdr)
            ->multipliedBy(10000)
            ->dividedBy($totalAssetsIdr, 0, RoundingMode::HalfUp)
            ->toInt();

        // Rasio non-halal: (nonHalal * 10000) / totalRevenue
        $nonHalalRevenueRatioBps = BigDecimal::of($nonHalalRevenueIdr)
            ->multipliedBy(10000)
            ->dividedBy($totalRevenueIdr, 0, RoundingMode::HalfUp)
            ->toInt();

        $normalizedSector = strtoupper(trim($sectorCode));
        $sectorAllowed = ! in_array($normalizedSector, self::PROHIBITED_SECTORS, true);

        $debtCompliant = ($debtRatioBps <= self::MAX_DEBT_RATIO_BPS);
        $revenueCompliant = ($nonHalalRevenueRatioBps <= self::MAX_NON_HALAL_REVENUE_BPS);
        $isCompliant = $debtCompliant && $revenueCompliant && $sectorAllowed;

        $result = new ShariahScreeningResult(
            interestBearingDebtIdr: $interestBearingDebtIdr,
            totalAssetsIdr: $totalAssetsIdr,
            debtRatioBps: $debtRatioBps,
            nonHalalRevenueIdr: $nonHalalRevenueIdr,
            totalRevenueIdr: $totalRevenueIdr,
            nonHalalRevenueRatioBps: $nonHalalRevenueRatioBps,
            sectorCode: $normalizedSector,
            sectorAllowed: $sectorAllowed,
            debtCompliant: $debtCompliant,
            revenueCompliant: $revenueCompliant,
            isCompliant: $isCompliant
        );

        (new ShariahScreeningCertificateVerifier())->verify($result);

        return $result;
    }

    /**
     * Hitung purifikasi dividen atas pendapatan non-halal yang masih dalam batas toleransi.
     *
     * @param  int  $dividendAmountIdr  Total dividen kotor yang diterima investor (IDR)
     * @param  int  $nonHalalRatioBps  Rasio pendapatan non-halal emiten (bps)
     * @return array{purification_charity_idr: int, net_halal_dividend_idr: int}
     */
    public function purifyDividend(int $dividendAmountIdr, int $nonHalalRatioBps): array
    {
        if ($dividendAmountIdr < 0 || $nonHalalRatioBps < 0) {
            throw new InvalidArgumentException('Dividen dan rasio non-halal tidak boleh negatif');
        }

        $purificationIdr = BigDecimal::of($dividendAmountIdr)
            ->multipliedBy($nonHalalRatioBps)
            ->dividedBy(10000, 0, RoundingMode::HalfUp)
            ->toInt();

        $netHalalIdr = max(0, $dividendAmountIdr - $purificationIdr);

        return [
            'purification_charity_idr' => $purificationIdr,
            'net_halal_dividend_idr' => $netHalalIdr,
        ];
    }
}
