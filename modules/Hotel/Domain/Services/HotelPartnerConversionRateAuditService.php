<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HtlAlliancePartner;

final class HotelPartnerConversionRateAuditService
{
    /**
     * @return array{
     *     total_partners_audited: int,
     *     anomalies_detected: int,
     *     audit_report: list<array{
     *         partner_code: string,
     *         partner_name: string,
     *         current_ratio: float,
     *         standard_benchmark_ratio: float,
     *         spread_pct: float,
     *         is_arbitrage_risk: bool,
     *         recommendation: string
     *     }>,
     *     audited_at: string
     * }
     */
    public function auditPartnerExchangeRatios(): array
    {
        $partners = HtlAlliancePartner::all();
        $auditReport = [];
        $anomaliesCount = 0;

        $benchmarks = [
            'GARUDA_MILES' => 10.0,
            'KRISFLYER' => 12.0,
            'ASIA_MILES' => 11.5,
            'QANTAS' => 13.0,
            'AIRASIA_REWARDS' => 5.0,
        ];

        foreach ($partners as $partner) {
            $code = $partner->partner_code;
            $currentRatio = (float) $partner->base_exchange_ratio;
            $benchmark = $benchmarks[$code] ?? 10.0;

            $spread = round((($currentRatio - $benchmark) / $benchmark) * 100, 2);
            $isRisk = abs($spread) > 20.0; // greater than 20% deviation

            if ($isRisk) {
                $anomaliesCount++;
            }

            $recommendation = match (true) {
                $spread > 20.0 => 'Rasio penukaran terlalu murah (devaluasi partner), segera sesuaikan rasio naik.',
                $spread < -20.0 => 'Rasio penukaran terlalu mahal (resiko arbitrase poin keluar), tinjau ulang perjanjian.',
                default => 'Rasio berada dalam ambang batas toleransi pasar normal.',
            };

            $auditReport[] = [
                'partner_code' => $code,
                'partner_name' => $partner->partner_name,
                'current_ratio' => $currentRatio,
                'standard_benchmark_ratio' => $benchmark,
                'spread_pct' => $spread,
                'is_arbitrage_risk' => $isRisk,
                'recommendation' => $recommendation,
            ];
        }

        return [
            'total_partners_audited' => count($auditReport),
            'anomalies_detected' => $anomaliesCount,
            'audit_report' => $auditReport,
            'audited_at' => now()->toIso8601String(),
        ];
    }
}
