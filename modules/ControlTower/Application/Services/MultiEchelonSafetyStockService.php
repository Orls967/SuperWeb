<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\ControlTower\Domain\Algorithms\MultiEchelonSafetyStock;
use Modules\ControlTower\Domain\Models\DemandForecast;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\SafetyStockPolicy;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Procurement\Contracts\SupplierLeadTimeStatistics;

final class MultiEchelonSafetyStockService
{
    public function __construct(
        private readonly MultiEchelonSafetyStock $algorithm,
        private readonly SupplierLeadTimeStatistics $leadTimeStats,
        private readonly ApprovalEngineInterface $approvalEngine,
    ) {}

    /**
     * Mengusulkan kebijakan safety stock baru berbasis stok echelon & statistik waktu tunggu pemasok.
     * Kebijakan tidak diterapkan ke nilai aktif atau EchelonStock sebelum disetujui (four-eyes).
     *
     * @param  array{
     *     demand_history?: list<int>,
     *     abc_class?: string,
     *     annual_usage_value_idr?: int,
     *     total_catalog_value_idr?: int,
     *     supplier_ref?: string|null,
     *     fallback_lead_time_days?: int,
     *     echelon_weights?: array<string, int>|null
     * }  $options
     * @return array{
     *     policy: SafetyStockPolicy,
     *     calculation: array<string, mixed>,
     *     approval_id: int
     * }
     */
    public function propose(string $itemCode, User $proposer, array $options = []): array
    {
        $cleanCode = trim($itemCode);
        if ($cleanCode === '') {
            throw new InvalidArgumentException('Kode item wajib diisi.');
        }

        $demandHistory = $this->resolveDemandHistory($cleanCode, $options['demand_history'] ?? null);
        $abcClass = $this->resolveAbcClass($cleanCode, $options);
        $supplierRef = $options['supplier_ref'] ?? $this->leadTimeStats->primarySupplierForItem($cleanCode);
        $fallbackLeadDays = max(1, (int) ($options['fallback_lead_time_days'] ?? 7));
        $echelonWeights = $options['echelon_weights'] ?? null;

        $calc = $this->algorithm->calculateWithSupplierStatistics(
            itemCode: $cleanCode,
            abcClass: $abcClass,
            demandHistory: $demandHistory,
            supplierRef: $supplierRef,
            fallbackLeadTimeDays: $fallbackLeadDays,
            echelonWeights: $echelonWeights,
        );

        return DB::transaction(function () use ($cleanCode, $proposer, $calc): array {
            $policy = SafetyStockPolicy::query()
                ->where('item_code', $cleanCode)
                ->lockForUpdate()
                ->first();

            if ($policy === null) {
                $policy = SafetyStockPolicy::create([
                    'item_code' => $cleanCode,
                    'abc_class' => $calc['abc_class'],
                    'xyz_class' => $calc['xyz_class'],
                    'service_level_bps' => $calc['service_level_bps'],
                    'safety_stock_qty' => 0,
                    'reorder_point_qty' => 0,
                    'slow_moving_flag' => false,
                    'dead_stock_flag' => false,
                    'status' => 'pending_approval',
                ]);
            }

            $approval = $this->approvalEngine->submit(
                approvalType: 'control_tower_safety_stock_policy',
                title: "Usulan Safety Stock {$cleanCode} ({$calc['abc_class']}{$calc['xyz_class']})",
                creator: $proposer,
                approvable: $policy,
                amount: $calc['safety_stock_qty'],
                steps: [['role' => 'logistics_admin']],
                slaHours: 48,
                metadata: [
                    'item_code' => $cleanCode,
                    'proposed_abc_class' => $calc['abc_class'],
                    'proposed_xyz_class' => $calc['xyz_class'],
                    'proposed_safety_stock_qty' => $calc['safety_stock_qty'],
                    'proposed_reorder_point_qty' => $calc['reorder_point_qty'],
                    'supplier_ref' => $calc['supplier_ref'],
                    'certificate_digest' => $calc['certificate']->digest,
                ],
            );

            $approvalId = (int) $approval->id;

            $policy->update([
                'status' => 'pending_approval',
                'proposed_abc_class' => $calc['abc_class'],
                'proposed_xyz_class' => $calc['xyz_class'],
                'proposed_service_level_bps' => $calc['service_level_bps'],
                'proposed_safety_stock_qty' => $calc['safety_stock_qty'],
                'proposed_reorder_point_qty' => $calc['reorder_point_qty'],
                'proposed_echelon_breakdown' => $calc['echelon_breakdown'],
                'supplier_ref' => $calc['supplier_ref'],
                'lead_time_mean_days' => $calc['lead_time_days'],
                'lead_time_sigma_days' => $calc['lead_time_sigma_days'],
                'certificate_digest' => $calc['certificate']->digest,
                'approval_id' => $approvalId,
                'proposed_by_user_id' => (int) $proposer->id,
                'decided_by_user_id' => null,
                'decided_at' => null,
                'decision_notes' => null,
            ]);

            return [
                'policy' => $policy->fresh(),
                'calculation' => $calc,
                'approval_id' => $approvalId,
            ];
        });
    }

    /**
     * Mengusulkan kebijakan untuk daftar SKU katalog sekaligus dengan pemeringkatan ABC Pareto.
     *
     * @param  list<array{item_code: string, annual_usage_value_idr: int, demand_history?: list<int>, supplier_ref?: string|null}>  $items
     * @return list<array{policy: SafetyStockPolicy, calculation: array<string, mixed>, approval_id: int}>
     */
    public function proposeCatalog(array $items, User $proposer): array
    {
        if ($items === []) {
            return [];
        }

        $abcMap = $this->algorithm->classifyAbcCatalog(array_map(
            static fn (array $item): array => [
                'item_code' => $item['item_code'],
                'annual_usage_value_idr' => (int) $item['annual_usage_value_idr'],
            ],
            $items,
        ));

        $results = [];
        foreach ($items as $item) {
            $results[] = $this->propose($item['item_code'], $proposer, [
                'abc_class' => $abcMap[$item['item_code']] ?? 'C',
                'demand_history' => $item['demand_history'] ?? null,
                'supplier_ref' => $item['supplier_ref'] ?? null,
            ]);
        }

        return $results;
    }

    /**
     * @param  list<int>|null  $explicitHistory
     * @return non-empty-list<int>
     */
    private function resolveDemandHistory(string $itemCode, ?array $explicitHistory): array
    {
        if ($explicitHistory !== null && $explicitHistory !== []) {
            return array_values(array_map('intval', $explicitHistory));
        }

        $forecasts = DemandForecast::query()
            ->where('item_code', $itemCode)
            ->orderBy('period')
            ->get(['actual_qty', 'forecast_qty']);

        if ($forecasts->isNotEmpty()) {
            $series = [];
            foreach ($forecasts as $fc) {
                $qty = $fc->actual_qty ?? $fc->forecast_qty;
                $series[] = max(0, (int) $qty);
            }

            if ($series !== []) {
                return $series;
            }
        }

        $totalOnHand = (int) EchelonStock::query()
            ->where('item_code', $itemCode)
            ->sum('on_hand_qty');

        return [max(1, $totalOnHand)];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveAbcClass(string $itemCode, array $options): string
    {
        if (isset($options['abc_class']) && is_string($options['abc_class'])) {
            $candidate = strtoupper(trim($options['abc_class']));
            if (in_array($candidate, ['A', 'B', 'C'], true)) {
                return $candidate;
            }
        }

        if (isset($options['annual_usage_value_idr'], $options['total_catalog_value_idr'])) {
            $annual = max(0, (int) $options['annual_usage_value_idr']);
            $total = max(0, (int) $options['total_catalog_value_idr']);
            if ($total > 0) {
                if ($annual === $total && $annual > 0) {
                    return 'A';
                }

                return $this->algorithm->classifyAbc(intdiv($annual * 10_000, $total));
            }
        }

        $existingNodeAbc = EchelonStock::query()
            ->where('item_code', $itemCode)
            ->value('abc_class');

        if (is_string($existingNodeAbc) && in_array($existingNodeAbc, ['A', 'B', 'C'], true)) {
            return $existingNodeAbc;
        }

        return 'B';
    }
}
