<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Services;

use InvalidArgumentException;
use Modules\EnterpriseFinance\Domain\DTOs\ConsolidatedFinancialRatiosDTO;

/**
 * Penganalisis Rasio Keuangan Konsolidasi Grup (Corporate Financial Ratios) (54.2 & 54.9).
 * Menghitung seluruh rasio keuangan fundamental korporat secara deterministik tanpa float IEEE 754:
 * 1. Rasio Likuiditas:
 *    - Current Ratio = (Aset Lancar / Liabilitas Jangka Pendek) * 1000‰
 *    - Quick Ratio = ((Kas + Piutang) / Liabilitas Jangka Pendek) * 1000‰
 *    - Cash Ratio = (Kas & Setara Kas / Liabilitas Jangka Pendek) * 1000‰
 * 2. Rasio Solvabilitas:
 *    - Debt to Equity Ratio (DER) = (Total Utang Berbunga / Total Ekuitas) * 1000‰
 *    - Debt to Assets Ratio (DAR) = (Total Utang Berbunga / Total Aset) * 1000‰
 *    - Interest Coverage Ratio (ISCR) = (EBIT / Beban Bunga) * 1000‰
 * 3. Rasio Profitabilitas:
 *    - EBITDA Margin = (EBITDA / Pendapatan) * 10.000 bps
 *    - Net Profit Margin (NPM) = (Laba Bersih / Pendapatan) * 10.000 bps
 *    - Return on Assets (ROA) = (Laba Bersih / Total Aset) * 10.000 bps
 *    - Return on Equity (ROE) = (Laba Bersih / Total Ekuitas) * 10.000 bps
 * 4. Rasio Efisiensi:
 *    - Asset Turnover = (Pendapatan / Total Aset) * 1000‰
 */
class ConsolidatedFinancialRatioAnalyzer
{
    public function analyzeRatios(
        string $period,
        int $revenueMinor,
        int $ebitdaMinor,
        int $ebitMinor,
        int $netProfitMinor,
        int $interestExpenseMinor,
        int $cashMinor,
        int $arMinor,
        int $currentAssetsMinor,
        int $totalAssetsMinor,
        int $currentLiabilitiesMinor,
        int $interestBearingDebtMinor,
        int $totalEquityMinor
    ): ConsolidatedFinancialRatiosDTO {
        if ($currentLiabilitiesMinor <= 0 || $totalAssetsMinor <= 0 || $totalEquityMinor <= 0) {
            throw new InvalidArgumentException('Liabilitas jangka pendek, total aset, dan total ekuitas harus bernilai positif untuk kalkulasi rasio.');
        }

        // Likuiditas
        $currentRatio = (int) intdiv($currentAssetsMinor * 1000, $currentLiabilitiesMinor);
        $quickAssets = $cashMinor + $arMinor;
        $quickRatio = (int) intdiv($quickAssets * 1000, $currentLiabilitiesMinor);
        $cashRatio = (int) intdiv($cashMinor * 1000, $currentLiabilitiesMinor);

        // Solvabilitas
        $der = (int) intdiv($interestBearingDebtMinor * 1000, $totalEquityMinor);
        $dar = (int) intdiv($interestBearingDebtMinor * 1000, $totalAssetsMinor);
        $iscr = ($interestExpenseMinor > 0)
            ? (int) intdiv($ebitMinor * 1000, $interestExpenseMinor)
            : 9999;

        // Profitabilitas
        $ebitdaMargin = ($revenueMinor > 0)
            ? (int) intdiv($ebitdaMinor * 10000, $revenueMinor)
            : 0;

        $npm = ($revenueMinor > 0)
            ? (int) intdiv($netProfitMinor * 10000, $revenueMinor)
            : 0;

        $roa = (int) intdiv($netProfitMinor * 10000, $totalAssetsMinor);
        $roe = (int) intdiv($netProfitMinor * 10000, $totalEquityMinor);

        // Efisiensi
        $assetTurnover = (int) intdiv($revenueMinor * 1000, $totalAssetsMinor);

        return new ConsolidatedFinancialRatiosDTO(
            period: $period,
            currentRatioPermille: $currentRatio,
            quickRatioPermille: $quickRatio,
            cashRatioPermille: $cashRatio,
            debtToEquityPermille: $der,
            debtToAssetsPermille: $dar,
            interestCoveragePermille: $iscr,
            ebitdaMarginBps: $ebitdaMargin,
            netProfitMarginBps: $npm,
            returnOnAssetsBps: $roa,
            returnOnEquityBps: $roe,
            assetTurnoverPermille: $assetTurnover
        );
    }
}
