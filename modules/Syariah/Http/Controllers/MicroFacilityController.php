<?php

declare(strict_types=1);

namespace Modules\Syariah\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Syariah\Application\Services\AgriSalamMicrofinanceService;
use Modules\Syariah\Application\Services\BmtMicrofinanceService;
use Modules\Syariah\Domain\Models\SybAgriMicroFacility;
use Modules\Syariah\Domain\Models\SybGigMicroloan;
use Modules\Syariah\Domain\Models\SybGroupFinancing;

class MicroFacilityController extends Controller
{
    public function __construct(
        protected BmtMicrofinanceService $bmtService,
        protected AgriSalamMicrofinanceService $agriService
    ) {}

    public function index(): View
    {
        $groupFinancings = SybGroupFinancing::with('members')->orderBy('id', 'desc')->paginate(10);
        $gigLoans = SybGigMicroloan::with('deductions')->orderBy('id', 'desc')->paginate(10);
        $agriFacilities = SybAgriMicroFacility::with('milestones')->orderBy('id', 'desc')->paginate(10);

        return view('syariah::micro.index', [
            'groupFinancings' => $groupFinancings,
            'gigLoans' => $gigLoans,
            'agriFacilities' => $agriFacilities,
        ]);
    }

    public function showGroup(int $id): View
    {
        $group = SybGroupFinancing::with('members')->findOrFail($id);

        return view('syariah::micro.group_show', [
            'group' => $group,
        ]);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cooperative_code' => ['required', 'string', 'max:32'],
            'group_name' => ['required', 'string', 'max:128'],
            'total_members' => ['required', 'integer', 'min:3', 'max:20'],
            'facility_amount_idr' => ['required', 'integer', 'min:500000000'],
            'shariah_contract' => ['nullable', 'string', 'max:32'],
            'profit_nisbah_shohibul_maal_bps' => ['nullable', 'integer', 'min:1000', 'max:9000'],
        ]);

        $group = $this->bmtService->createGroupFacility(
            cooperativeCode: $validated['cooperative_code'],
            groupName: $validated['group_name'],
            totalMembers: $validated['total_members'],
            facilityAmountIdr: $validated['facility_amount_idr'],
            shariahContract: $validated['shariah_contract'] ?? 'MUDHARABAH_BERTINGKAT',
            profitNisbahShohibulMaalBps: $validated['profit_nisbah_shohibul_maal_bps'] ?? 4000
        );

        return response()->json([
            'message' => 'Fasilitas pembiayaan kelompok tanggung renteng berhasil dibuat.',
            'group' => $group,
        ], 201);
    }

    public function showGig(int $id): View
    {
        $loan = SybGigMicroloan::with('deductions')->findOrFail($id);

        return view('syariah::micro.gig_show', [
            'loan' => $loan,
        ]);
    }

    public function storeGig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'worker_party_ref' => ['required', 'string', 'max:64'],
            'amount_idr' => ['required', 'integer', 'min:50000000'],
            'tenor_months' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);

        $loan = $this->bmtService->issueGigMicrocredit(
            workerPartyId: $validated['worker_party_ref'],
            amountIdr: $validated['amount_idr'],
            tenorMonths: $validated['tenor_months'] ?? 6
        );

        return response()->json([
            'message' => 'Pembiayaan mikro gig worker berhasil diterbitkan.',
            'loan' => $loan,
        ], 201);
    }

    public function showAgri(int $id): View
    {
        $facility = SybAgriMicroFacility::with('milestones')->findOrFail($id);

        return view('syariah::micro.agri_show', [
            'facility' => $facility,
        ]);
    }

    public function storeAgri(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'farmer_party_ref' => ['required', 'string', 'max:64'],
            'plot_code' => ['required', 'string', 'max:64'],
            'commodity' => ['required', 'string', 'max:64'],
            'financing_amount_idr' => ['required', 'integer', 'min:100000000'],
            'expected_yield_kg' => ['required', 'integer', 'min:100'],
            'contract_price_per_kg_idr' => ['required', 'integer', 'min:1000'],
        ]);

        $facility = $this->agriService->createFacility(
            farmerPartyId: $validated['farmer_party_ref'],
            plotCode: $validated['plot_code'],
            commodity: $validated['commodity'],
            financingAmountIdr: $validated['financing_amount_idr'],
            expectedYieldKg: $validated['expected_yield_kg'],
            contractPricePerKgIdr: $validated['contract_price_per_kg_idr']
        );

        return response()->json([
            'message' => 'Pembiayaan salam pertanian berhasil dibuat.',
            'facility' => $facility,
        ], 201);
    }

    public function disburseMilestone(int $id): JsonResponse
    {
        $facility = SybAgriMicroFacility::with('milestones')->findOrFail($id);
        $pendingMilestone = $facility->milestones->where('status', 'PENDING')->first();

        if (! $pendingMilestone) {
            return response()->json(['error' => 'Semua milestone sudah dicairkan.'], 422);
        }

        $milestone = $this->agriService->evaluateAndDisburseMilestone($pendingMilestone->milestone_code);

        return response()->json([
            'message' => 'Milestone salam pertanian berhasil dicairkan terikat validasi NDVI.',
            'milestone' => $milestone,
        ], 200);
    }
}
