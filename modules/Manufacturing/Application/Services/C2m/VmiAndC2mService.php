<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Services\C2m;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Manufacturing\Domain\Models\C2m\C2mBomNode;
use Modules\Manufacturing\Domain\Models\C2m\C2mCustomOrder;
use Modules\Manufacturing\Domain\Models\C2m\VmiReplenishment;

class VmiAndC2mService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 82.1 VMI trigger when WMS shelf stock hits reorder point
     */
    public function evaluateVmiTrigger(
        int $supplierId,
        string $sku,
        int $currentStock,
        int $reorderPoint,
        int $reorderQty,
        int $unitCostIdr,
        int $contractPlafondIdr = 50_000_000
    ): ?VmiReplenishment {
        if ($currentStock > $reorderPoint) {
            return null; // Above reorder point, no action needed
        }

        return DB::transaction(function () use ($supplierId, $sku, $currentStock, $reorderPoint, $reorderQty, $unitCostIdr, $contractPlafondIdr) {
            $today = date('Y-m-d');
            $idempotencyKey = "VMI:{$supplierId}:{$sku}:{$today}";

            $existing = VmiReplenishment::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing; // Idempotent check
            }

            $totalEstimated = $reorderQty * $unitCostIdr;
            $status = $totalEstimated <= $contractPlafondIdr ? 'AUTO_ORDERED' : 'PENDING_APPROVAL';

            // Deterministic code generation (A4 compliant)
            $codeEntropy = hash('sha256', "{$idempotencyKey}:{$totalEstimated}");
            $vmiCode = 'VMI-'.strtoupper(substr($codeEntropy, 0, 12));

            $replenishment = VmiReplenishment::create([
                'vmi_code' => $vmiCode,
                'supplier_id' => $supplierId,
                'sku' => $sku,
                'current_shelf_stock' => $currentStock,
                'reorder_point' => $reorderPoint,
                'suggested_reorder_qty' => $reorderQty,
                'total_estimated_idr' => $totalEstimated,
                'contract_plafond_idr' => $contractPlafondIdr,
                'status' => $status,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Budget commitment in ledger
            if ($status === 'AUTO_ORDERED') {
                $this->ledgerService->post(new PostingDTO(
                    type: 'VMI_PURCHASE_COMMITMENT',
                    description: "VMI auto PO commitment for {$sku} qty {$reorderQty}",
                    idempotencyKey: "VMI-COMM-{$replenishment->vmi_code}",
                    entries: [
                        PostingEntryDTO::forCode('mfg:vmi_commitment:IDR', 'IDR', $totalEstimated),
                        PostingEntryDTO::forCode('mfg:vmi_budget_encumbrance:IDR', 'IDR', -$totalEstimated),
                    ],
                    referenceType: 'VMI_REPLENISHMENT',
                    referenceId: (string) $replenishment->id,
                ));
            }

            return $replenishment;
        });
    }

    /**
     * Approve VMI replenishment above contract plafond
     */
    public function approveVmiReplenishment(VmiReplenishment $replenishment): VmiReplenishment
    {
        if ($replenishment->status === 'APPROVED' || $replenishment->status === 'AUTO_ORDERED') {
            return $replenishment;
        }

        return DB::transaction(function () use ($replenishment) {
            $replenishment->update(['status' => 'APPROVED']);

            $this->ledgerService->post(new PostingDTO(
                type: 'VMI_PURCHASE_COMMITMENT',
                description: "VMI manual approved commitment for {$replenishment->sku} qty {$replenishment->suggested_reorder_qty}",
                idempotencyKey: "VMI-COMM-{$replenishment->vmi_code}",
                entries: [
                    PostingEntryDTO::forCode('mfg:vmi_commitment:IDR', 'IDR', $replenishment->total_estimated_idr),
                    PostingEntryDTO::forCode('mfg:vmi_budget_encumbrance:IDR', 'IDR', -$replenishment->total_estimated_idr),
                ],
                referenceType: 'VMI_REPLENISHMENT',
                referenceId: (string) $replenishment->id,
            ));

            return $replenishment->fresh();
        });
    }

    /**
     * 82.3 & 82.4 Validate C2M Parametric Design, detect BOM cycles, roll-up BOM cost and calculate price
     *
     * @param  array<string, int>  $bomItemsCost  raw material costs
     * @param  array<int, array{code: string, parent: ?string, name: string, material: string, qty: int, unit_cost_idr: int}>|null  $bomHierarchy  tree structure for anti-cycle validation
     */
    public function configureAndQuoteC2m(
        int $userId,
        string $baseModel,
        array $parameters,
        array $bomItemsCost,
        ?array $bomHierarchy = null
    ): C2mCustomOrder {
        // Validate parametric constraints (dimensions within engineering tolerances)
        $sizeMm = $parameters['size_mm'] ?? 100;
        if ($sizeMm <= 0 || $sizeMm > 3000) {
            throw new InvalidArgumentException('Dimensions exceed engineering tolerances (1mm - 3000mm)');
        }

        // 82.7 (c) Cycle detection in BOM structure
        if ($bomHierarchy !== null && count($bomHierarchy) > 0) {
            $this->assertNoBomCycles($bomHierarchy);
        }

        $baseBomCost = array_sum($bomItemsCost);
        if ($baseBomCost <= 0) {
            throw new InvalidArgumentException('BOM cost cannot be zero');
        }

        // Complexity factor based on finishing and tolerances
        $complexity = 1.0;
        if (($parameters['finish'] ?? 'STANDARD') === 'TITANIUM_COATED') {
            $complexity += 0.35;
        }
        if (($parameters['tolerance'] ?? 'STANDARD') === 'AEROSPACE_PRECISION') {
            $complexity += 0.25;
        }

        $totalBomWithComplexity = (int) round($baseBomCost * $complexity);
        $finalPrice = (int) round($totalBomWithComplexity * 1.30); // 30% margin

        // Deterministic code generation (A4 compliant)
        $c2mEntropy = hash('sha256', "{$userId}:{$baseModel}:".json_encode($parameters).":{$baseBomCost}");
        $c2mCode = 'C2M-'.strtoupper(substr($c2mEntropy, 0, 12));

        $order = C2mCustomOrder::create([
            'c2m_code' => $c2mCode,
            'user_id' => $userId,
            'base_model' => $baseModel,
            'parameters' => $parameters,
            'complexity_factor' => $complexity,
            'rolled_up_bom_cost_idr' => $totalBomWithComplexity,
            'final_price_idr' => $finalPrice,
            'status' => 'DESIGN_VALIDATED',
            'estimated_lead_days' => max(3, (int) round(5 * $complexity)),
        ]);

        // Save BOM nodes if hierarchy provided
        if ($bomHierarchy !== null) {
            foreach ($bomHierarchy as $node) {
                $qty = $node['qty'] ?? 1;
                $unitCost = $node['unit_cost_idr'] ?? 0;
                C2mBomNode::create([
                    'c2m_custom_order_id' => $order->id,
                    'node_code' => $node['code'],
                    'parent_node_code' => $node['parent'] ?? null,
                    'part_name' => $node['name'],
                    'material' => $node['material'],
                    'quantity' => $qty,
                    'unit_cost_idr' => $unitCost,
                    'subtotal_cost_idr' => $qty * $unitCost,
                ]);
            }
        }

        return $order;
    }

    /**
     * Cycle detection DFS algorithm
     *
     * @param  array<int, array{code: string, parent: ?string}>  $hierarchy
     */
    public function assertNoBomCycles(array $hierarchy): void
    {
        $childrenMap = [];
        $nodes = [];

        foreach ($hierarchy as $node) {
            $code = $node['code'];
            $parent = $node['parent'] ?? null;
            $nodes[$code] = true;
            if ($parent !== null) {
                $childrenMap[$parent][] = $code;
            }
        }

        $visited = [];
        $recursionStack = [];

        $dfs = function (string $current) use (&$dfs, &$visited, &$recursionStack, $childrenMap): void {
            $visited[$current] = true;
            $recursionStack[$current] = true;

            if (isset($childrenMap[$current])) {
                foreach ($childrenMap[$current] as $child) {
                    if (! isset($visited[$child])) {
                        $dfs($child);
                    } elseif (! empty($recursionStack[$child])) {
                        throw new InvalidArgumentException("Circular dependency detected in BOM structure at node {$child}");
                    }
                }
            }

            $recursionStack[$current] = false;
        };

        foreach (array_keys($nodes) as $nodeCode) {
            if (! isset($visited[$nodeCode])) {
                $dfs($nodeCode);
            }
        }
    }

    /**
     * Issue factory SPK work order for C2M
     */
    public function issueFactorySpk(C2mCustomOrder $order): C2mCustomOrder
    {
        $spkEntropy = hash('sha256', "{$order->c2m_code}:SPK:{$order->id}");
        $spkNo = 'SPK-C2M-'.date('Ymd').'-'.strtoupper(substr($spkEntropy, 0, 8));

        $order->update([
            'spk_number' => $spkNo,
            'status' => 'SPK_ISSUED',
        ]);

        return $order;
    }

    /**
     * 82.5 Cancel C2M order with material cost penalty if already in production
     */
    public function cancelC2mOrder(C2mCustomOrder $order): C2mCustomOrder
    {
        if (in_array($order->status, ['SPK_ISSUED', 'IN_PRODUCTION'], true)) {
            $order->update([
                'status' => 'CANCELLED_WITH_MATERIAL_PENALTY',
            ]);

            return $order;
        }

        $order->update(['status' => 'CANCELLED']);

        return $order;
    }

    /**
     * 82.6 VMI and C2M Analytics / Dashboard summaries
     */
    public function getVmiDashboardSummary(): array
    {
        $totalVmi = VmiReplenishment::count();
        $autoOrdered = VmiReplenishment::where('status', 'AUTO_ORDERED')->count();
        $pendingApproval = VmiReplenishment::where('status', 'PENDING_APPROVAL')->count();
        $totalEstimatedIdr = (int) (VmiReplenishment::where('status', 'AUTO_ORDERED')->sum('total_estimated_idr') ?? 0);

        return [
            'total_replenishments' => $totalVmi,
            'auto_ordered_count' => $autoOrdered,
            'pending_approval_count' => $pendingApproval,
            'total_auto_ordered_idr' => $totalEstimatedIdr,
            'vmi_fill_rate_percent' => $totalVmi > 0 ? round(($autoOrdered / $totalVmi) * 100, 1) : 100.0,
        ];
    }

    public function getC2mProductionBoard(): array
    {
        $orders = C2mCustomOrder::all();
        $byStatus = [
            'DESIGN_VALIDATED' => $orders->where('status', 'DESIGN_VALIDATED')->count(),
            'SPK_ISSUED' => $orders->where('status', 'SPK_ISSUED')->count(),
            'IN_PRODUCTION' => $orders->where('status', 'IN_PRODUCTION')->count(),
            'QC_PASSED' => $orders->where('status', 'QC_PASSED')->count(),
            'SHIPPED' => $orders->where('status', 'SHIPPED')->count(),
        ];

        return [
            'total_orders' => $orders->count(),
            'by_status' => $byStatus,
            'pipeline_value_idr' => (int) $orders->sum('final_price_idr'),
            'average_lead_days' => $orders->count() > 0 ? (int) round($orders->avg('estimated_lead_days')) : 5,
        ];
    }
}
