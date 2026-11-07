<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\StateMachines;

use Modules\Mining\Domain\Enums\FuelAnomalyCaseStatus;
use Modules\Mining\Domain\Exceptions\InvalidMiningStateTransitionException;
use Modules\Mining\Domain\Models\MiningFuelAnomalyCase;
use Modules\Mining\Domain\Models\MiningFuelAnomalyHistory;

/**
 * State Machine Siklus Sengketa & Penahanan Tagihan Anomali BBM (D93-5).
 * Mengawal 8 status: detected -> verifying -> confirmed -> payment_held -> disputed -> resolved_deducted -> resolved_released -> written_off.
 */
final class FuelAnomalyCaseStateMachine
{
    public function transition(MiningFuelAnomalyCase $case, FuelAnomalyCaseStatus $targetStatus, ?string $actorId = null, ?string $notes = null): void
    {
        $currentStatus = $this->resolveCurrentStatus($case);

        if ($currentStatus === $targetStatus) {
            return;
        }

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningFuelAnomalyCase',
                $currentStatus->value,
                $targetStatus->value,
                "Kasus anomali BBM {$case->case_number} berstatus {$currentStatus->value} tidak dapat beralih ke {$targetStatus->value}"
            );
        }

        $this->assertTransitionPreconditions($case, $currentStatus, $targetStatus, $notes);

        $case->update([
            'status' => $targetStatus->value,
        ]);

        MiningFuelAnomalyHistory::create([
            'case_id' => $case->id,
            'from_status' => $currentStatus->value,
            'to_status' => $targetStatus->value,
            'actor_id' => $actorId ?? 'system',
            'notes' => $notes,
        ]);
    }

    public function resolveCurrentStatus(MiningFuelAnomalyCase $case): FuelAnomalyCaseStatus
    {
        return $case->status instanceof FuelAnomalyCaseStatus
            ? $case->status
            : (FuelAnomalyCaseStatus::tryFrom((string) $case->status) ?? FuelAnomalyCaseStatus::DETECTED);
    }

    private function assertTransitionPreconditions(
        MiningFuelAnomalyCase $case,
        FuelAnomalyCaseStatus $from,
        FuelAnomalyCaseStatus $to,
        ?string $notes
    ): void {
        if ($to === FuelAnomalyCaseStatus::PAYMENT_HELD && (int) $case->held_amount_idr <= 0) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningFuelAnomalyCase',
                $from->value,
                $to->value,
                'Penahanan pembayaran kontraktor wajib memiliki nilai held_amount_idr positif'
            );
        }

        if ($to === FuelAnomalyCaseStatus::DISPUTED && ($notes === null || trim($notes) === '')) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningFuelAnomalyCase',
                $from->value,
                $to->value,
                'Sanggahan kontraktor wajib menyertakan alasan tertulis'
            );
        }
    }
}
