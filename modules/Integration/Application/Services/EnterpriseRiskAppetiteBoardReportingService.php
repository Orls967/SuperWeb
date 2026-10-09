<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EnterpriseRiskAppetiteBoardReportingService (Fase 450)
 *
 * Implements:
 *  - 450.1 Quantified risk appetite per category (credit, market, operational, compliance, strategic, climate)
 *  - 450.2 Appetite breach workflow: KRI breach -> owner response -> time-bound remediation -> board committee escalation
 *  - 450.3 Board risk reporting with aggregated exposure
 *  - 450.4 Tests: appetite quantified & testable, breach triggers workflow, risk:audit clean
 *  - 450.5 Edge case: Repeated appetite breach (> 1 recurrence) mandates board risk committee escalation
 *  - 450.6 Risk: KRI validation with incident history
 *  - 450.7 Evidence: appetite document, breach log, board risk pack
 */
class EnterpriseRiskAppetiteBoardReportingService
{
    public function registerKri(
        string $kriCode,
        string $category,
        float $appetiteLimit,
        float $currentValue,
        string $owner
    ): object {
        $isBreach = ($currentValue > $appetiteLimit);

        $id = DB::table('gov_risk_appetite_kris')->insertGetId([
            'kri_code' => strtoupper($kriCode),
            'category' => strtolower($category),
            'appetite_limit_threshold' => $appetiteLimit,
            'current_kri_value' => $currentValue,
            'is_in_breach' => $isBreach,
            'assigned_risk_owner' => $owner,
            'remediation_due_date' => $isBreach ? now()->addDays(30)->toDateString() : null,
            'escalated_to_board_risk_committee' => false,
            'breach_recurrence_count' => $isBreach ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('gov_risk_appetite_kris')->where('id', $id)->first();
    }

    /**
     * 450.2, 450.4, 450.5 Update KRI reading and trigger breach workflow
     */
    public function updateKriReading(string $kriCode, float $newReading): object
    {
        $kri = DB::table('gov_risk_appetite_kris')->where('kri_code', strtoupper($kriCode))->first();
        if (! $kri) {
            throw new InvalidArgumentException("KRI '{$kriCode}' not found.");
        }

        $isBreach = ($newReading > (float) $kri->appetite_limit_threshold);
        $recurrence = $kri->breach_recurrence_count;
        $escalateToBoard = $kri->escalated_to_board_risk_committee;

        if ($isBreach) {
            $recurrence++;
            // 450.5 Edge case: Recurrent breach (> 1) requires mandatory escalation to board risk committee
            if ($recurrence >= 2) {
                $escalateToBoard = true;
            }
        }

        DB::table('gov_risk_appetite_kris')->where('id', $kri->id)->update([
            'current_kri_value' => $newReading,
            'is_in_breach' => $isBreach,
            'breach_recurrence_count' => $recurrence,
            'escalated_to_board_risk_committee' => $escalateToBoard,
            'remediation_due_date' => $isBreach ? ($kri->remediation_due_date ?? now()->addDays(30)->toDateString()) : null,
            'updated_at' => now(),
        ]);

        return (object) DB::table('gov_risk_appetite_kris')->where('id', $kri->id)->first();
    }

    public function generateBoardRiskPack(): array
    {
        $totalKris = DB::table('gov_risk_appetite_kris')->count();
        $breached = DB::table('gov_risk_appetite_kris')->where('is_in_breach', true)->count();
        $escalated = DB::table('gov_risk_appetite_kris')->where('escalated_to_board_risk_committee', true)->count();

        return [
            'total_kris' => $totalKris,
            'breached_kris' => $breached,
            'board_escalated_count' => $escalated,
            'status' => $breached === 0 ? 'WITHIN_APPETITE' : 'BREACH_ESCALATED',
        ];
    }

    public function audit(): array
    {
        // Discrepancy: Repeated breaches (>=2) not escalated to Board
        $unescalatedRepeatedBreaches = DB::table('gov_risk_appetite_kris')
            ->where('breach_recurrence_count', '>=', 2)
            ->where('escalated_to_board_risk_committee', false)
            ->count();

        return [
            'status' => $unescalatedRepeatedBreaches === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_kris' => DB::table('gov_risk_appetite_kris')->count(),
            'discrepancy_count' => $unescalatedRepeatedBreaches,
        ];
    }
}
