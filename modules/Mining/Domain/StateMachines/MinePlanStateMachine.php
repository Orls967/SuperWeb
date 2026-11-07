<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\StateMachines;

use Modules\Mining\Domain\Enums\MinePlanStatus;
use Modules\Mining\Domain\Exceptions\InvalidMiningStateTransitionException;
use Modules\Mining\Domain\Models\MiningPlan;

/**
 * State Machine Siklus Hidup Rencana Tambang Bulanan (D93-1).
 * Mengawal 8 status: draft -> submitted -> reviewed -> approved -> active -> revised -> closed -> cancelled.
 */
final class MinePlanStateMachine
{
    public function transition(MiningPlan $plan, MinePlanStatus $targetStatus, ?string $actorId = null, ?string $notes = null): void
    {
        $currentStatus = $this->resolveCurrentStatus($plan);

        if ($currentStatus === $targetStatus) {
            return;
        }

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningPlan',
                $currentStatus->value,
                $targetStatus->value,
                "Rencana tambang {$plan->plan_code} berstatus {$currentStatus->value} tidak dapat langsung dialihkan ke {$targetStatus->value} (Aktor: ".($actorId ?? 'system').', Catatan: '.($notes ?? '-').')'
            );
        }

        $this->assertTransitionGuard($plan, $currentStatus, $targetStatus);

        $plan->update([
            'status' => $targetStatus->value,
        ]);
    }

    /**
     * Menjalankan rangkaian transisi berurutan (misal dari draft sampai approved saat pengesahan rencana).
     *
     * @param  list<MinePlanStatus>  $steps
     */
    public function advanceAlongPath(MiningPlan $plan, array $steps, ?string $actorId = null, ?string $notes = null): void
    {
        foreach ($steps as $step) {
            $this->transition($plan, $step, $actorId, $notes);
        }
    }

    public function resolveCurrentStatus(MiningPlan $plan): MinePlanStatus
    {
        return $plan->status instanceof MinePlanStatus
            ? $plan->status
            : (MinePlanStatus::tryFrom((string) $plan->status) ?? MinePlanStatus::DRAFT);
    }

    private function assertTransitionGuard(MiningPlan $plan, MinePlanStatus $from, MinePlanStatus $to): void
    {
        if (in_array($to, [MinePlanStatus::APPROVED, MinePlanStatus::ACTIVE], true) && (int) $plan->target_ore_kg <= 0) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningPlan',
                $from->value,
                $to->value,
                'Rencana tambang wajib memiliki target tonase bijih > 0 kg sebelum disetujui atau diaktifkan'
            );
        }
    }
}
