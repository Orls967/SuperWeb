<?php

declare(strict_types=1);

namespace Modules\Fashion\Application\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Fashion\Domain\Models\FashionBuyPlan;
use Modules\Fashion\Domain\Models\FashionCollection;
use Modules\Fashion\Domain\Models\FashionProductionAllocation;
use Modules\Fashion\Domain\Models\FashionProductionOrder;
use Modules\Fashion\Domain\Models\FashionSample;
use Modules\Shared\Domain\Algorithms\Apportionment\LargestRemainder;

class FashionSourcingService
{
    public function __construct(
        private readonly LargestRemainder $apportioner = new LargestRemainder
    ) {}

    public function createCollection(string $code, string $name, string $season, int $year): FashionCollection
    {
        return FashionCollection::create([
            'collection_code' => $code,
            'name' => $name,
            'season' => $season,
            'year' => $year,
            'status' => 'DRAFT',
        ]);
    }

    public function createSample(int $collectionId, string $sampleCode, string $articleName, ?string $techPackRef = null): FashionSample
    {
        return FashionSample::create([
            'collection_id' => $collectionId,
            'sample_code' => $sampleCode,
            'article_name' => $articleName,
            'tech_pack_ref' => $techPackRef,
            'approval_status' => 'PENDING',
        ]);
    }

    public function recordSampleReview(int $sampleId, string $decisionStatus): FashionSample
    {
        $status = strtoupper($decisionStatus);
        if (! in_array($status, ['APPROVED', 'REJECTED'], true)) {
            throw new InvalidArgumentException("Status review tidak valid: {$decisionStatus}");
        }

        $sample = FashionSample::findOrFail($sampleId);
        $sample->update([
            'approval_status' => $status,
            'approved_at' => $status === 'APPROVED' ? now() : null,
        ]);

        return $sample->fresh();
    }

    public function createBuyPlan(int $collectionId, string $planCode, int $targetUnits, int $budgetIdr): FashionBuyPlan
    {
        return FashionBuyPlan::create([
            'collection_id' => $collectionId,
            'plan_code' => $planCode,
            'target_total_units' => $targetUnits,
            'budget_idr' => $budgetIdr,
            'status' => 'OPEN',
        ]);
    }

    /**
     * @param  array<string, int>  $sizeWeights  size_code => weight
     * @param  list<string>  $colors
     */
    public function allocateAndIssueProductionOrder(
        int $buyPlanId,
        int $sampleId,
        string $poNumber,
        string $factoryName,
        int $totalUnits,
        int $unitCostIdr,
        array $sizeWeights,
        array $colors
    ): FashionProductionOrder {
        if ($totalUnits <= 0) {
            throw new InvalidArgumentException('Total unit harus bernilai positif.');
        }

        $sample = FashionSample::findOrFail($sampleId);
        if ($sample->approval_status !== 'APPROVED') {
            throw new DomainException("PO produksi diblokir: sampel {$sample->sample_code} belum berstatus APPROVED.");
        }

        $buyPlan = FashionBuyPlan::findOrFail($buyPlanId);
        $totalCostIdr = $totalUnits * $unitCostIdr;

        return DB::transaction(function () use (
            $buyPlan,
            $sample,
            $poNumber,
            $factoryName,
            $totalUnits,
            $totalCostIdr,
            $sizeWeights,
            $colors
        ) {
            $order = FashionProductionOrder::create([
                'buy_plan_id' => $buyPlan->id,
                'sample_id' => $sample->id,
                'po_number' => $poNumber,
                'factory_name' => $factoryName,
                'total_ordered_units' => $totalUnits,
                'total_cost_idr' => $totalCostIdr,
                'status' => 'ISSUED',
            ]);

            // Alokasikan unit ke setiap warna secara merata atau pembagian proporsional
            $colorCount = count($colors);
            if ($colorCount === 0) {
                $colors = ['DEFAULT'];
                $colorCount = 1;
            }

            $colorItems = [];
            foreach ($colors as $c) {
                $colorItems[] = ['key' => $c, 'weight' => 1];
            }
            $colorAllocations = $this->apportioner->apportion($totalUnits, $colorItems);

            // Alokasikan unit per warna ke setiap ukuran sesuai kurva ukuran Hamilton
            $sizeItems = [];
            foreach ($sizeWeights as $sz => $wt) {
                $sizeItems[] = ['key' => $sz, 'weight' => (int) $wt];
            }

            foreach ($colorAllocations as $color => $colorUnits) {
                if ($colorUnits === 0) {
                    continue;
                }
                $sizeAllocations = $this->apportioner->apportion($colorUnits, $sizeItems);

                foreach ($sizeAllocations as $sz => $units) {
                    FashionProductionAllocation::create([
                        'production_order_id' => $order->id,
                        'size_code' => $sz,
                        'color_code' => $color,
                        'allocated_units' => $units,
                    ]);
                }
            }

            return $order->load('allocations');
        });
    }
}
