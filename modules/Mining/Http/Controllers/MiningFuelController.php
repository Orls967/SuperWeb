<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mining\Application\Sagas\FuelAnomalyHoldSaga;
use Modules\Mining\Domain\Models\MiningFuelAnomalyCase;
use Modules\Mining\Domain\Models\MiningSite;

class MiningFuelController extends Controller
{
    public function index(Request $request): View
    {
        $sites = MiningSite::all();
        $cases = MiningFuelAnomalyCase::with(['site', 'equipment', 'paymentHolds'])
            ->latest()
            ->paginate(15);

        $totalHeldIdr = (int) MiningFuelAnomalyCase::where('status', 'payment_held')->sum('held_amount_idr');
        $totalResolvedIdr = (int) MiningFuelAnomalyCase::where('status', 'resolved_deducted')->sum('held_amount_idr');

        return view('mining::fuel.index', compact('sites', 'cases', 'totalHeldIdr', 'totalResolvedIdr'));
    }

    public function resolve(
        Request $request,
        MiningFuelAnomalyCase $case,
        FuelAnomalyHoldSaga $saga
    ): RedirectResponse {
        $validated = $request->validate([
            'action' => 'required|in:deduct,release',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validated['action'] === 'deduct') {
            $saga->resolveDeducted($case, $request->user(), $validated['notes'] ?? null);
            $msg = "Kasus anomali {$case->case_number} diselesaikan dengan pemotongan permanen tagihan.";
        } else {
            $saga->resolveReleased($case, $request->user(), $validated['notes'] ?? null);
            $msg = "Kasus anomali {$case->case_number} diselesaikan dengan perilisan kembali dana hold ke kontraktor.";
        }

        return redirect()->route('mining.fuel')->with('success', $msg);
    }
}
