<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mining\Domain\Models\MiningContractorPaymentHold;
use Modules\Mining\Domain\Models\MiningFuelAnomalyCase;
use Modules\Mining\Domain\Models\MiningHaulCycle;

class MiningContractorPortalController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $partyId = (string) $user?->id;

        // Tampilkan hanya data milik kontraktor yang sedang login
        $myCycles = MiningHaulCycle::with(['truck', 'shovel'])
            ->latest()
            ->paginate(15);

        $myCases = MiningFuelAnomalyCase::where('contractor_party_ref', $partyId)
            ->with(['equipment', 'paymentHolds'])
            ->latest()
            ->get();

        $myHolds = MiningContractorPaymentHold::where('contractor_party_ref', $partyId)
            ->latest()
            ->get();

        return view('mining::contractor.index', compact('user', 'myCycles', 'myCases', 'myHolds'));
    }

    public function dispute(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $partyId = (string) $user?->id;

        $validated = $request->validate([
            'case_id' => 'required|string',
            'reason' => 'required|string|min:5',
        ]);

        $case = MiningFuelAnomalyCase::findOrFail($validated['case_id']);

        // Anti-IDOR Enforcement: Kontraktor dilarang menyanggah kasus milik kontraktor lain
        if ($case->contractor_party_id !== $partyId && ! ($user?->hasRole('admin') ?? false)) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki wewenang atas kasus anomali kontraktor lain.');
        }

        $case->update([
            'status' => 'disputed',
            'dispute_reason' => $validated['reason'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Sanggahan berhasil diajukan untuk penelaahan teknis.',
                'case_number' => $case->case_number,
                'status' => 'disputed',
            ]);
        }

        return redirect()->route('mining.contractor.portal')
            ->with('success', 'Sanggahan berhasil diajukan untuk penelaahan teknis.');
    }
}
