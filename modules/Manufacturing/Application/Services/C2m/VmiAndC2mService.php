<?php

namespace Modules\Manufacturing\Application\Services\C2m;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
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

            $replenishment = VmiReplenishment::create([
                'vmi_code' => 'VMI-'.strtoupper(bin2hex(random_bytes(6))),
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
     * 82.3 & 82.4 Validate C2M Parametric Design, roll-up BOM cost and calculate price
     */
    public function configureAndQuoteC2m(
        int $userId,
        string $baseModel,
        array $parameters,
        array $bomItemsCost // associative array of raw materials: ['CARBON_FIBER_SHEET' => 800000, 'RESIN' => 200000, ...]
    ): C2mCustomOrder {
        // Validate parametric constraints (no cycle, within size tolerances)
        $sizeMm = $parameters['size_mm'] ?? 100;
        if ($sizeMm <= 0 || $sizeMm > 3000) {
            throw new \InvalidArgumentException('Dimensions exceed engineering tolerances (1mm - 3000mm)');
        }

        $baseBomCost = array_sum($bomItemsCost);
        if ($baseBomCost <= 0) {
            throw new \InvalidArgumentException('BOM cost cannot be zero');
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

        return C2mCustomOrder::create([
            'c2m_code' => 'C2M-'.strtoupper(bin2hex(random_bytes(6))),
            'user_id' => $userId,
            'base_model' => $baseModel,
            'parameters' => $parameters,
            'complexity_factor' => $complexity,
            'rolled_up_bom_cost_idr' => $totalBomWithComplexity,
            'final_price_idr' => $finalPrice,
            'status' => 'DESIGN_VALIDATED',
            'estimated_lead_days' => max(3, (int) round(5 * $complexity)),
        ]);
    }

    /**
     * Issue factory SPK work order for C2M
     */
    public function issueFactorySpk(C2mCustomOrder $order): C2mCustomOrder
    {
        $spkNo = 'SPK-C2M-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));

        $order->update([
            'spk_number' => $spkNo,
            'status' => 'SPK_ISSUED',
        ]);

        return $order;
    }
}
