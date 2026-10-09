<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\EnterpriseFinance\Domain\Models\EfCapexProposal;
use Modules\EnterpriseFinance\Domain\Models\EfMaAcquisition;
use Modules\EnterpriseFinance\Domain\Models\EfSegmentFinancialReport;
use Modules\EnterpriseFinance\Domain\Models\EfSubholdingDividend;
use RuntimeException;

class ConglomerateCapitalAndGovernanceService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * 141.1 Holding & Subholding dividend declaration.
     * Test (a): dividen holding = laba anak * porsi terverifikasi.
     */
    public function declareSubholdingDividend(array $params): EfSubholdingDividend
    {
        $netProfit = (int) $params['verified_net_profit_minor'];
        $payoutRatio = (float) $params['dividend_payout_ratio_pct'];
        $declaredMinor = (int) round(($netProfit * $payoutRatio) / 100.0);

        return DB::transaction(function () use ($params, $netProfit, $payoutRatio, $declaredMinor) {
            $divCode = 'DIV-'.strtoupper(Str::random(8));

            // Balanced ledger dividend accrual (intercompany dividend receivable vs revenue)
            $this->ledgerService->post(new PostingDTO(
                type: 'HOLDING_DIVIDEND_DECLARATION',
                description: "Holding dividend declared from {$params['subholding_name']} ({$params['subsidiary_entity_id']})",
                idempotencyKey: 'EF-'.$divCode,
                entries: [
                    PostingEntryDTO::forCode('ef:holding_dividend_receivable:IDR', 'IDR', $declaredMinor),
                    PostingEntryDTO::forCode('ef:holding_dividend_income:IDR', 'IDR', -$declaredMinor),
                ],
                referenceType: 'SUBHOLDING_DIVIDEND',
                referenceId: $divCode,
            ));

            return EfSubholdingDividend::create([
                'id' => (string) Str::uuid(),
                'dividend_code' => $divCode,
                'subholding_name' => $params['subholding_name'],
                'subsidiary_entity_id' => $params['subsidiary_entity_id'],
                'verified_net_profit_minor' => $netProfit,
                'dividend_payout_ratio_pct' => $payoutRatio,
                'dividend_declared_minor' => $declaredMinor,
                'fiscal_year' => $params['fiscal_year'],
                'status' => 'DECLARED',
            ]);
        });
    }

    /**
     * 141.2 Capital allocation engine & Capex spending enforcement.
     * Test (b): capex spending cannot exceed allocated Treasury budget.
     */
    public function submitAndApproveCapex(array $params, int $treasuryAllocatedMinor): EfCapexProposal
    {
        return EfCapexProposal::create([
            'id' => (string) Str::uuid(),
            'proposal_code' => $params['proposal_code'] ?? 'CPX-'.strtoupper(Str::random(8)),
            'line_code' => $params['line_code'],
            'project_title' => $params['project_title'],
            'requested_budget_minor' => (int) $params['requested_budget_minor'],
            'allocated_treasury_budget_minor' => $treasuryAllocatedMinor,
            'actual_spent_minor' => 0,
            'irr_pct' => (float) $params['irr_pct'],
            'npv_minor' => (int) $params['npv_minor'],
            'esg_score' => (float) ($params['esg_score'] ?? 85.0),
            'status' => 'APPROVED',
        ]);
    }

    public function recordCapexSpending(string $proposalCode, int $spendMinor): EfCapexProposal
    {
        $proposal = EfCapexProposal::where('proposal_code', $proposalCode)->firstOrFail();

        if ($proposal->actual_spent_minor + $spendMinor > $proposal->allocated_treasury_budget_minor) {
            throw new RuntimeException("Capex spending breach: Total spend exceeds allocated Treasury budget ({$proposal->allocated_treasury_budget_minor}).");
        }

        $proposal->actual_spent_minor += $spendMinor;
        $proposal->save();

        return $proposal;
    }

    /**
     * 141.3 M&A Due Diligence & Idempotent Monolith Integration Backfill.
     * Test (c): M&A integration backfill idempoten & tak duplikat.
     */
    public function registerAcquisition(array $params): EfMaAcquisition
    {
        return EfMaAcquisition::create([
            'id' => (string) Str::uuid(),
            'deal_code' => $params['deal_code'] ?? 'MA-'.strtoupper(Str::random(8)),
            'target_company_name' => $params['target_company_name'],
            'target_industry' => $params['target_industry'],
            'valuation_minor' => (int) $params['valuation_minor'],
            'deal_value_minor' => (int) $params['deal_value_minor'],
            'integration_playbook_status' => 'PENDING',
            'backfilled_entity_ids' => [],
        ]);
    }

    public function backfillAcquisitionEntities(string $dealCode, array $incomingEntities): EfMaAcquisition
    {
        $deal = EfMaAcquisition::where('deal_code', $dealCode)->firstOrFail();

        $existing = $deal->backfilled_entity_ids ?? [];
        // Idempotent union of entity IDs
        $merged = array_values(array_unique(array_merge($existing, $incomingEntities)));

        $deal->backfilled_entity_ids = $merged;
        $deal->integration_playbook_status = 'BACKFILLED';
        $deal->save();

        return $deal;
    }

    /**
     * 141.5 17-Lines Segment Reporting & Group Consolidation.
     * Test (d): sum(segment revenue) == total consolidated group revenue.
     */
    public function compileConsolidatedReport(string $quarter, array $segmentBreakdown): EfSegmentFinancialReport
    {
        $sumRev = 0;
        $sumEbitda = 0;

        foreach ($segmentBreakdown as $lineData) {
            $sumRev += (int) $lineData['revenue_minor'];
            $sumEbitda += (int) $lineData['ebitda_minor'];
        }

        return EfSegmentFinancialReport::updateOrCreate(
            ['period_quarter' => $quarter],
            [
                'id' => (string) Str::uuid(),
                'report_code' => 'REP-'.$quarter,
                'segment_contributions' => $segmentBreakdown,
                'total_consolidated_revenue_minor' => $sumRev,
                'total_consolidated_ebitda_minor' => $sumEbitda,
            ]
        );
    }
}
