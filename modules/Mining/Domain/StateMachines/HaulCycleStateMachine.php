<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\StateMachines;

use Modules\Core\Contracts\ClockInterface;
use Modules\Mining\Domain\Enums\HaulCycleStatus;
use Modules\Mining\Domain\Exceptions\InvalidMiningStateTransitionException;
use Modules\Mining\Domain\Models\MiningHaulCycle;
use Modules\Mining\Domain\Models\MiningHaulCycleHistory;

/**
 * State Machine Siklus Angkut Truk Tambang (D93-2).
 * Mengawal 8 status: queued -> assigned -> travelling_empty -> loading -> hauling -> dumping -> completed -> aborted.
 */
final class HaulCycleStateMachine
{
    public function __construct(
        private readonly ?ClockInterface $clock = null
    ) {}

    public function transition(MiningHaulCycle $cycle, HaulCycleStatus $targetStatus, ?string $actorId = null, ?string $notes = null): void
    {
        $currentStatus = $this->resolveCurrentStatus($cycle);

        if ($currentStatus === $targetStatus) {
            return;
        }

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningHaulCycle',
                $currentStatus->value,
                $targetStatus->value,
                "Siklus angkut truk berstatus {$currentStatus->value} tidak dapat beralih ke {$targetStatus->value}"
            );
        }

        $this->assertGuard($cycle, $currentStatus, $targetStatus);

        $cycle->update([
            'status' => $targetStatus->value,
        ]);

        $clock = $this->clock ?? app(ClockInterface::class);

        MiningHaulCycleHistory::create([
            'haul_cycle_id' => $cycle->id,
            'from_status' => $currentStatus->value,
            'to_status' => $targetStatus->value,
            'actor_id' => $actorId ?? 'system',
            'notes' => $notes,
            'recorded_at' => $clock->now(),
        ]);
    }

    public function recordInitialAssignment(MiningHaulCycle $cycle, ?string $actorId = null, ?string $notes = null): void
    {
        $clock = $this->clock ?? app(ClockInterface::class);
        $currentStatus = $this->resolveCurrentStatus($cycle);

        MiningHaulCycleHistory::create([
            'haul_cycle_id' => $cycle->id,
            'from_status' => null,
            'to_status' => $currentStatus->value,
            'actor_id' => $actorId ?? 'system',
            'notes' => $notes ?? 'Penugasan awal siklus angkut truk',
            'recorded_at' => $clock->now(),
        ]);
    }

    public function resolveCurrentStatus(MiningHaulCycle $cycle): HaulCycleStatus
    {
        return $cycle->status instanceof HaulCycleStatus
            ? $cycle->status
            : (HaulCycleStatus::tryFrom((string) $cycle->status) ?? HaulCycleStatus::QUEUED);
    }

    private function assertGuard(MiningHaulCycle $cycle, HaulCycleStatus $from, HaulCycleStatus $to): void
    {
        if ($to === HaulCycleStatus::COMPLETED && (int) $cycle->target_payload_kg <= 0) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningHaulCycle',
                $from->value,
                $to->value,
                'Siklus angkut tidak dapat diselesaikan tanpa target muatan valid'
            );
        }
    }
}
