<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use DomainException;
use Modules\Wms\Application\Actions\AllocateOutboundWavePickingAction;
use Modules\Wms\Domain\Algorithms\WaveBatchOptimizer;
use Modules\Wms\Domain\Services\PickWavePriorityScorer;
use Modules\Wms\Domain\Services\WaveReleaseReadinessChecker;

/**
 * Orkestrasi outbound dispatch: scoring order, pembentukan wave, readiness check, alokasi pick, & packing list.
 */
final class WmsOutboundDispatchWorkflowService
{
    public function __construct(
        private readonly PickWavePriorityScorer $scorer,
        private readonly WaveBatchOptimizer $batchOptimizer,
        private readonly WaveReleaseReadinessChecker $readinessChecker,
        private readonly AllocateOutboundWavePickingAction $allocateAction,
        private readonly PackingListGenerator $packingListGenerator,
    ) {}

    /**
     * @param  list<array{order_id: string, minutes_to_cutoff: int, is_express: bool, line_count: int, age_hours: int, total_weight_g: int}>  $orders
     * @param  list<array{product_id: int, qty_base: int, unit_cost_idr: int, available_qty: int, unit_weight_g: int, unit_volume_cm3: int}>  $lines
     * @return array{
     *     ranked_orders: list<array<string, mixed>>,
     *     waves: list<array<string, mixed>>,
     *     allocation: array<string, mixed>,
     *     packing_list: array<string, mixed>
     * }
     */
    public function dispatchWave(
        int $warehouseId,
        string $waveReference,
        array $orders,
        array $lines,
        bool $dockAssigned = true,
        bool $withinCarrierCutoff = true,
        ?int $actorUserId = null,
    ): array {
        $readiness = $this->readinessChecker->check(
            lines: array_map(static fn (array $l): array => [
                'product_id' => $l['product_id'],
                'requested_qty' => $l['qty_base'],
                'available_qty' => $l['available_qty'],
            ], $lines),
            dockAssigned: $dockAssigned,
            withinCarrierCutoff: $withinCarrierCutoff,
        );

        if (! $readiness['ready']) {
            throw new DomainException('Wave tidak siap rilis: '.implode(', ', $readiness['blocking_reasons']));
        }

        $ranked = $this->scorer->scoreAndRank($orders);
        $waves = $this->batchOptimizer->buildWaves(array_map(static fn (array $o): array => [
            'order_id' => $o['order_id'],
            'line_count' => $o['line_count'],
            'total_weight_g' => $o['total_weight_g'],
            'cutoff_minutes' => $o['minutes_to_cutoff'],
        ], $orders));

        $allocation = $this->allocateAction->execute(
            warehouseId: $warehouseId,
            waveReference: $waveReference,
            lines: array_map(static fn (array $l): array => [
                'product_id' => $l['product_id'],
                'qty_base' => $l['qty_base'],
                'unit_cost_idr' => $l['unit_cost_idr'],
            ], $lines),
            actorUserId: $actorUserId,
        );

        $packingList = $this->packingListGenerator->generate(array_map(static fn (array $l): array => [
            'product_id' => $l['product_id'],
            'qty' => $l['qty_base'],
            'unit_weight_g' => $l['unit_weight_g'],
            'unit_volume_cm3' => $l['unit_volume_cm3'],
        ], $lines));

        return [
            'ranked_orders' => $ranked,
            'waves' => $waves,
            'allocation' => $allocation,
            'packing_list' => $packingList,
        ];
    }
}
