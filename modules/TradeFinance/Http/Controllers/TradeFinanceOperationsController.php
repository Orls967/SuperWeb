<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\TradeFinance\Application\Actions\DiscountSupplyChainFinanceInvoiceAction;
use Modules\TradeFinance\Application\Actions\IssueDocumentaryLetterOfCreditAction;
use Modules\TradeFinance\Application\Actions\VerifyLcDocumentPresentationAction;
use Modules\TradeFinance\Domain\Models\LetterOfCredit;
use Modules\TradeFinance\Domain\Models\TradeLoan;

class TradeFinanceOperationsController extends Controller
{
    public function index(): mixed
    {
        $lcs = LetterOfCredit::latest()->take(10)->get();
        $loans = TradeLoan::where('facility_type', 'scf')->latest()->take(10)->get();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'letters_of_credit' => $lcs,
                'scf_loans' => $loans,
            ]);
        }

        return view('trade_finance::trade_finance_dashboard', compact('lcs', 'loans'));
    }

    public function issueLc(Request $request, IssueDocumentaryLetterOfCreditAction $action): JsonResponse
    {
        $data = $request->validate([
            'applicant_name' => 'required|string|max:120',
            'beneficiary_name' => 'required|string|max:120',
            'issuing_bank' => 'required|string|max:100',
            'advising_bank' => 'required|string|max:100',
            'currency' => 'nullable|string|size:3',
            'amount_foreign' => 'required|integer|min:1',
            'amount_functional_idr' => 'required|integer|min:1',
            'issue_date' => 'required|date',
            'expiry_date' => 'required|date|after_or_equal:issue_date',
            'tenor_days' => 'nullable|integer|min:0',
            'type' => 'nullable|string|in:sight,usance,standby',
            'lc_number' => 'nullable|string|max:40',
        ]);

        $lc = $action->execute($data);

        return response()->json([
            'success' => true,
            'message' => 'Documentary letter of credit issued successfully.',
            'letter_of_credit' => $lc,
        ], 201);
    }

    public function verifyPresentation(Request $request, LetterOfCredit $lc, VerifyLcDocumentPresentationAction $action): JsonResponse
    {
        $data = $request->validate([
            'documents' => 'required|array|min:1',
            'documents.*.doc_name' => 'required|string|max:80',
            'documents.*.document_number' => 'required|string|max:80',
            'documents.*.has_discrepancy' => 'nullable|boolean',
            'documents.*.discrepancy_details' => 'nullable|string|max:255',
            'documents.*.is_waived_by_applicant' => 'nullable|boolean',
        ]);

        $result = $action->execute($lc, $data['documents']);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function discountScf(Request $request, DiscountSupplyChainFinanceInvoiceAction $action): JsonResponse
    {
        $data = $request->validate([
            'borrower_name' => 'required|string|max:120',
            'principal_amount_idr' => 'required|integer|min:1',
            'annual_rate_percent' => 'required|numeric|min:0',
            'tenor_days' => 'required|integer|min:1',
            'disbursed_at' => 'nullable|date',
            'due_date' => 'nullable|date',
            'loan_number' => 'nullable|string|max:40',
        ]);

        $result = $action->execute($data);

        return response()->json([
            'success' => true,
            'message' => 'Supply chain finance invoice discounted and disbursed.',
            'data' => $result,
        ], 201);
    }
}
