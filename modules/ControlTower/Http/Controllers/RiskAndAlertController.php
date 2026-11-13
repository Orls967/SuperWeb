<?php

declare(strict_types=1);

namespace Modules\ControlTower\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use Modules\ControlTower\Application\Actions\ExecuteAutonomousReallocationPlaybookAction;
use Modules\ControlTower\Application\Services\AlertScannerService;
use Modules\ControlTower\Application\Services\OrderRiskAssessmentService;
use Modules\ControlTower\Domain\Models\AlertRule;
use Modules\ControlTower\Domain\Models\DisruptionAlert;
use Modules\ControlTower\Domain\Models\OrderRiskScore;
use Modules\ControlTower\Domain\Models\SopCycle;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RiskAndAlertController extends Controller
{
    public function risk(Request $request): View
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        $tier = $request->query('tier');
        $item = $request->query('item');

        $scores = OrderRiskScore::query()
            ->when(is_string($tier) && $tier !== '', fn ($q) => $q->where('risk_tier', strtoupper($tier)))
            ->when(is_string($item) && $item !== '', fn ($q) => $q->where('item_code', 'like', '%'.$item.'%'))
            ->orderByDesc('risk_score')
            ->orderBy('item_code')
            ->paginate(25)
            ->withQueryString();

        $summary = [
            'total_scored' => OrderRiskScore::query()->count(),
            'critical_count' => OrderRiskScore::query()->where('risk_tier', 'CRITICAL')->count(),
            'high_count' => OrderRiskScore::query()->where('risk_tier', 'HIGH')->count(),
            'avg_score' => (int) round((float) (OrderRiskScore::query()->avg('risk_score') ?? 0)),
        ];

        return view('control_tower::risk', [
            'scores' => $scores,
            'summary' => $summary,
            'tier' => is_string($tier) ? strtoupper($tier) : '',
            'item' => is_string($item) ? $item : '',
        ]);
    }

    public function scanRisk(Request $request, OrderRiskAssessmentService $service): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manageAlerts', SopCycle::class);

        $data = $request->validate([
            'item_code' => ['nullable', 'string', 'max:32'],
        ]);

        $itemCode = isset($data['item_code']) && $data['item_code'] !== '' ? (string) $data['item_code'] : null;
        $scanned = $service->scanAll($itemCode);

        return redirect()
            ->route('control_tower.risk.index')
            ->with('status', "Pemindaian risiko selesai: {$scanned->count()} item/pesanan dinilai.");
    }

    public function exportRiskCsv(Request $request): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'item_code',
                'order_reference',
                'risk_score',
                'risk_tier',
                'shortfall_component',
                'lead_time_component',
                'shelf_life_component',
                'transit_component',
                'mitigation_recommendation',
                'scored_at',
            ]);

            foreach (OrderRiskScore::query()->orderByDesc('risk_score')->orderBy('item_code')->cursor() as $row) {
                fputcsv($out, [
                    $row->item_code,
                    $row->order_reference ?? '',
                    $row->risk_score,
                    $row->risk_tier,
                    $row->shortfall_component,
                    $row->lead_time_component,
                    $row->shelf_life_component,
                    $row->transit_component,
                    $row->mitigation_recommendation ?? '',
                    $row->scored_at?->toIso8601String() ?? '',
                ]);
            }

            fclose($out);
        }, 'control-tower-risk-scores.csv');
    }

    public function alerts(Request $request): View
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        $status = $request->query('status');
        $severity = $request->query('severity');

        $alerts = DisruptionAlert::query()
            ->when(is_string($status) && $status !== '', fn ($q) => $q->where('status', strtolower($status)))
            ->when(is_string($severity) && $severity !== '', fn ($q) => $q->where('severity', strtoupper($severity)))
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'mitigated' THEN 1 ELSE 2 END")
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $rules = AlertRule::query()->orderBy('rule_code')->get();

        $summary = [
            'active_count' => DisruptionAlert::query()->where('status', 'active')->count(),
            'critical_count' => DisruptionAlert::query()->where('status', 'active')->where('severity', 'CRITICAL')->count(),
            'mitigated_count' => DisruptionAlert::query()->where('status', 'mitigated')->count(),
            'rules_count' => $rules->where('is_active', true)->count(),
        ];

        return view('control_tower::alerts', [
            'alerts' => $alerts,
            'rules' => $rules,
            'summary' => $summary,
            'status' => is_string($status) ? strtolower($status) : '',
            'severity' => is_string($severity) ? strtoupper($severity) : '',
        ]);
    }

    public function scanAlerts(Request $request, AlertScannerService $scanner): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manageAlerts', SopCycle::class);

        $result = $scanner->scan();

        return redirect()
            ->route('control_tower.alerts.index')
            ->with('status', "Pemindaian gangguan selesai: {$result['created_count']} peringatan baru, {$result['existing_count']} diperbarui.");
    }

    public function mitigateAlert(
        Request $request,
        int $alert,
        ExecuteAutonomousReallocationPlaybookAction $playbook,
    ): RedirectResponse {
        Gate::forUser($request->user())->authorize('manageAlerts', SopCycle::class);

        $alertModel = DisruptionAlert::query()->findOrFail($alert);

        $data = $request->validate([
            'item_code' => ['nullable', 'string', 'max:32'],
            'from_node' => ['nullable', 'string', 'max:32'],
            'to_node' => ['nullable', 'string', 'max:32'],
            'transfer_qty' => ['nullable', 'integer', 'min:1'],
        ]);

        $userId = (int) $request->user()->id;

        try {
            $proposal = is_array($alertModel->mitigation_proposal_json) ? $alertModel->mitigation_proposal_json : [];

            if (isset($data['from_node'], $data['to_node'], $data['transfer_qty'])) {
                $itemCode = (string) ($data['item_code'] ?? $proposal['item_code'] ?? '');
                if ($itemCode === '') {
                    throw new InvalidArgumentException('Kode item diperlukan untuk usulan realokasi.');
                }

                $playbook->proposeReallocation(
                    itemCode: $itemCode,
                    fromNode: (string) $data['from_node'],
                    toNode: (string) $data['to_node'],
                    transferQty: (int) $data['transfer_qty'],
                    alert: $alertModel,
                );

                $playbook->approveReallocation($alertModel->fresh() ?? $alertModel, $userId);
            } elseif (($proposal['action'] ?? null) === 'reallocation' && isset($proposal['item_code'], $proposal['from_node'], $proposal['to_node'], $proposal['transfer_qty'])) {
                $playbook->approveReallocation($alertModel, $userId);
            } else {
                $proposal['status'] = 'approved';
                $alertModel->update([
                    'status' => 'mitigated',
                    'approved_by_user_id' => $userId,
                    'mitigation_proposal_json' => $proposal,
                ]);
            }
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['mitigate' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('control_tower.alerts.index')
            ->with('status', "Peringatan {$alertModel->alert_code} berhasil dimitigasi.");
    }

    public function exportAlertsCsv(Request $request): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'alert_code',
                'dedup_key',
                'severity',
                'category',
                'title',
                'affected_orders_count',
                'status',
                'approved_by_user_id',
                'created_at',
            ]);

            foreach (DisruptionAlert::query()->latest('id')->cursor() as $row) {
                fputcsv($out, [
                    $row->alert_code,
                    $row->dedup_key ?? '',
                    $row->severity,
                    $row->category,
                    $row->title,
                    $row->affected_orders_count,
                    $row->status,
                    $row->approved_by_user_id ?? '',
                    $row->created_at?->toIso8601String() ?? '',
                ]);
            }

            fclose($out);
        }, 'control-tower-alerts.csv');
    }
}
