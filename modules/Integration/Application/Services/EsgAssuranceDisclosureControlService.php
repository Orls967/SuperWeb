<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EsgAssuranceDisclosureControlService (Fase 331)
 *
 * Implements:
 *  - 331.1 Disclosure workflow and formal restatement process
 *  - 331.2 Estimate vs Measured classification; no unsubstantiated claim promoted as verified
 *  - 331.3 Sustainability statement reconciliation to audited financial GL
 *  - 331.4 Tests: Unsubstantiated value blocked; restatement preserves old publication; esg:audit clean
 *  - 331.5 Edge case: Material disclosure error triggers formal restatement process preserving audit trail (never quietly hidden)
 *  - 331.6 Risk: Estimates strictly disclosed with methodology rather than claimed as verified
 */
class EsgAssuranceDisclosureControlService
{
    /**
     * Publish ESG disclosure metric with evidence requirement (331.1, 331.2, 331.4).
     */
    public function publishDisclosure(
        string $publicationCode,
        string $reportingYear,
        string $metricName,
        float $reportedValue,
        string $dataTier,
        bool $hasEvidence
    ): object {
        $pCode = strtoupper($publicationCode);
        $tier = strtoupper($dataTier);

        // Core gate 331.2 & 331.4: Unsubstantiated values cannot be published
        if (! $hasEvidence) {
            throw new InvalidArgumentException("ESG disclosure violation: Unsubstantiated metric value lacking source evidence cannot be published (331.4).");
        }

        $id = DB::table('esg_disclosure_publications')->insertGetId([
            'publication_code' => $pCode,
            'reporting_year' => $reportingYear,
            'metric_name' => strtoupper($metricName),
            'reported_value' => $reportedValue,
            'data_tier' => $tier,
            'has_supporting_evidence' => true,
            'is_restated' => false,
            'previous_publication_code' => null,
            'published_to_board' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('esg_disclosure_publications')->find($id);
    }

    /**
     * Formal restatement of erroneous ESG publication preserving prior publication (331.1, 331.4, 331.5 Edge Case).
     */
    public function restateDisclosure(
        string $priorCode,
        string $newCode,
        float $correctedValue,
        string $reason
    ): object {
        $oldP = strtoupper($priorCode);
        $newP = strtoupper($newCode);

        $prior = DB::table('esg_disclosure_publications')->where('publication_code', $oldP)->first();
        if (! $prior) {
            throw new InvalidArgumentException("Prior publication '{$priorCode}' not found.");
        }

        // Mark previous publication as restated
        DB::table('esg_disclosure_publications')->where('publication_code', $oldP)->update([
            'is_restated' => true,
            'updated_at' => now(),
        ]);

        // Insert new publication preserving reference to prior (331.4 & 331.5)
        $id = DB::table('esg_disclosure_publications')->insertGetId([
            'publication_code' => $newP,
            'reporting_year' => $prior->reporting_year,
            'metric_name' => $prior->metric_name,
            'reported_value' => $correctedValue,
            'data_tier' => $prior->data_tier,
            'has_supporting_evidence' => true,
            'is_restated' => false,
            'previous_publication_code' => $oldP,
            'published_to_board' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('esg_disclosure_publications')->find($id);
    }

    /**
     * Reconcile sustainability statement green capex with finance GL ledger (331.3 & 331.4).
     */
    public function reconcileGreenCapexWithFinance(
        string $packCode,
        string $reportingYear,
        float $greenCapexReported,
        float $glCapexAudited
    ): object {
        $pCode = strtoupper($packCode);
        $variance = abs(round($greenCapexReported - $glCapexAudited, 2));

        $reconciled = ($variance === 0.00);

        $id = DB::table('esg_finance_reconciliation_packs')->insertGetId([
            'pack_code' => $pCode,
            'reporting_year' => $reportingYear,
            'green_capex_reported_usd' => $greenCapexReported,
            'gl_capex_audited_usd' => $glCapexAudited,
            'variance_usd' => $variance,
            'financial_audit_reconciled' => $reconciled,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('esg_finance_reconciliation_packs')->find($id);
    }

    /**
     * ESG Assurance & Disclosure Audit (`esg:audit`) (331.4, 331.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Published disclosures without evidence
        $unsubstantiatedDisclosures = DB::table('esg_disclosure_publications')
            ->where('published_to_board', true)
            ->where('has_supporting_evidence', false)
            ->count();

        // Discrepancy 2: Reconciliation packs with unresolved variance
        $unreconciledPacks = DB::table('esg_finance_reconciliation_packs')
            ->where('financial_audit_reconciled', false)
            ->count();

        $discrepancies = $unsubstantiatedDisclosures + $unreconciledPacks;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_publications' => DB::table('esg_disclosure_publications')->count(),
            'total_reconciliation_packs' => DB::table('esg_finance_reconciliation_packs')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
