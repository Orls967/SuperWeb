<?php

declare(strict_types=1);

use Modules\Wms\Application\Services\CrossWarehouseRebalancer;
use Modules\Wms\Application\Services\PackingListGenerator;
use Modules\Wms\Application\Services\ReceivingToleranceEvaluator;
use Modules\Wms\Application\Services\SlottingScenarioPlanner;
use Modules\Wms\Domain\Algorithms\AbcVelocityClassifier;
use Modules\Wms\Domain\Algorithms\CycleCountScheduler;
use Modules\Wms\Domain\Algorithms\FefoLotAllocator;
use Modules\Wms\Domain\Algorithms\PickPathPlanner;
use Modules\Wms\Domain\Algorithms\PutawaySlotter;
use Modules\Wms\Domain\Algorithms\ReplenishmentMinMaxCalculator;
use Modules\Wms\Domain\Algorithms\WaveBatchOptimizer;
use Modules\Wms\Domain\Services\BinCapacityEvaluator;
use Modules\Wms\Domain\Services\LaborProductivityEstimator;
use Modules\Wms\Domain\Services\PalletizationPlanner;
use Modules\Wms\Domain\Services\ReturnGoodsInspectionService;
use Modules\Wms\Domain\Support\QuantityNormalizer;
use Modules\Wms\Domain\ValueObjects\BinCoordinate;
use Modules\Wms\Domain\ValueObjects\ThreePlRateCard;

it('normalizes quantities to integer base units without float', function () {
    expect(QuantityNormalizer::toBaseUnits(2, 'box'))->toBe(24);
    expect(QuantityNormalizer::toBaseUnits(3, 'pallet'))->toBe(1440);
    expect(QuantityNormalizer::fromDecimalString('12.000000'))->toBe(12);
    expect(QuantityNormalizer::computeValueIdr(15, 25000))->toBe(375000);
    expect(QuantityNormalizer::varianceBps(100, 105))->toBe(500);
});

it('throws NO_CAPACITY and INVALID_QUANTITY on PutawaySlotter edge cases', function () {
    $slotter = new PutawaySlotter;

    expect(fn () => $slotter->plan(0, 'A', [['id' => 'B1', 'distance' => 10, 'freeCapacity' => 20]]))
        ->toThrow(DomainException::class, 'INVALID_QUANTITY');

    expect(fn () => $slotter->plan(100, 'A', [['id' => 'B1', 'distance' => 10, 'freeCapacity' => 20]]))
        ->toThrow(DomainException::class, 'NO_CAPACITY');
});

it('throws EMPTY_PICK_LIST and tie-breaks S_SHAPE in PickPathPlanner', function () {
    $planner = new PickPathPlanner;

    expect(fn () => $planner->plan(10, 100, []))->toThrow(DomainException::class, 'EMPTY_PICK_LIST');

    $singleAisle = $planner->plan(10, 100, [['aisle' => 1, 'pos' => 25]]);
    expect($singleAisle['strategy'])->toBe('S_SHAPE');
    expect($singleAisle['total_distance'])->toBe(50);
});

it('evaluates receiving tolerance bps and slotting planner', function () {
    $eval = new ReceivingToleranceEvaluator;
    expect($eval->evaluate(100, 104)['decision'])->toBe('within_tolerance');
    expect($eval->evaluate(100, 110)['decision'])->toBe('over_receipt');
    expect($eval->evaluate(100, 90)['decision'])->toBe('short_receipt');

    $planner = new SlottingScenarioPlanner(new PutawaySlotter);
    $reloc = $planner->simulateRelocation(
        [['sku' => 'SKU-1', 'abcClass' => 'A', 'qty' => 10, 'currentDistance' => 80]],
        [['id' => 'BIN-1', 'distance' => 10, 'freeCapacity' => 20]]
    );
    expect($reloc['total_distance_saved'])->toBe(700);
});

it('runs domain value objects and logistics helpers deterministically', function () {
    $coordA = new BinCoordinate('STO', 1, 2, 1, 10);
    $coordB = new BinCoordinate('STO', 3, 1, 2, 20);
    expect($coordA->manhattanDistanceTo($coordB))->toBe(32);

    $rate = new ThreePlRateCard('CLIENT-1', 5000, 500, 750, 100000);
    expect($rate->computeTotalFeeIdr(10, 10, 10))->toBe(100000);
    expect($rate->computeTotalFeeIdr(30, 20, 20))->toBe(175000);

    $abc = (new AbcVelocityClassifier)->classify([
        ['product_id' => 1, 'annual_value_idr' => 800000],
        ['product_id' => 2, 'annual_value_idr' => 150000],
        ['product_id' => 3, 'annual_value_idr' => 50000],
    ]);
    expect($abc[0]['abc_class'])->toBe('A');
    expect($abc[2]['abc_class'])->toBe('C');

    $fefo = (new FefoLotAllocator)->allocate(15, [
        ['bin_id' => 'B2', 'lot_number' => 'L2', 'expiry_epoch' => 2000, 'available_qty' => 10, 'status' => 'available'],
        ['bin_id' => 'B1', 'lot_number' => 'L1', 'expiry_epoch' => 1000, 'available_qty' => 10, 'status' => 'available'],
    ]);
    expect($fefo[0]['lot_number'])->toBe('L1');

    expect((new ReplenishmentMinMaxCalculator)->evaluate(10, 5, 2, 2, 7)['suggested_replenish_qty'])->toBe(35);
    expect((new WaveBatchOptimizer)->buildWaves([
        ['order_id' => 'O1', 'line_count' => 30, 'total_weight_g' => 10000, 'cutoff_minutes' => 60],
        ['order_id' => 'O2', 'line_count' => 30, 'total_weight_g' => 10000, 'cutoff_minutes' => 60],
    ]))->toHaveCount(2);
    expect((new BinCapacityEvaluator)->evaluateFit(5, 20, 5000, 50000, 1000, 20000, 5, 1000, 500)['fits'])->toBeTrue();
    expect((new PalletizationPlanner)->plan(100, 5000, 200)['pallets_required'])->toBeGreaterThan(0);
    expect((new ReturnGoodsInspectionService)->inspect(5, false, false, 90)['disposition'])->toBe('quarantine_rework');
    expect((new LaborProductivityEstimator)->estimate(['pick' => 60])['total_minutes'])->toBe(35);
    expect((new PackingListGenerator)->generate([['product_id' => 1, 'qty' => 10, 'unit_weight_g' => 500, 'unit_volume_cm3' => 100]])['carton_count'])->toBe(1);
    expect((new CrossWarehouseRebalancer)->proposeTransfers([
        ['warehouse_code' => 'DC1', 'product_id' => 1, 'available_qty' => 100, 'min_qty' => 10, 'max_qty' => 50],
        ['warehouse_code' => 'DC2', 'product_id' => 1, 'available_qty' => 0, 'min_qty' => 20, 'max_qty' => 50],
    ]))->toHaveCount(1);
    expect((new CycleCountScheduler)->schedule(100, [['id' => 'X', 'abcClass' => 'A', 'lastCountedDay' => 50]], 1)['selected'])->toHaveCount(1);
});
