<?php

declare(strict_types=1);

namespace Modules\Marine\UI\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Marine\Application\Services\MarineAquacultureService;
use Modules\Marine\Domain\Models\MarineCycle;
use Modules\Marine\Domain\Models\MarinePayout;
use Modules\Marine\Domain\Models\MarinePond;

class MarineController extends Controller
{
    public function index(Request $request, MarineAquacultureService $service)
    {
        $user = $request->user();
        $isStaff = $user && in_array($user->role, ['admin', 'manager', 'officer'], true);

        $pondsQuery = MarinePond::query();
        $cyclesQuery = MarineCycle::with(['pond', 'farmer', 'harvest', 'payout']);
        $payoutsQuery = MarinePayout::with(['cycle', 'farmer']);

        if (! $isStaff && $user) {
            $pondsQuery->where('farmer_id', $user->id);
            $cyclesQuery->where('farmer_id', $user->id);
            $payoutsQuery->where('farmer_id', $user->id);
        }

        $ponds = $pondsQuery->latest()->take(20)->get();
        $cycles = $cyclesQuery->latest()->take(20)->get();
        $payouts = $payoutsQuery->latest()->take(20)->get();
        $audit = $service->auditMarine();

        return view('marine::index', compact('ponds', 'cycles', 'payouts', 'audit'));
    }

    public function showPond(Request $request, int $id)
    {
        $pond = MarinePond::findOrFail($id);
        $user = $request->user();

        $isStaff = $user && in_array($user->role, ['admin', 'manager', 'officer'], true);
        if (! $isStaff && (! $user || (int) $pond->farmer_id !== (int) $user->id)) {
            abort(403, 'Akses kolam ditolak (anti-IDOR).');
        }

        return response()->json($pond);
    }
}
