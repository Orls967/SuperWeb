<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AiFraudAmlMeshService (Fase 200)
 *
 * Implements:
 *  - 200.1 Composite fraud scoring aggregating cross-line anomaly signals
 *  - 200.2 Account freezing requires human approval
 *  - 200.3 Gapless AML Suspicious Activity Report (SAR) sequence filing
 */
class AiFraudAmlMeshService
{
    /**
     * Ingest cross-line signals and calculate composite risk score.
     */
    public function createFraudCase(string $entityId, array $signals): object
    {
        $code = 'CAS-FRD-'.strtoupper(Str::random(8));

        // Composite risk calculation
        $totalWeight = 0.0;
        foreach ($signals as $s) {
            $totalWeight += (float) ($s['weight'] ?? 10.0);
        }
        $compositeScore = min(100.0, max(0.0, round($totalWeight, 2)));

        $id = DB::table('ai_fraud_mesh_cases')->insertGetId([
            'case_code' => $code,
            'entity_id' => $entityId,
            'contributing_line_signals' => json_encode($signals),
            'composite_fraud_score' => $compositeScore,
            'case_status' => 'OPEN',
            'freeze_approved_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_fraud_mesh_cases')->find($id);
    }

    /**
     * Freeze entity account. Enforces mandatory approval by authorized AML compliance officer.
     */
    public function freezeAccount(string $caseCode, string $complianceOfficer): object
    {
        if (empty(trim($complianceOfficer))) {
            throw new \InvalidArgumentException('AML compliance requirement: Account freezing requires approval by designated compliance officer.');
        }

        DB::table('ai_fraud_mesh_cases')->where('case_code', $caseCode)->update([
            'case_status' => 'FROZEN',
            'freeze_approved_by' => $complianceOfficer,
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_fraud_mesh_cases')->where('case_code', $caseCode)->first();
    }

    /**
     * File gapless AML Suspicious Activity Report (SAR).
     */
    public function fileAmlSar(string $caseCode, float $suspiciousAmount): object
    {
        return DB::transaction(function () use ($caseCode, $suspiciousAmount) {
            $lastSeq = DB::table('ai_aml_sar_filings')->lockForUpdate()->max('sar_sequence_number') ?? 0;
            $nextSeq = (int) $lastSeq + 1;
            $refCode = 'SAR-PPATK-'.date('Y').'-'.str_pad((string) $nextSeq, 6, '0', STR_PAD_LEFT);

            $id = DB::table('ai_aml_sar_filings')->insertGetId([
                'sar_sequence_number' => $nextSeq,
                'sar_reference_code' => $refCode,
                'case_code' => $caseCode,
                'suspicious_amount_idr' => $suspiciousAmount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (object) DB::table('ai_aml_sar_filings')->find($id);
        });
    }

    /**
     * Quality audit gate (`fraud:audit`).
     */
    public function audit(): array
    {
        $unapprovedFreezes = DB::table('ai_fraud_mesh_cases')
            ->where('case_status', 'FROZEN')
            ->whereNull('freeze_approved_by')
            ->count();

        return [
            'status' => $unapprovedFreezes === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_cases' => DB::table('ai_fraud_mesh_cases')->count(),
            'total_sars' => DB::table('ai_aml_sar_filings')->count(),
            'discrepancy_count' => $unapprovedFreezes,
        ];
    }
}
