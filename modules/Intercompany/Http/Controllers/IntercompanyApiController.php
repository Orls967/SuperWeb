<?php

declare(strict_types=1);

namespace Modules\Intercompany\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Intercompany\Application\Actions\CreateMirroredIntercompanyTransactionAction;
use Modules\Intercompany\Application\Actions\GenerateBilateralEliminationJournalAction;
use Modules\Intercompany\Application\Actions\IssueIntercompanyLoanAction;
use Modules\Intercompany\Application\Actions\ValidateArmsLengthMarginAction;
use Modules\Intercompany\Domain\Models\EliminationEntry;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\IntercompanyLoan;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Modules\Intercompany\Domain\Models\SubsidiaryNci;
use Modules\Intercompany\Http\Requests\IssueLoanRequest;
use Modules\Intercompany\Http\Requests\StoreIntercompanyTransactionRequest;

class IntercompanyApiController extends Controller
{
    public function getEntities(): JsonResponse
    {
        $entities = IntercompanyEntity::where('is_active', true)->orderBy('entity_code')->get();

        return response()->json([
            'status' => 'success',
            'data' => $entities,
            'count' => $entities->count(),
        ]);
    }

    public function getTransactions(Request $request): JsonResponse
    {
        $limit = min((int) ($request->query('limit', 50)), 100);
        $txs = IntercompanyTransaction::orderByDesc('created_at')->limit($limit)->get();

        return response()->json([
            'status' => 'success',
            'data' => $txs,
            'count' => $txs->count(),
        ]);
    }

    public function storeTransaction(
        StoreIntercompanyTransactionRequest $request,
        CreateMirroredIntercompanyTransactionAction $action
    ): JsonResponse {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        $tx = $action->execute($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Mirrored intercompany transaction recorded and posted.',
            'data' => $tx,
        ], 201);
    }

    public function getLoans(Request $request): JsonResponse
    {
        $loans = IntercompanyLoan::with('schedules')->orderByDesc('created_at')->get();

        return response()->json([
            'status' => 'success',
            'data' => $loans,
            'count' => $loans->count(),
        ]);
    }

    public function issueLoan(
        IssueLoanRequest $request,
        IssueIntercompanyLoanAction $action
    ): JsonResponse {
        $data = $request->validated();
        $data['operator_id'] = $request->user()?->id;

        $loan = $action->execute($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Intercompany facility issued with amortization schedule.',
            'data' => $loan->load('schedules'),
        ], 201);
    }

    public function getEliminations(Request $request): JsonResponse
    {
        $period = (string) ($request->query('period') ?: now()->format('Y-m'));
        $eliminations = EliminationEntry::where('period', $period)->get();

        return response()->json([
            'status' => 'success',
            'period' => $period,
            'data' => $eliminations,
            'total_debit_idr' => (int) $eliminations->sum('debit_amount_idr'),
            'total_credit_idr' => (int) $eliminations->sum('credit_amount_idr'),
        ]);
    }

    public function runElimination(
        Request $request,
        GenerateBilateralEliminationJournalAction $action
    ): JsonResponse {
        $period = (string) ($request->input('period') ?: now()->format('Y-m'));
        $allowInTransit = (bool) $request->input('allow_in_transit', false);

        $res = $action->execute([
            'period' => $period,
            'allow_in_transit' => $allowInTransit,
            'operator_id' => $request->user()?->id,
        ]);

        return response()->json([
            'status' => 'success',
            'period' => $period,
            'data' => [
                'eliminated_total_idr' => $res->eliminatedTotalIdr,
                'discrepancy_idr' => $res->discrepancyIdr,
                'entries_count' => $res->eliminationEntriesCount,
                'matched_pairs' => $res->totalMatchedCount,
                'certificate_hash' => $res->certificateHash,
            ],
        ]);
    }

    public function getMatrix(Request $request): JsonResponse
    {
        $period = (string) ($request->query('period') ?: now()->format('Y-m'));
        $entities = IntercompanyEntity::where('is_active', true)->get();

        $matrix = [];
        foreach ($entities as $e1) {
            foreach ($entities as $e2) {
                if ($e1->id === $e2->id) {
                    continue;
                }

                $txs = IntercompanyTransaction::where('selling_entity', $e1->entity_code)
                    ->where('buying_entity', $e2->entity_code)
                    ->get();

                if ($txs->isNotEmpty()) {
                    $arSum = (int) $txs->sum('amount_idr');
                    $apSum = (int) $txs->where('status', '!=', 'draft')->sum('amount_idr');

                    $matrix[] = [
                        'seller' => $e1->entity_code,
                        'buyer' => $e2->entity_code,
                        'ar_amount_idr' => $arSum,
                        'ap_amount_idr' => $apSum,
                        'discrepancy_idr' => $arSum - $apSum,
                        'status' => ($arSum === $apSum) ? 'BALANCED' : 'DISCREPANCY',
                    ];
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'period' => $period,
            'matrix' => $matrix,
        ]);
    }

    public function evaluateTransferPricing(
        Request $request,
        ValidateArmsLengthMarginAction $action
    ): JsonResponse {
        $validated = $request->validate([
            'rule_id' => 'required|integer|exists:ic_transfer_pricing_rules,id',
            'cost_minor' => 'required|integer|min:1',
            'actual_price_minor' => 'required|integer|min:1',
        ]);

        $eval = $action->execute(
            ruleId: (int) $validated['rule_id'],
            costMinor: (int) $validated['cost_minor'],
            actualPriceMinor: (int) $validated['actual_price_minor']
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'is_compliant' => $eval->isCompliant(),
                'status' => $eval->status,
                'actual_margin_bps' => $eval->actualMarginBps,
                'min_benchmark_bps' => $eval->minBenchmarkBps,
                'median_benchmark_bps' => $eval->medianBenchmarkBps,
                'max_benchmark_bps' => $eval->maxBenchmarkBps,
                'variance_to_median_bps' => $eval->varianceToMedianBps,
                'recommended_adjustment_minor' => $eval->recommendedAdjustmentMinor,
            ],
        ]);
    }

    public function getNciSummary(): JsonResponse
    {
        $ncis = SubsidiaryNci::with('dividends')->get();

        return response()->json([
            'status' => 'success',
            'data' => $ncis,
            'count' => $ncis->count(),
        ]);
    }
}
