<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\ControlTower\Domain\Models\SopCycle;
use Modules\ControlTower\Domain\Models\SopEvent;
use Modules\ControlTower\Domain\StateMachines\SopCycleStateMachine;
use Modules\Core\Contracts\ClockInterface;

final class TransitionSopCycleAction
{
    public function __construct(
        private readonly SopCycleStateMachine $stateMachine,
        private readonly ClockInterface $clock,
    ) {}

    public function execute(
        SopCycle $cycle,
        string $toStatus,
        ?int $actorUserId = null,
        ?string $notes = null,
    ): SopCycle {
        return DB::transaction(function () use ($cycle, $toStatus, $actorUserId, $notes): SopCycle {
            $from = $cycle->status;
            $this->stateMachine->assertTransition($from, $toStatus);

            $updates = ['status' => $toStatus];
            if ($toStatus === 'executive_signoff' || $toStatus === 'published') {
                $updates['signed_off_by_user_id'] = $actorUserId;
                $updates['signed_off_at'] = $this->clock->now();
            }

            $cycle->update($updates);

            SopEvent::create([
                'sop_cycle_id' => $cycle->id,
                'from_status' => $from,
                'to_status' => $toStatus,
                'actor_user_id' => $actorUserId,
                'notes' => $notes ?? "Transisi {$from} -> {$toStatus}",
                'created_at' => $this->clock->now(),
            ]);

            return $cycle->fresh() ?? $cycle;
        });
    }
}
