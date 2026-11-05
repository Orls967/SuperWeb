<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Modules\Insurance\Application\Services\SolvencyService;
use Modules\Insurance\Domain\Enums\SolvencyActionType;
use Modules\Insurance\Domain\Models\SolvencySnapshot;

final class CapitalController extends Controller
{
    public function index(Request $request)
    {
        $snapshots = SolvencySnapshot::with('actions')->orderByDesc('id')->paginate(10);
        $latest = $snapshots->first();

        return view('insurance::capital.index', compact('snapshots', 'latest'));
    }

    public function createSnapshot(Request $request, SolvencyService $service)
    {
        $validated = $request->validate([
            'valuation_date' => ['nullable', 'date'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $date = ! empty($validated['valuation_date'])
            ? CarbonImmutable::parse($validated['valuation_date'])
            : CarbonImmutable::now();

        $snapshot = $service->createSnapshot($date, null, null, $user->id);

        return redirect()->route('insurance.capital.index')
            ->with('status', "Snapshot solvabilitas per {$snapshot->valuation_date->format('Y-m-d')} berhasil dihitung (RBC: {$snapshot->ratio_bps} bps).");
    }

    public function simulate(Request $request, SolvencyService $service)
    {
        $validated = $request->validate([
            'snapshot_id' => ['required', 'integer'],
            'action_type' => ['required', 'string'],
            'amount_idr' => ['nullable', 'integer'],
            'quota_share_bps' => ['nullable', 'integer'],
            'reduction_pct' => ['nullable', 'integer'],
            'class' => ['nullable', 'string'],
        ]);

        $snapshot = SolvencySnapshot::findOrFail($validated['snapshot_id']);
        $type = SolvencyActionType::from($validated['action_type']);

        $simulatedRatio = $service->simulateAction($snapshot, $type, $validated);

        return response()->json([
            'action_type' => $type->value,
            'simulated_ratio_bps' => $simulatedRatio,
            'simulated_percentage' => round($simulatedRatio / 100, 2),
        ]);
    }
}

