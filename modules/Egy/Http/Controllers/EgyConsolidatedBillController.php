<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\Egy\Application\Services\DistrictUtilitiesManagementService;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;

final class EgyConsolidatedBillController extends Controller
{
    public function __construct(
        private readonly DistrictUtilitiesManagementService $utilitiesService
    ) {}

    public function index(Request $request): View
    {
        $invoices = UtilityConsolidatedInvoice::with('lines')
            ->latest()
            ->paginate(15);

        return view('egy::consolidated_bills.index', [
            'invoices' => $invoices,
        ]);
    }

    public function show(Request $request, string $id): View
    {
        $invoice = UtilityConsolidatedInvoice::with('lines')->findOrFail($id);

        $user = $request->user();
        if ($user !== null && ! ($user->hasRole('admin') || $user->hasRole('energy_billing') || $user->hasRole('utility_operator'))) {
            // Anti-IDOR: hanya customer yang memiliki invoice ini yang boleh melihat
            $partyId = (string) ($user->party_id ?? $user->id);
            if ((string) $invoice->customer_party_id !== (string) $user->id && (string) $invoice->customer_party_id !== $partyId) {
                abort(403, 'Anda tidak berhak mengakses invoice properti ini');
            }
        }

        return view('egy::consolidated_bills.show', [
            'invoice' => $invoice,
        ]);
    }

    public function export(Request $request): Response
    {
        $period = (string) $request->query('period', date('Y-m'));
        $csvContent = $this->utilitiesService->generateConsolidatedBillsCsv($period);

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"consolidated-bills-{$period}.csv\"",
        ]);
    }
}
