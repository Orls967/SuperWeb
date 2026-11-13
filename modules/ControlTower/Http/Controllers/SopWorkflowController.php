<?php

declare(strict_types=1);

namespace Modules\ControlTower\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Modules\ControlTower\Application\Services\SopPublicationOrchestrator;
use Modules\ControlTower\Domain\Models\SopCycle;
use Modules\ControlTower\Http\Requests\PublishSopCycleRequest;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SopWorkflowController extends Controller
{
    public function consensus(
        PublishSopCycleRequest $request,
        int $cycle,
        SopPublicationOrchestrator $orchestrator,
    ): JsonResponse|RedirectResponse {
        Gate::forUser($request->user())->authorize('manageSop', SopCycle::class);

        $sopCycle = SopCycle::query()->findOrFail($cycle);
        $data = $request->validated();

        $action = isset($data['action']) ? (string) $data['action'] : null;
        $toStatus = isset($data['to_status']) ? (string) $data['to_status'] : match ($action) {
            'submit_approval' => 'exec_sop',
            'publish' => 'published',
            'close' => 'closed',
            default => '',
        };

        $reason = null;
        if (isset($data['reason']) && trim((string) $data['reason']) !== '') {
            $reason = trim((string) $data['reason']);
        } elseif (isset($data['notes']) && trim((string) $data['notes']) !== '') {
            $reason = trim((string) $data['notes']);
        }

        $snapshot = isset($data['consensus_snapshot']) && is_array($data['consensus_snapshot'])
            ? $data['consensus_snapshot']
            : null;

        try {
            $updated = match (true) {
                $action === 'publish' || $toStatus === 'published' => $orchestrator->publishConsensus(
                    cycle: $sopCycle,
                    signOffUser: $request->user(),
                    notes: $reason,
                    consensusSnapshot: $snapshot,
                ),
                $action === 'submit_approval' || ($toStatus === 'exec_sop' && $sopCycle->status === 'pre_sop') => $orchestrator->submitForExecutiveApproval(
                    cycle: $sopCycle,
                    creator: $request->user(),
                ),
                $action === 'close' || $toStatus === 'closed' => $orchestrator->closeCycle(
                    cycle: $sopCycle,
                    actorUserId: $request->user()?->id,
                    notes: $reason,
                ),
                default => $orchestrator->advanceStage(
                    cycle: $sopCycle,
                    toStatus: $toStatus,
                    actorUserId: $request->user()?->id,
                    reason: $reason,
                    payload: array_filter([
                        'demand_plan_qty' => $data['demand_plan_qty'] ?? null,
                        'supply_plan_qty' => $data['supply_plan_qty'] ?? null,
                        'capacity_utilization_bps' => $data['capacity_utilization_bps'] ?? null,
                        'gross_margin_est_idr' => $data['gross_margin_est_idr'] ?? null,
                        'cash_impact_est_idr' => $data['cash_impact_est_idr'] ?? null,
                        'consensus_snapshot_json' => $snapshot,
                    ], static fn (mixed $value): bool => $value !== null),
                ),
            };
        } catch (InvalidArgumentException|RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()->withErrors(['consensus' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $updated,
            ]);
        }

        return redirect()->route('control_tower.sop.index')->with('status', 'Status konsensus S&OP berhasil diperbarui.');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'cycle_code',
                'period',
                'status',
                'scenario_type',
                'demand_plan_qty',
                'supply_plan_qty',
                'capacity_utilization_bps',
                'gross_margin_est_idr',
                'cash_impact_est_idr',
                'approval_id',
                'task_id',
                'published_at',
                'published_by_user_id',
            ]);

            foreach (SopCycle::query()->orderByDesc('period')->orderBy('id')->cursor() as $cycle) {
                fputcsv($out, [
                    $cycle->cycle_code,
                    $cycle->period,
                    $cycle->status,
                    $cycle->scenario_type,
                    $cycle->demand_plan_qty,
                    $cycle->supply_plan_qty,
                    $cycle->capacity_utilization_bps,
                    $cycle->gross_margin_est_idr,
                    $cycle->cash_impact_est_idr,
                    $cycle->approval_id,
                    $cycle->task_id,
                    $cycle->published_at?->toIso8601String(),
                    $cycle->published_by_user_id,
                ]);
            }

            fclose($out);
        }, 'control-tower-sop-cycles.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
