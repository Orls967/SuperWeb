<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Modules\Core\Contracts\SimulationRunner;
use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\StateMachines\SimulationRunStateMachine;
use Modules\Core\Domain\StateMachines\SimulationRunStatus;
use Modules\Core\Http\Requests\CreateSimulationRunRequest;

class SimulationRunController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected SimulationRunner $runner,
        protected SimulationRunStateMachine $stateMachine,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', SimRun::class);

        $query = SimRun::query()->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('mode')) {
            $query->where('mode', (string) $request->query('mode'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $runs = $query->paginate(15)->withQueryString();

        $metrics = [
            'total_runs' => SimRun::count(),
            'running_runs' => SimRun::where('status', SimulationRunStatus::RUNNING->value)->count(),
            'completed_runs' => SimRun::where('status', SimulationRunStatus::COMPLETED->value)->count(),
            'sandbox_runs' => SimRun::where('mode', 'sandbox')->count(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $runs->items(),
                'metrics' => $metrics,
                'current_page' => $runs->currentPage(),
                'last_page' => $runs->lastPage(),
                'total' => $runs->total(),
            ]);
        }

        return view('core::admin.simulation.index', [
            'runs' => $runs,
            'metrics' => $metrics,
            'filters' => $request->only(['status', 'mode', 'search']),
        ]);
    }

    public function store(CreateSimulationRunRequest $request): JsonResponse
    {
        $idempotencyKey = (string) $request->header('Idempotency-Key');
        $cacheKey = "idemp:sim:run:create:{$idempotencyKey}";

        $cachedResponse = Cache::get($cacheKey);
        if ($cachedResponse !== null) {
            return response()->json($cachedResponse, 200);
        }

        $validated = $request->validated();
        $name = (string) $validated['name'];
        $days = (int) $validated['days'];
        $seed = (int) $validated['seed'];
        $mode = (string) ($validated['mode'] ?? 'live');

        $runId = $this->runner->start($name, $days, $seed, $mode);

        $run = SimRun::where('name', $name)->orWhere('id', $runId)->firstOrFail();
        if ($request->user()) {
            $run->created_by = $request->user()->id;
            $run->save();
        }

        $responseData = [
            'status' => 'success',
            'data' => [
                'id' => (string) $run->id,
                'name' => $run->name,
                'status' => $run->status,
                'current_day' => $run->current_day,
                'total_days' => $run->total_days,
                'seed' => $run->seed,
                'mode' => $run->mode,
                'chain_hash' => $run->chain_hash,
            ],
        ];

        Cache::put($cacheKey, $responseData, now()->addHours(24));

        return response()->json($responseData, 201);
    }

    public function show(Request $request, string $runId): View|JsonResponse
    {
        $run = SimRun::where('id', $runId)->orWhere('name', $runId)->firstOrFail();
        $this->authorize('view', $run);

        $checkpoints = $run->checkpoints()->orderBy('day_number', 'desc')->paginate(10);
        $transitions = $run->transitions()->orderBy('id', 'desc')->take(20)->get();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $run,
                'checkpoints' => $checkpoints->items(),
                'transitions' => $transitions,
            ]);
        }

        return view('core::admin.simulation.show', [
            'run' => $run,
            'checkpoints' => $checkpoints,
            'transitions' => $transitions,
        ]);
    }

    public function pause(Request $request, string $runId): JsonResponse
    {
        $run = SimRun::where('id', $runId)->orWhere('name', $runId)->firstOrFail();
        $this->authorize('pause', $run);

        $this->stateMachine->transition(
            $run,
            SimulationRunStatus::PAUSED,
            'Sesi simulasi dijeda manual oleh operator.'
        );

        return response()->json([
            'status' => 'success',
            'message' => "Sesi simulasi [{$run->name}] berhasil dijeda.",
            'data' => $run,
        ]);
    }

    public function resume(Request $request, string $runId): JsonResponse
    {
        $run = SimRun::where('id', $runId)->orWhere('name', $runId)->firstOrFail();
        $this->authorize('resume', $run);

        $this->runner->resume($run->id);

        return response()->json([
            'status' => 'success',
            'message' => "Sesi simulasi [{$run->name}] berhasil dilanjutkan.",
            'data' => $run->fresh(),
        ]);
    }
}
