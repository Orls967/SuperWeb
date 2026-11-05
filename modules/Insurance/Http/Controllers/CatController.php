<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Insurance\Application\Services\CatModelingService;
use Modules\Insurance\Domain\Models\CatPlan;
use Modules\Insurance\Domain\Models\CatRun;

final class CatController extends Controller
{
    public function index(Request $request)
    {
        $runs = CatRun::orderByDesc('id')->paginate(10);
        $latest = $runs->first();
        $plans = $latest ? CatPlan::where('run_id', $latest->id)->get() : collect();

        return view('insurance::cat.index', compact('runs', 'latest', 'plans'));
    }

    public function run(Request $request, CatModelingService $service)
    {
        $validated = $request->validate([
            'portfolio_code' => ['nullable', 'string', 'max:50'],
            'seed' => ['required', 'integer'],
            'years' => ['nullable', 'integer', 'min:10', 'max:1000'],
            'events_per_peril' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $run = $service->run(
            portfolioCode: $validated['portfolio_code'] ?? 'GENERAL',
            seed: (int) $validated['seed'],
            years: (int) ($validated['years'] ?? 250),
            eventsPerPeril: (int) ($validated['events_per_peril'] ?? 10),
            actorId: $user->id
        );

        return redirect()->route('insurance.cat.show', $run->id)
            ->with('status', "Simulasi CAT {$run->run_code} berhasil diselesaikan.");
    }

    public function show(int $id)
    {
        $run = CatRun::with(['exposures', 'yearLosses'])->findOrFail($id);
        $plans = CatPlan::where('run_id', $run->id)->get();

        return view('insurance::cat.show', compact('run', 'plans'));
    }
}

