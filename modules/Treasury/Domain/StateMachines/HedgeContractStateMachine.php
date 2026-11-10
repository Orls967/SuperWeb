<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\StateMachines;

use Illuminate\Support\Facades\DB;
use Modules\Treasury\Domain\Enums\HedgeStatus;
use Modules\Treasury\Domain\Exceptions\HedgeInvalidTransitionException;
use Modules\Treasury\Domain\Exceptions\HedgeMakerCheckerViolationException;
use Modules\Treasury\Domain\Models\ForwardContract;
use Modules\Treasury\Domain\Models\ForwardContractHistory;
use Psr\Clock\ClockInterface;

/**
 * State Machine Kontrak Lindung Nilai (Forward FX Hedge).
 * 8 Status Siklus Hidup:
 *   DRAFT -> PENDING_APPROVAL -> APPROVED -> BOOKED -> ACTIVE -> (MATURED | SETTLED | ROLLED | CANCELLED)
 *
 * Mengimplementasikan:
 *   - Guard Four-Eyes (Segregation of Duties / Maker-Checker)
 *   - Riwayat status append-only tak-terhapuskan
 *   - Penalti denda pembatalan (cancellation fee guard)
 */
final class HedgeContractStateMachine
{
    /** @var array<string, list<string>> */
    private const ALLOWED_TRANSITIONS = [
        HedgeStatus::DRAFT->value => [
            HedgeStatus::APPROVED->value,
            HedgeStatus::CANCELLED->value,
        ],
        HedgeStatus::APPROVED->value => [
            HedgeStatus::BOOKED->value,
            HedgeStatus::CANCELLED->value,
        ],
        HedgeStatus::BOOKED->value => [
            HedgeStatus::ACTIVE->value,
            HedgeStatus::CANCELLED->value,
        ],
        HedgeStatus::ACTIVE->value => [
            HedgeStatus::MATURED->value,
            HedgeStatus::SETTLED->value,
            HedgeStatus::ROLLED->value,
            HedgeStatus::CANCELLED->value,
        ],
        HedgeStatus::MATURED->value => [
            HedgeStatus::SETTLED->value,
            HedgeStatus::ROLLED->value,
            HedgeStatus::CANCELLED->value,
        ],
        HedgeStatus::SETTLED->value => [],
        HedgeStatus::ROLLED->value => [],
        HedgeStatus::CANCELLED->value => [],
    ];

    public function __construct(
        private readonly ?ClockInterface $clock = null
    ) {}

    public function canTransition(ForwardContract $contract, HedgeStatus $targetStatus): bool
    {
        $current = $contract->status instanceof HedgeStatus ? $contract->status->value : (string) $contract->status;
        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];

        return in_array($targetStatus->value, $allowed, true);
    }

    public function transition(
        ForwardContract $contract,
        HedgeStatus $targetStatus,
        int|string|null $actorId = null,
        string $reason = ''
    ): ForwardContract {
        $fromStatus = $contract->status instanceof HedgeStatus ? $contract->status->value : (string) $contract->status;
        $toStatus = $targetStatus->value;

        if (! $this->canTransition($contract, $targetStatus)) {
            throw new HedgeInvalidTransitionException($fromStatus, $toStatus);
        }

        // Guard 1: Maker-Checker Segregation of Duties pada transisi ke APPROVED
        if ($targetStatus === HedgeStatus::APPROVED) {
            if ($contract->creator_id !== null && $actorId !== null && (string) $contract->creator_id === (string) $actorId) {
                throw new HedgeMakerCheckerViolationException(
                    "Penyetuju kontrak lindung nilai (ID: {$actorId}) tidak boleh sama dengan pembuat draf (prinsip Four-Eyes Segregation of Duties)."
                );
            }
        }

        // Guard 2: Pembatalan dari booked/active mewajibkan pembebanan denda pembatalan
        if (in_array($fromStatus, [HedgeStatus::BOOKED->value, HedgeStatus::ACTIVE->value, HedgeStatus::MATURED->value], true) && $targetStatus === HedgeStatus::CANCELLED) {
            if (! $contract->cancellation_fee_idr) {
                $contract->cancellation_fee_idr = 5_000_000;
            }
        }

        return DB::transaction(function () use ($contract, $fromStatus, $toStatus, $actorId, $reason) {
            $contract->update([
                'status' => $toStatus,
                'cancellation_fee_idr' => $contract->cancellation_fee_idr,
            ]);

            $now = $this->clock ? $this->clock->now()->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');

            ForwardContractHistory::create([
                'forward_contract_id' => $contract->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'actor_id' => $actorId !== null ? (int) $actorId : null,
                'action' => 'status_transition',
                'reason' => $reason,
                'created_at' => $now,
            ]);

            return $contract->fresh();
        });
    }
}
