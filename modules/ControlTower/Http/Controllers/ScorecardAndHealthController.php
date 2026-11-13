<?php

declare(strict_types=1);

namespace Modules\ControlTower\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\ControlTower\Application\Services\InventoryHealthOrchestratorService;
use Modules\ControlTower\Application\Services\SupplierScorecardOrchestratorService;
use Modules\ControlTower\Domain\Models\InventoryHealthSnapshot;
use Modules\ControlTower\Domain\Models\SopCycle;
use Modules\ControlTower\Domain\Models\SupplierScorecardSnapshot;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ScorecardAndHealthController extends Controller
{
    public function suppliers(Request $request): View
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        $tierFilter = $request->string('tier')->trim()->toString();
        $periodFilter = $request->string('period')->trim()->toString();
        $search = $request->string('search')->trim()->toString();

        $query = SupplierScorecardSnapshot::query()
            ->when($tierFilter !== '', fn ($q) => $q->where('tier', $tierFilter))
            ->when($periodFilter !== '', fn ($q) => $q->where('period', $periodFilter))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('supplier_ref', 'like', "%{$search}%")
                        ->orWhere('supplier_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('period')
            ->orderByDesc('composite_score')
            ->orderBy('supplier_ref');

        $scorecards = $query->get();

        $allForSummary = SupplierScorecardSnapshot::query()
            ->when($periodFilter !== '', fn ($q) => $q->where('period', $periodFilter))
            ->get();

        $tierCounts = [
            'STRATEGIC' => $allForSummary->where('tier', 'STRATEGIC')->count(),
            'PREFERRED' => $allForSummary->where('tier', 'PREFERRED')->count(),
            'WATCHLIST' => $allForSummary->where('tier', 'WATCHLIST')->count(),
            'AT_RISK' => $allForSummary->where('tier', 'AT_RISK')->count(),
        ];

        $totalCount = $allForSummary->count();
        $avgCompositeScore = $totalCount > 0
            ? intdiv((int) $allForSummary->sum('composite_score'), $totalCount)
            : 0;

        $periods = SupplierScorecardSnapshot::query()
            ->select('period')
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period');

        return view('control_tower::suppliers', [
            'scorecards' => $scorecards,
            'tierCounts' => $tierCounts,
            'totalCount' => $totalCount,
            'avgCompositeScore' => $avgCompositeScore,
            'periods' => $periods,
            'filters' => [
                'tier' => $tierFilter,
                'period' => $periodFilter,
                'search' => $search,
            ],
        ]);
    }

    public function refreshSuppliers(
        Request $request,
        SupplierScorecardOrchestratorService $orchestrator,
    ): RedirectResponse {
        Gate::forUser($request->user())->authorize('manageSop', SopCycle::class);

        $validated = $request->validate([
            'period' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'supplier_ref' => ['nullable', 'string', 'max:64'],
        ]);

        $snapshots = $orchestrator->generateForPeriod(
            period: $validated['period'] ?? null,
            supplierRef: $validated['supplier_ref'] ?? null,
        );

        return redirect()
            ->route('control_tower.suppliers.index')
            ->with('status', sprintf('Berhasil memperbarui %d kartu skor pemasok.', $snapshots->count()));
    }

    public function exportSuppliersCsv(Request $request): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        $tierFilter = $request->string('tier')->trim()->toString();
        $periodFilter = $request->string('period')->trim()->toString();

        return response()->streamDownload(function () use ($tierFilter, $periodFilter): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'supplier_ref',
                'supplier_name',
                'period',
                'composite_score',
                'otd_score',
                'reliability_score',
                'volume_score',
                'on_time_permil',
                'mean_lead_time_days',
                'sigma_lead_time_days',
                'samples_count',
                'tier',
                'computed_at',
            ]);

            SupplierScorecardSnapshot::query()
                ->when($tierFilter !== '', fn ($q) => $q->where('tier', $tierFilter))
                ->when($periodFilter !== '', fn ($q) => $q->where('period', $periodFilter))
                ->orderByDesc('period')
                ->orderByDesc('composite_score')
                ->orderBy('supplier_ref')
                ->cursor()
                ->each(function (SupplierScorecardSnapshot $row) use ($out): void {
                    fputcsv($out, [
                        $row->supplier_ref,
                        $row->supplier_name,
                        $row->period,
                        $row->composite_score,
                        $row->otd_score,
                        $row->reliability_score,
                        $row->volume_score,
                        $row->on_time_permil,
                        $row->mean_lead_time_days,
                        $row->sigma_lead_time_days,
                        $row->samples_count,
                        $row->tier,
                        $row->computed_at?->toIso8601String() ?? '',
                    ]);
                });

            fclose($out);
        }, 'control-tower-supplier-scorecards.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function inventoryHealth(Request $request): View
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        $statusFilter = $request->string('health_status')->trim()->toString();
        $bucketFilter = $request->string('aging_bucket')->trim()->toString();
        $nodeFilter = $request->string('echelon_node')->trim()->toString();
        $dateFilter = $request->string('snapshot_date')->trim()->toString();

        $snapshots = InventoryHealthSnapshot::query()
            ->when($statusFilter !== '', fn ($q) => $q->where('health_status', $statusFilter))
            ->when($bucketFilter !== '', fn ($q) => $q->where('aging_bucket', $bucketFilter))
            ->when($nodeFilter !== '', fn ($q) => $q->where('echelon_node', $nodeFilter))
            ->when($dateFilter !== '', fn ($q) => $q->whereDate('snapshot_date', $dateFilter))
            ->orderByDesc('snapshot_date')
            ->orderByDesc('obsolescence_risk_idr')
            ->orderBy('item_code')
            ->get();

        $allForSummary = InventoryHealthSnapshot::query()
            ->when($dateFilter !== '', fn ($q) => $q->whereDate('snapshot_date', $dateFilter))
            ->get();

        $statusCounts = [
            'DEAD_STOCK' => $allForSummary->where('health_status', 'DEAD_STOCK')->count(),
            'SLOW_MOVING' => $allForSummary->where('health_status', 'SLOW_MOVING')->count(),
            'EXCESS' => $allForSummary->where('health_status', 'EXCESS')->count(),
            'SHORTAGE_RISK' => $allForSummary->where('health_status', 'SHORTAGE_RISK')->count(),
            'HEALTHY' => $allForSummary->where('health_status', 'HEALTHY')->count(),
        ];

        $bucketCounts = [
            '0-30' => $allForSummary->where('aging_bucket', '0-30')->count(),
            '31-90' => $allForSummary->where('aging_bucket', '31-90')->count(),
            '91-180' => $allForSummary->where('aging_bucket', '91-180')->count(),
            '>180' => $allForSummary->where('aging_bucket', '>180')->count(),
        ];

        return view('control_tower::inventory_health', [
            'snapshots' => $snapshots,
            'statusCounts' => $statusCounts,
            'bucketCounts' => $bucketCounts,
            'totalTiedCapitalIdr' => (int) $allForSummary->sum('tied_capital_idr'),
            'totalObsolescenceRiskIdr' => (int) $allForSummary->sum('obsolescence_risk_idr'),
            'nodes' => InventoryHealthSnapshot::query()->select('echelon_node')->distinct()->orderBy('echelon_node')->pluck('echelon_node'),
            'dates' => InventoryHealthSnapshot::query()->select('snapshot_date')->distinct()->orderByDesc('snapshot_date')->pluck('snapshot_date'),
            'filters' => [
                'health_status' => $statusFilter,
                'aging_bucket' => $bucketFilter,
                'echelon_node' => $nodeFilter,
                'snapshot_date' => $dateFilter,
            ],
        ]);
    }

    public function refreshInventoryHealth(
        Request $request,
        InventoryHealthOrchestratorService $orchestrator,
    ): RedirectResponse {
        Gate::forUser($request->user())->authorize('manageSop', SopCycle::class);

        $validated = $request->validate([
            'snapshot_date' => ['nullable', 'date'],
            'default_daily_demand_qty' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'default_unit_cost_idr' => ['nullable', 'integer', 'min:0'],
        ]);

        $snapshots = $orchestrator->analyzeAll(
            snapshotDate: $validated['snapshot_date'] ?? null,
            defaultDailyDemandQty: (int) ($validated['default_daily_demand_qty'] ?? 10),
            defaultUnitCostIdr: (int) ($validated['default_unit_cost_idr'] ?? 100_000),
        );

        return redirect()
            ->route('control_tower.inventory_health.index')
            ->with('status', sprintf('Berhasil menganalisis %d posisi kesehatan persediaan.', $snapshots->count()));
    }

    public function exportInventoryHealthCsv(Request $request): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', SopCycle::class);

        $statusFilter = $request->string('health_status')->trim()->toString();
        $bucketFilter = $request->string('aging_bucket')->trim()->toString();
        $dateFilter = $request->string('snapshot_date')->trim()->toString();

        return response()->streamDownload(function () use ($statusFilter, $bucketFilter, $dateFilter): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'item_code',
                'echelon_node',
                'health_status',
                'aging_bucket',
                'on_hand_qty',
                'quarantine_qty',
                'available_qty',
                'daily_demand_qty',
                'dos_days',
                'oldest_lot_age_days',
                'tied_capital_idr',
                'obsolescence_risk_idr',
                'snapshot_date',
            ]);

            InventoryHealthSnapshot::query()
                ->when($statusFilter !== '', fn ($q) => $q->where('health_status', $statusFilter))
                ->when($bucketFilter !== '', fn ($q) => $q->where('aging_bucket', $bucketFilter))
                ->when($dateFilter !== '', fn ($q) => $q->whereDate('snapshot_date', $dateFilter))
                ->orderByDesc('snapshot_date')
                ->orderBy('item_code')
                ->orderBy('echelon_node')
                ->cursor()
                ->each(function (InventoryHealthSnapshot $row) use ($out): void {
                    fputcsv($out, [
                        $row->item_code,
                        $row->echelon_node,
                        $row->health_status,
                        $row->aging_bucket,
                        $row->on_hand_qty,
                        $row->quarantine_qty,
                        $row->available_qty,
                        $row->daily_demand_qty,
                        $row->dos_days,
                        $row->oldest_lot_age_days,
                        $row->tied_capital_idr,
                        $row->obsolescence_risk_idr,
                        $row->snapshot_date?->toDateString() ?? '',
                    ]);
                });

            fclose($out);
        }, 'control-tower-inventory-health.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
