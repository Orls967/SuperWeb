<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\StateMachines;

use Modules\Mining\Domain\Enums\RoyaltyReturnStatus;
use Modules\Mining\Domain\Exceptions\InvalidMiningStateTransitionException;
use Modules\Mining\Domain\Models\MiningRoyaltyReturn;

/**
 * State Machine Pelaporan & Pembayaran Royalti Minerba (D94-4).
 * Mengawal 8 status: draft -> calculated -> reviewed -> approved -> filed -> paid -> amended -> closed.
 */
final class RoyaltyReturnStateMachine
{
    public function __construct(
        private ?MiningRoyaltyReturn $returnModel = null
    ) {}

    public function transitionTo(RoyaltyReturnStatus $targetStatus, ?string $notes = null): void
    {
        if (! $this->returnModel) {
            throw new \RuntimeException('MiningRoyaltyReturn model tidak disetel dalam state machine.');
        }

        $this->transition($this->returnModel, $targetStatus, notes: $notes);
    }

    public function transition(
        MiningRoyaltyReturn $return,
        RoyaltyReturnStatus $targetStatus,
        ?string $actorId = null,
        ?string $notes = null
    ): void {
        $currentStatus = $return->status;

        if ($currentStatus === $targetStatus) {
            return;
        }

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningRoyaltyReturn',
                $currentStatus->value,
                $targetStatus->value,
                "SPT Royalti {$return->return_number} berstatus {$currentStatus->value} tidak dapat dialihkan ke {$targetStatus->value}."
            );
        }

        $this->assertTransitionGuard($return, $currentStatus, $targetStatus);

        $updates = ['status' => $targetStatus];
        if ($targetStatus === RoyaltyReturnStatus::FILED && empty($return->document_number)) {
            $updates['document_number'] = 'SPT-ROYL-'.$return->period_month.'-'.substr($return->id, 0, 8);
        }

        $return->update($updates);
    }

    public function advanceAlongPath(
        MiningRoyaltyReturn $return,
        array $steps,
        ?string $actorId = null,
        ?string $notes = null
    ): void {
        foreach ($steps as $step) {
            $this->transition($return, $step, $actorId, $notes);
        }
    }

    private function assertTransitionGuard(
        MiningRoyaltyReturn $return,
        RoyaltyReturnStatus $from,
        RoyaltyReturnStatus $to
    ): void {
        if ($to === RoyaltyReturnStatus::APPROVED && $return->calculated_royalty_idr <= 0) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningRoyaltyReturn',
                $from->value,
                $to->value,
                "SPT Royalti {$return->return_number} tidak dapat disetujui karena nilai royalti IDR nihil."
            );
        }

        if ($to === RoyaltyReturnStatus::PAID && empty($return->payment_ref)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningRoyaltyReturn',
                $from->value,
                $to->value,
                "SPT Royalti {$return->return_number} tidak dapat dialihkan ke PAID tanpa NTPN/referensi pembayaran perbankan."
            );
        }
    }
}
