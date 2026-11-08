<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HtlBanquetOrder;

final class HotelBanquetBeveragePouringAuditService
{
    /**
     * Standard industrial pouring sizes in milliliters:
     * Wine: 150ml per glass (5 glasses per 750ml bottle)
     * Spirit / Liquor: 45ml per standard single shot
     * Beer: 330ml per serving
     */
    public const STANDARD_WINE_POUR_ML = 150.0;

    public const STANDARD_BOTTLE_WINE_ML = 750.0;

    public const STANDARD_SPIRIT_POUR_ML = 45.0;

    public const ALLOWABLE_VARIANCE_PERCENT = 5.0;

    /**
     * @param  array<int, array{item: string, bottles_opened: int, glasses_poured: int, bottle_volume_ml: float, standard_pour_ml: float}>  $dispenseLogs
     * @return array<string, mixed>
     */
    public function auditPouringVariance(string $beoOrderId, array $dispenseLogs): array
    {
        $order = HtlBanquetOrder::findOrFail($beoOrderId);
        $totalTheoreticalGlasses = 0;
        $totalActualGlasses = 0;
        $auditDiscrepancies = [];
        $totalFinancialVariance = 0.0;

        foreach ($dispenseLogs as $log) {
            $item = (string) ($log['item'] ?? 'Beverage');
            $opened = (int) ($log['bottles_opened'] ?? 0);
            $poured = (int) ($log['glasses_poured'] ?? 0);
            $volume = (float) ($log['bottle_volume_ml'] ?? self::STANDARD_BOTTLE_WINE_ML);
            $standardPour = (float) ($log['standard_pour_ml'] ?? self::STANDARD_WINE_POUR_ML);

            $expectedGlassesPerBottle = $volume / max(1.0, $standardPour);
            $theoreticalTotal = (int) floor($opened * $expectedGlassesPerBottle);

            $varianceGlasses = $poured - $theoreticalTotal;
            $variancePct = $theoreticalTotal > 0 ? (($poured - $theoreticalTotal) / $theoreticalTotal) * 100.0 : 0.0;

            // Flag if over-pouring or under-pouring exceeds tolerance
            $flagged = abs($variancePct) > self::ALLOWABLE_VARIANCE_PERCENT;
            $estimatedGlassCost = 85000.0; // standard cost per glass IDR
            $varianceCost = round($varianceGlasses * $estimatedGlassCost, 2);

            $totalTheoreticalGlasses += $theoreticalTotal;
            $totalActualGlasses += $poured;
            $totalFinancialVariance += $varianceCost;

            $auditDiscrepancies[] = [
                'item' => $item,
                'bottles_opened' => $opened,
                'theoretical_glasses' => $theoreticalTotal,
                'actual_glasses' => $poured,
                'variance_glasses' => $varianceGlasses,
                'variance_percent' => round($variancePct, 2),
                'financial_variance' => $varianceCost,
                'audit_status' => $flagged ? 'warning_variance_exceeded' : 'nominal',
            ];
        }

        $overallVariancePct = $totalTheoreticalGlasses > 0
            ? round((($totalActualGlasses - $totalTheoreticalGlasses) / $totalTheoreticalGlasses) * 100.0, 2)
            : 0.0;

        return [
            'beo_order_id' => $order->id,
            'beo_number' => $order->beo_number,
            'total_theoretical_glasses' => $totalTheoreticalGlasses,
            'total_actual_glasses' => $totalActualGlasses,
            'overall_variance_percent' => $overallVariancePct,
            'total_financial_variance' => $totalFinancialVariance,
            'is_within_compliance' => abs($overallVariancePct) <= self::ALLOWABLE_VARIANCE_PERCENT,
            'audit_items' => $auditDiscrepancies,
        ];
    }
}
