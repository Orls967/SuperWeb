<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Application\Actions\Simulation\DiffSimulationRunsAction;
use Modules\Core\Domain\Models\SimCheckpoint;
use Modules\Core\Domain\Models\SimRun;

class SimulationDiffController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly DiffSimulationRunsAction $diffAction,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', SimRun::class);

        $nameA = (string) $request->query('a');
        $nameB = (string) $request->query('b');

        $runA = null;
        $runB = null;
        $diffResult = null;

        if ($nameA !== '' && $nameB !== '') {
            $runA = SimRun::where('name', $nameA)->orWhere('id', $nameA)->first();
            $runB = SimRun::where('name', $nameB)->orWhere('id', $nameB)->first();

            if ($runA && $runB) {
                $diffResult = $this->diffAction->execute($runA, $runB)->toArray();
            }
        }

        $allRuns = SimRun::orderBy('name', 'asc')->get(['id', 'name', 'seed', 'mode']);

        if ($request->wantsJson()) {
            return response()->json([
                'run_a' => $runA,
                'run_b' => $runB,
                'diff' => $diffResult,
            ]);
        }

        return view('core::admin.simulation.diff', [
            'runA' => $runA,
            'runB' => $runB,
            'diffResult' => $diffResult,
            'allRuns' => $allRuns,
        ]);
    }

    /**
     * @return array{
     *     diverged: bool,
     *     first_divergence_day: int|null,
     *     reason: string,
     *     handler_diffs: array<string, array{a: mixed, b: mixed, identical: bool}>
     * }
     */
    protected function computeDiff(SimRun $runA, SimRun $runB): array
    {
        if ($runA->seed !== $runB->seed) {
            return [
                'diverged' => true,
                'first_divergence_day' => 1,
                'reason' => "Seed berbeda (Run A: {$runA->seed}, Run B: {$runB->seed})",
                'handler_diffs' => [],
            ];
        }

        $checkpointsA = SimCheckpoint::where('run_id', $runA->id)->orderBy('day_number', 'asc')->get()->keyBy('day_number');
        $checkpointsB = SimCheckpoint::where('run_id', $runB->id)->orderBy('day_number', 'asc')->get()->keyBy('day_number');

        $days = $checkpointsA->keys()->merge($checkpointsB->keys())->unique()->sort()->values();

        foreach ($days as $day) {
            $cpA = $checkpointsA->get($day);
            $cpB = $checkpointsB->get($day);

            if (! $cpA || ! $cpB) {
                return [
                    'diverged' => true,
                    'first_divergence_day' => (int) $day,
                    'reason' => "Keberadaan checkpoint berbeda pada hari {$day}",
                    'handler_diffs' => [],
                ];
            }

            if ($cpA->chain_hash !== $cpB->chain_hash) {
                $handlerDiffs = [];
                $keys = array_unique(array_merge(array_keys($cpA->handler_hashes ?? []), array_keys($cpB->handler_hashes ?? [])));
                sort($keys);

                foreach ($keys as $k) {
                    $vA = $cpA->handler_hashes[$k] ?? null;
                    $vB = $cpB->handler_hashes[$k] ?? null;
                    $handlerDiffs[$k] = [
                        'a' => $vA,
                        'b' => $vB,
                        'identical' => ($vA === $vB),
                    ];
                }

                return [
                    'diverged' => true,
                    'first_divergence_day' => (int) $day,
                    'reason' => "Chain hash berbeda pada hari {$day}",
                    'handler_diffs' => $handlerDiffs,
                ];
            }
        }

        return [
            'diverged' => false,
            'first_divergence_day' => null,
            'reason' => 'Kedua sesi simulasi 100% identik di seluruh hari',
            'handler_diffs' => [],
        ];
    }
}
