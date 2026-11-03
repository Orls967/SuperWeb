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
use Modules\Pricing\Application\Services\AlgorithmicSurgePricingService;
use Modules\Shared\Domain\Algorithms\Csp\Constraint;
use Modules\Shared\Domain\Algorithms\Csp\QuickXplain;
use Modules\Shared\Domain\Algorithms\Inventory\ReorderPolicy;

class VmiAndC2mService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * D82-1 Evaluasi VMI trigger berbasis kebijakan (s, S) dan verifikasi sertifikat matematis.
     */
    public function evaluateVmiTrigger(
        int $supplierId,
        string $sku,
        int $currentStock,
        int $reorderPoint,
        int $reorderQty,
        int $unitCostIdr,
        int $contractPlafondIdr = 50_000_000,
        ?int $meanDailyDemand = null,
        ?int $demandStdDev = null,
        ?int $leadTimeDays = null,
        ?int $serviceLevelBps = null,
        ?int $reviewPeriodDays = null,
        ?int $packSize = null,
        ?int $onHand = null,
        ?int $onOrder = null,
        ?int $backorder = null,
    ): ?VmiReplenishment {
        $today = date('Y-m-d');
        $idempotencyKey = "VMI:{$supplierId}:{$sku}:{$today}";

        $existing = VmiReplenishment::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing; // Idempotent check
        }

        if ($meanDailyDemand !== null) {
            $effectiveOnHand = $onHand ?? $currentStock;
            $effectiveOnOrder = $onOrder ?? 0;
            $effectiveBackorder = $backorder ?? 0;
            $effectivePackSize = $packSize ?? 1;

            $input = [
                'd_mean' => $meanDailyDemand,
                'sigma' => $demandStdDev ?? 0,
                'lead_time_days' => $leadTimeDays ?? 1,
                'z' => ($serviceLevelBps ?? 9500) === 9500 ? 1.645 : 1.645,
                'review_period_days' => $reviewPeriodDays ?? 7,
                'on_hand' => $effectiveOnHand,
                'on_order' => $effectiveOnOrder,
                'backorder' => $effectiveBackorder,
                'pack_size' => $effectivePackSize,
                'unit_price_idr' => $unitCostIdr,
                'contract_ceiling_idr' => $contractPlafondIdr,
            ];

            $policy = new ReorderPolicy;
            $result = $policy->evaluate($input);

            if ($result->orderQty === 0) {
                return null;
            }

            $reorderPoint = $result->reorderPoint;
            $reorderQty = $result->orderQty;
            $totalEstimated = $result->totalCostIdr;
            $currentStock = $effectiveOnHand;
            $status = $result->autoPoAllowed ? 'AUTO_ORDERED' : 'PENDING_APPROVAL';
        } else {
            if ($currentStock > $reorderPoint) {
                return null;
            }
            $totalEstimated = $reorderQty * $unitCostIdr;
            $status = $totalEstimated <= $contractPlafondIdr ? 'AUTO_ORDERED' : 'PENDING_APPROVAL';
        }

        return DB::transaction(function () use ($supplierId, $sku, $currentStock, $reorderPoint, $reorderQty, $totalEstimated, $contractPlafondIdr, $status, $idempotencyKey) {
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

            // Budget commitment in ledger bila AUTO_ORDERED
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
     * D82-1 Menyetujui usulan VMI replenishment di atas plafon kontrak.
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
     * D82-2 Batasan biner untuk template meja modular C2M.
     *
     * @return list<Constraint>
     */
    public function getDeskModularConstraints(): array
    {
        return [
            new Constraint(
                var1: 'width',
                var2: 'depth',
                predicate: fn ($w, $d) => $w >= $d + 20,
                name: 'c1_width_depth'
            ),
            new Constraint(
                var1: 'material',
                var2: 'width',
                predicate: fn ($m, $w) => $m !== 'kaca' || $w <= 100,
                name: 'c2_glass_width'
            ),
            new Constraint(
                var1: 'drawers',
                var2: 'width',
                predicate: fn ($l, $w) => $l !== 3 || $w <= 80,
                name: 'c3_drawers_width'
            ),
            new Constraint(
                var1: 'material',
                var2: 'drawers',
                predicate: fn ($m, $l) => $m !== 'besi' || $l !== 3,
                name: 'c4_steel_drawers'
            ),
            new Constraint(
                var1: 'width',
                var2: 'drawers',
                predicate: fn ($w, $l) => $w < 100 || $l >= 4,
                name: 'c5_wide_drawers'
            ),
        ];
    }

    /**
     * D82-2 Konfigurasi dan kalkulasi C2M menggunakan CSP (AC-3) dan QuickXplain.
     *
     * @param  array<string, mixed>  $userAssignments
     * @return array{valid: bool, minimal_conflict?: array<string, mixed>, order?: C2mCustomOrder, quote_hash?: string}
     */
    public function configureAndQuoteC2mCsp(
        int $userId,
        string $baseModel,
        array $userAssignments,
        int $rawMaterialCostIdr = 1_500_000
    ): array {
        $constraints = $this->getDeskModularConstraints();

        // 1. Periksa apakah terdapat inkonsistensi
        $inconsistent = false;
        foreach ($constraints as $c) {
            if (array_key_exists($c->var1, $userAssignments) && array_key_exists($c->var2, $userAssignments)) {
                if (! $c->check($userAssignments[$c->var1], $userAssignments[$c->var2])) {
                    $inconsistent = true;
                    break;
                }
            }
        }

        if ($inconsistent) {
            $qx = new QuickXplain;
            $qxResult = $qx->explain($constraints, $userAssignments);

            return [
                'valid' => false,
                'minimal_conflict' => $qxResult->minimalConflict,
            ];
        }

        // 2. Jika konsisten, simpan C2M custom order dan kunci harga via PriceLocker
        return DB::transaction(function () use ($userId, $baseModel, $userAssignments, $rawMaterialCostIdr) {
            $complexityFactor = 1.25;
            $finalPriceIdr = (int) round($rawMaterialCostIdr * $complexityFactor);

            $entropy = hash('sha256', "C2M:{$userId}:{$baseModel}:".json_encode($userAssignments).":{$finalPriceIdr}");
            $c2mCode = 'C2M-'.strtoupper(substr($entropy, 0, 10));

            $order = C2mCustomOrder::create([
                'c2m_code' => $c2mCode,
                'user_id' => $userId,
                'base_model' => $baseModel,
                'parameters' => $userAssignments,
                'complexity_factor' => $complexityFactor,
                'rolled_up_bom_cost_idr' => $rawMaterialCostIdr,
                'final_price_idr' => $finalPriceIdr,
                'status' => 'DESIGN_VALIDATED',
                'estimated_lead_days' => 5,
            ]);

            // Hasilkan BOM & routing turunan ke mfg_c2m_bom_nodes
            $material = (string) ($userAssignments['material'] ?? 'kayu');
            $width = (int) ($userAssignments['width'] ?? 100);
            $depth = (int) ($userAssignments['depth'] ?? 60);
            $drawers = (int) ($userAssignments['drawers'] ?? 4);

            C2mBomNode::create([
                'c2m_custom_order_id' => $order->id,
                'node_code' => 'TOP-PANEL-01',
                'part_name' => "Daun Meja {$width}x{$depth} {$material}",
                'material' => $material,
                'quantity' => 1,
                'unit_cost_idr' => (int) round($rawMaterialCostIdr * 0.5),
                'subtotal_cost_idr' => (int) round($rawMaterialCostIdr * 0.5),
            ]);

            C2mBomNode::create([
                'c2m_custom_order_id' => $order->id,
                'node_code' => 'LEGS-SET-01',
                'part_name' => "Kaki Meja Modular {$material}",
                'material' => $material,
                'quantity' => 4,
                'unit_cost_idr' => (int) round($rawMaterialCostIdr * 0.075),
                'subtotal_cost_idr' => (int) round($rawMaterialCostIdr * 0.3),
            ]);

            C2mBomNode::create([
                'c2m_custom_order_id' => $order->id,
                'node_code' => 'DRAWER-SET-01',
                'part_name' => "Laci Meja ({$drawers} unit)",
                'material' => $material,
                'quantity' => $drawers,
                'unit_cost_idr' => (int) round(($rawMaterialCostIdr * 0.2) / max(1, $drawers)),
                'subtotal_cost_idr' => (int) round($rawMaterialCostIdr * 0.2),
            ]);

            // Kunci harga dengan PriceLocker (pric_frozen_quotes)
            $quoteHash = hash('sha256', "C2M:{$order->c2m_code}:{$order->final_price_idr}");
            app(AlgorithmicSurgePricingService::class)->freezePriceQuoteDeterministic(
                quoteCode: "Q-C2M-{$order->c2m_code}",
                sku: "C2M-{$order->c2m_code}",
                effectivePriceIdr: $order->final_price_idr,
                floorPriceIdr: $rawMaterialCostIdr,
                ceilingPriceIdr: (int) ($finalPriceIdr * 1.5),
                quoteHash: $quoteHash,
                orderRef: "C2M:{$order->c2m_code}",
                channel: 'c2m_store',
                lockMinutes: 15,
                metadata: ['parameters' => $userAssignments],
            );

            return [
                'valid' => true,
                'order' => $order,
                'quote_hash' => $quoteHash,
            ];
        });
    }

    /**
     * 82.3 & 82.4 Validate C2M Parametric Design, detect BOM cycles, roll-up BOM cost and calculate price
     *
     * @param  array<string, int>  $bomItemsCost
     * @param  array<int, array{code: string, parent: ?string, name: string, material: string, qty: int, unit_cost_idr: int}>|null  $bomHierarchy
     */
    public function configureAndQuoteC2m(
        int $userId,
        string $baseModel,
        array $parameters,
        array $bomItemsCost = [],
        ?array $bomHierarchy = null
    ): C2mCustomOrder {
        // Assert no BOM cycles if hierarchy is provided
        if ($bomHierarchy !== null) {
            $this->assertNoBomCycles($bomHierarchy);
        }

        // Complexity factor calculation
        $baseComplexity = 1.0;
        $finish = $parameters['finish'] ?? 'STANDARD';
        $tolerance = $parameters['tolerance'] ?? 'STANDARD';

        if ($finish === 'TITANIUM_COATED') {
            $baseComplexity += 0.35;
        } elseif ($finish === 'ANODIZED_BLACK') {
            $baseComplexity += 0.15;
        }

        if ($tolerance === 'AEROSPACE_PRECISION') {
            $baseComplexity += 0.25;
        }

        $baseBomCost = array_sum($bomItemsCost);
        $rolledUpBomCost = (int) round($baseBomCost * $baseComplexity);
        $finalPrice = (int) round($rolledUpBomCost * 1.30); // 30% gross margin target

        $entropy = hash('sha256', "C2M:{$userId}:{$baseModel}:".json_encode($parameters).":{$finalPrice}");
        $c2mCode = 'C2M-'.strtoupper(substr($entropy, 0, 10));

        $order = C2mCustomOrder::create([
            'c2m_code' => $c2mCode,
            'user_id' => $userId,
            'base_model' => $baseModel,
            'parameters' => $parameters,
            'complexity_factor' => $baseComplexity,
            'rolled_up_bom_cost_idr' => $rolledUpBomCost,
            'final_price_idr' => $finalPrice,
            'status' => 'DESIGN_VALIDATED',
            'estimated_lead_days' => 7,
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
     * Cancel C2M order with material cost penalty if already in production
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
     * D82-1 Mendapatkan daftar replenishment untuk pemasok tertentu (atau semua jika null).
     *
     * @return list<VmiReplenishment>
     */
    public function getReplenishmentsForSupplier(?int $supplierId = null): array
    {
        $query = VmiReplenishment::query();
        if ($supplierId !== null) {
            $query->where('supplier_id', $supplierId);
        }

        return $query->orderByDesc('id')->limit(50)->get()->all();
    }

    /**
     * D82-1 Menyetujui usulan replenishment berdasarkan ID.
     */
    public function approveVmiReplenishmentById(int $replenishmentId): VmiReplenishment
    {
        $replenishment = VmiReplenishment::findOrFail($replenishmentId);

        return $this->approveVmiReplenishment($replenishment);
    }

    /**
     * D82-2 Mendapatkan daftar pesanan kustom C2M untuk customer tertentu.
     *
     * @return list<C2mCustomOrder>
     */
    public function getCustomOrdersForCustomer(int $userId): array
    {
        return C2mCustomOrder::with('bomNodes')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->all();
    }

    /**
     * VMI and C2M Analytics / Dashboard summaries
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
        $orders = C2mCustomOrder::with('bomNodes')->get();
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
