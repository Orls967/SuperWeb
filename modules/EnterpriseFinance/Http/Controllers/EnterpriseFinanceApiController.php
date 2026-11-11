<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\EnterpriseFinance\Application\Services\EnterpriseFinanceLedgerPoster;
use Modules\EnterpriseFinance\Application\Services\EnterpriseFinanceService;
use Modules\EnterpriseFinance\Domain\Models\EfCapexProposal;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Modules\EnterpriseFinance\Domain\Models\SyndicatedFacility;
use Modules\EnterpriseFinance\Http\Requests\ApproveCapexRequest;
use Modules\EnterpriseFinance\Http\Requests\ConsolidateTrialBalanceRequest;
use Modules\EnterpriseFinance\Http\Requests\EvaluateCovenantsRequest;
use Modules\EnterpriseFinance\Http\Requests\SpendBudgetRequest;

class EnterpriseFinanceApiController extends Controller
{
    public function __construct(
        protected readonly EnterpriseFinanceService $service,
        protected readonly EnterpriseFinanceLedgerPoster $ledgerPoster
    ) {}

    public function spendBudget(
        SpendBudgetRequest $request,
        EnterpriseBudget $budget
    ): JsonResponse {
        $validated = $request->validated();
        $amountIdr = (int) $validated['amount_idr'];

        $budget = $this->service->realizeSpend($budget, $amountIdr);

        $idempotencyKey = (string) ($request->header('Idempotency-Key')
            ?: ('SPEND:'.$budget->id.':'.($validated['reference_no'] ?? $budget->budget_code)));

        // Posting transaksi ke ledger perbankan
        $tx = $this->ledgerPoster->postBudgetSpendRealization(
            budget: $budget,
            amountMinor: $amountIdr,
            idempotencyKey: $idempotencyKey,
            referenceNo: $validated['reference_no'] ?? null,
            userId: $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Realisasi belanja anggaran berhasil dibukukan.',
            'budget' => $budget,
            'ledger_transaction_id' => $tx->id,
        ]);
    }

    public function approveCapex(
        ApproveCapexRequest $request,
        string $code
    ): JsonResponse {
        $proposal = EfCapexProposal::where('proposal_code', $code)->firstOrFail();
        $allocated = (int) $request->validated()['allocated_minor'];

        $proposal->update([
            'allocated_treasury_budget_minor' => $allocated,
            'status' => 'APPROVED',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Proposal belanja modal (CAPEX) berhasil disetujui.',
            'proposal' => $proposal,
        ]);
    }

    public function consolidateTrialBalance(
        ConsolidateTrialBalanceRequest $request
    ): JsonResponse {
        $data = $request->validated();
        $run = $this->service->consolidateTrialBalance(
            period: $data['period'],
            entitiesData: $data['entities'],
            reportingCurrency: $data['reporting_currency'] ?? 'IDR',
            userId: $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Konsolidasi trial balance multi-entitas berhasil diproses.',
            'run' => $run,
        ], 201);
    }

    public function evaluateCovenants(
        EvaluateCovenantsRequest $request,
        SyndicatedFacility $facility
    ): JsonResponse {
        $v = $request->validated();
        $evaluation = $this->service->evaluateDebtCovenants(
            facility: $facility,
            evaluationPeriod: $v['period'],
            ebitdaMinor: (int) $v['ebitda_minor'],
            taxPaidMinor: (int) $v['tax_paid_minor'],
            debtServiceMinor: (int) $v['debt_service_minor'],
            totalDebtMinor: (int) $v['total_debt_minor'],
            totalEquityMinor: (int) $v['total_equity_minor'],
            ebitMinor: (int) $v['ebit_minor'],
            interestExpenseMinor: (int) $v['interest_expense_minor'],
            currentAssetsMinor: (int) $v['current_assets_minor'],
            currentLiabilitiesMinor: (int) $v['current_liabilities_minor'],
            userId: $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Evaluasi covenant fasilitas utang berhasil dihitung.',
            'evaluation' => $evaluation,
        ], 201);
    }

    public function checkSod(Request $request): JsonResponse
    {
        $roles = (array) $request->input('roles', []);
        $result = $this->service->checkSodConflict($roles);

        return response()->json([
            'success' => true,
            'result' => $result,
        ]);
    }
}
