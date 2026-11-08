<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

use InvalidArgumentException;

/**
 * H16: EventMarginCalculator
 * Kalkulasi margin kotor event MICE & Banquet dengan penegakan batas bawah guardrail >= 40.0%.
 */
final class EventMarginCalculator
{
    public const STATUS_APPROVED = 'GUARDRAIL_COMPLIANT_APPROVED';

    public const STATUS_REJECTED = 'GUARDRAIL_BREACH_REJECTED';

    /**
     * @param  array<string, int>  $revenueBreakdown  Pos pendapatan: hall, fnb, av, rooms, expo, dll.
     * @param  array<string, int>  $costBreakdown  Pos biaya: food_cogs, bev_cogs, labor, vendor_av, utility, dll.
     */
    public function calculate(
        string $proposalId,
        array $revenueBreakdown,
        array $costBreakdown,
        float $guardrailMinMarginPercent = 40.0,
        string $secretKey = 'superweb-hotel-mice-salt'
    ): EventMarginCertificate {
        if ($guardrailMinMarginPercent < 0.0 || $guardrailMinMarginPercent >= 100.0) {
            throw new InvalidArgumentException('Guardrail threshold percent must be between 0 and 100.');
        }

        $totalRevenue = (int) array_sum($revenueBreakdown);
        $totalCost = (int) array_sum($costBreakdown);

        $grossMarginIdr = $totalRevenue - $totalCost;

        if ($totalRevenue > 0) {
            $grossMarginPercent = round(($grossMarginIdr / $totalRevenue) * 100.0, 2);
        } else {
            $grossMarginPercent = 0.0;
        }

        $isCompliant = ($grossMarginPercent >= $guardrailMinMarginPercent);
        $status = $isCompliant ? self::STATUS_APPROVED : self::STATUS_REJECTED;

        $priceShortfallIdr = 0;
        if (! $isCompliant) {
            $requiredRevenue = (int) ceil($totalCost / max(0.01, 1.0 - ($guardrailMinMarginPercent / 100.0)));
            $priceShortfallIdr = max(0, $requiredRevenue - $totalRevenue);
        }

        $payload = implode('|', [
            $proposalId,
            $totalRevenue,
            $totalCost,
            $grossMarginIdr,
            number_format($grossMarginPercent, 2, '.', ''),
            number_format($guardrailMinMarginPercent, 2, '.', ''),
            $isCompliant ? '1' : '0',
            $status,
            $priceShortfallIdr,
            json_encode($revenueBreakdown, JSON_UNESCAPED_SLASHES),
            json_encode($costBreakdown, JSON_UNESCAPED_SLASHES),
        ]);

        $signatureHash = hash_hmac('sha256', $payload, $secretKey);

        return new EventMarginCertificate(
            proposalId: $proposalId,
            totalRevenueIdr: $totalRevenue,
            totalDirectCostIdr: $totalCost,
            grossMarginIdr: $grossMarginIdr,
            grossMarginPercent: $grossMarginPercent,
            guardrailThresholdPercent: $guardrailMinMarginPercent,
            isCompliant: $isCompliant,
            status: $status,
            priceShortfallIdr: $priceShortfallIdr,
            revenueBreakdown: $revenueBreakdown,
            costBreakdown: $costBreakdown,
            signatureHash: $signatureHash
        );
    }
}
