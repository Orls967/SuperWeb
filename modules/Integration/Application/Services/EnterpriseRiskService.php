<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * EnterpriseRiskService (Fase 202)
 *
 * Implements:
 *  - 202.1 Risk taxonomy register with inherent & residual risk scoring
 *  - 202.3 Automated KRI calculation and risk appetite breach escalation to board
 */
class EnterpriseRiskService
{
    /**
     * Register risk item with inherent & residual calculations.
     */
    public function registerRisk(string $domain, string $category, string $title, int $likelihood, int $impact, string $treatment, int $residualScore): object
    {
        $inherent = $likelihood * $impact;
        $code = 'RSK-'.strtoupper($domain).'-'.strtoupper(Str::random(6));

        $id = DB::table('erm_risk_registers')->insertGetId([
            'risk_code' => $code,
            'domain_code' => strtoupper($domain),
            'risk_category' => strtoupper($category),
            'risk_title' => $title,
            'likelihood_score' => $likelihood,
            'impact_score' => $impact,
            'inherent_risk_score' => $inherent,
            'treatment_strategy' => strtoupper($treatment),
            'residual_risk_score' => $residualScore,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('erm_risk_registers')->find($id);
    }

    /**
     * Monitor Key Risk Indicator (KRI). Flags appetite breach and escalates to board if breached.
     */
    public function monitorKri(string $domain, string $kriName, float $threshold, float $actualValue): object
    {
        $code = 'KRI-'.strtoupper($domain).'-'.strtoupper(Str::slug($kriName));
        $breached = ($actualValue > $threshold);
        $escalation = $breached ? 'ESCALATED_TO_BOARD' : 'NORMAL';

        DB::table('erm_kri_monitors')->updateOrInsert(
            ['kri_code' => $code],
            [
                'domain_code' => strtoupper($domain),
                'kri_name' => $kriName,
                'max_appetite_threshold' => $threshold,
                'actual_kri_value' => $actualValue,
                'appetite_breached' => $breached,
                'board_escalation_status' => $escalation,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('erm_kri_monitors')->where('kri_code', $code)->first();
    }

    /**
     * Quality audit gate (`risk:audit`).
     */
    public function audit(): array
    {
        // Check for unaddressed breaches
        $unaddressedBreaches = DB::table('erm_kri_monitors')
            ->where('appetite_breached', true)
            ->where('board_escalation_status', 'NORMAL')
            ->count();

        return [
            'status' => $unaddressedBreaches === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_registered_risks' => DB::table('erm_risk_registers')->count(),
            'total_kri_monitors' => DB::table('erm_kri_monitors')->count(),
            'discrepancy_count' => $unaddressedBreaches,
        ];
    }
}
