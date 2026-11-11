<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\EnterpriseFinance\Application\Services\CorporateTaxReconciliationService;
use Modules\EnterpriseFinance\Domain\Models\TaxReconSummary;

class CorporateTaxController extends Controller
{
    public function __construct(
        protected readonly CorporateTaxReconciliationService $taxService
    ) {}

    public function index(Request $request): View
    {
        $summaries = TaxReconSummary::query()
            ->orderBy('period', 'desc')
            ->paginate(15);

        return view('enterprise_finance::tax_reconciliation', [
            'summaries' => $summaries,
        ]);
    }

    public function settle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'entity_code' => ['required', 'string'],
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $this->taxService->settleVat(
            entityCode: $validated['entity_code'],
            period: $validated['period'],
            operatorId: (int) ($request->user()?->id ?? 1)
        );

        return redirect()->route('enterprise_finance.tax')
            ->with('status', "Setoran PPN Masa {$validated['period']} berhasil dibukukan.");
    }
}

