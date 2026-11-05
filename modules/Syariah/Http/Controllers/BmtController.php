<?php

declare(strict_types=1);

namespace Modules\Syariah\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Syariah\Application\Services\BmtMicrofinanceService;
use Modules\Syariah\Domain\Models\SybBmtCooperative;
use Modules\Syariah\Domain\Models\SybBmtSavings;

class BmtController extends Controller
{
    public function __construct(
        protected BmtMicrofinanceService $bmtService
    ) {}

    public function index(): View
    {
        $cooperatives = SybBmtCooperative::withCount(['savings', 'microFacilities', 'groupFinancings'])
            ->orderBy('id', 'desc')
            ->paginate(15);

        return view('syariah::bmt.index', [
            'cooperatives' => $cooperatives,
        ]);
    }

    public function show(int $id): View
    {
        $cooperative = SybBmtCooperative::with(['savings', 'microFacilities', 'groupFinancings'])
            ->findOrFail($id);

        return view('syariah::bmt.cooperative_show', [
            'cooperative' => $cooperative,
        ]);
    }

    public function storeCooperative(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cooperative_code' => ['nullable', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:128'],
            'legal_entity_number' => ['required', 'string', 'max:64'],
            'city' => ['required', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:255'],
            'dps_advisor' => ['required', 'string', 'max:128'],
        ]);

        $coop = $this->bmtService->createCooperative($validated);

        return response()->json([
            'message' => 'Koperasi Syariah / BMT berhasil didaftarkan.',
            'cooperative' => $coop,
        ], 201);
    }

    public function openSavings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cooperative_code' => ['required', 'string', 'max:32'],
            'customer_party_ref' => ['required', 'string', 'max:64'],
            'contract_type' => ['nullable', 'string', 'in:WADIAH_YAD_DHAMANAH,MUDHARABAH_MUTLAQAH,MUDHARABAH_MUQAYYADAH'],
            'initial_deposit_idr' => ['nullable', 'integer', 'min:0'],
        ]);

        $savings = $this->bmtService->openSavingsAccount(
            cooperativeCode: $validated['cooperative_code'],
            customerPartyId: $validated['customer_party_ref'],
            contractType: $validated['contract_type'] ?? 'WADIAH_YAD_DHAMANAH',
            initialDepositIdr: $validated['initial_deposit_idr'] ?? 0
        );

        return response()->json([
            'message' => 'Rekening simpanan BMT berhasil dibuka.',
            'savings' => $savings,
        ], 201);
    }

    public function showSavings(int $id): View
    {
        $savings = SybBmtSavings::with(['cooperative', 'transactions' => fn ($q) => $q->orderBy('id', 'desc')])
            ->findOrFail($id);

        return view('syariah::bmt.savings_show', [
            'savings' => $savings,
        ]);
    }

    public function depositSavings(Request $request, int $id): JsonResponse
    {
        $savings = SybBmtSavings::findOrFail($id);
        $validated = $request->validate([
            'amount_idr' => ['required', 'integer', 'min:100000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $tx = $this->bmtService->depositSavings(
            accountNumber: $savings->account_number,
            amountIdr: $validated['amount_idr'],
            note: $validated['note'] ?? 'Setoran Simpanan'
        );

        return response()->json([
            'message' => 'Setoran tabungan BMT berhasil diproses.',
            'transaction' => $tx,
        ], 200);
    }

    public function withdrawSavings(Request $request, int $id): JsonResponse
    {
        $savings = SybBmtSavings::findOrFail($id);
        $validated = $request->validate([
            'amount_idr' => ['required', 'integer', 'min:50000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $tx = $this->bmtService->withdrawSavings(
            accountNumber: $savings->account_number,
            amountIdr: $validated['amount_idr'],
            note: $validated['note'] ?? 'Penarikan Simpanan'
        );

        return response()->json([
            'message' => 'Penarikan tabungan BMT berhasil diproses.',
            'transaction' => $tx,
        ], 200);
    }
}
