<?php

declare(strict_types=1);

namespace Modules\Core\Application\Boundaries;

use App\Quality\Docs\DecisionLog;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Domain\Enums\ViolationStatus;
use Modules\Core\Domain\Models\CoreBoundaryViolation;
use Modules\Core\Domain\StateMachines\BoundaryViolationStateMachine;

final class WaiveViolationAction
{
    public function __construct(
        private readonly BoundaryViolationStateMachine $stateMachine = new BoundaryViolationStateMachine,
        private readonly TransitionViolationAction $transitionAction = new TransitionViolationAction,
        private readonly ?ClockInterface $clock = null,
    ) {}

    public function execute(
        CoreBoundaryViolation $violation,
        string $actorId,
        string $reason,
        string $adrAnchor,
        DateTimeInterface|string $expiresAt,
    ): CoreBoundaryViolation {
        $now = ($this->clock ?? app(ClockInterface::class))->now();
        $expiresCarbon = Carbon::parse($expiresAt);

        if ($now->diffInDays($expiresCarbon, false) <= 0 || $now->diffInDays($expiresCarbon, false) > 90) {
            throw new DomainException('BOUND_WAIVER_EXCEEDS_90_DAYS: Durasi waiver harus antara 1 dan 90 hari.');
        }

        $cleanAnchor = ltrim(trim($adrAnchor), '#');
        if (str_contains($cleanAnchor, '#')) {
            $parts = explode('#', $cleanAnchor);
            $cleanAnchor = (string) end($parts);
        }

        if ($cleanAnchor === '') {
            throw new DomainException("BOUND_WAIVER_INVALID_ANCHOR: Anchor keputusan '{$adrAnchor}' tidak valid.");
        }

        $decisionsPath = base_path('docs/DECISIONS.md');
        if (is_file($decisionsPath) && ! DecisionLog::fromFile($decisionsPath)->has($cleanAnchor)) {
            throw new DomainException("BOUND_WAIVER_ANCHOR_MISSING: Anchor keputusan '{$adrAnchor}' tidak ditemukan di docs/DECISIONS.md.");
        }

        return $this->stateMachine->transition(
            $violation,
            ViolationStatus::WAIVED,
            $actorId,
            $reason,
            [
                'adr_anchor' => $adrAnchor,
                'waiver_reason' => $reason,
                'waived_by' => $actorId,
                'waived_at' => $now,
                'expires_at' => $expiresCarbon,
            ]
        );
    }

    /**
     * Extend an active or triaged waiver atomically inside a database transaction (W8-D43).
     */
    public function extend(
        CoreBoundaryViolation $violation,
        string $actorId,
        string $reason,
        string $adrAnchor,
        int $days,
    ): CoreBoundaryViolation {
        $now = ($this->clock ?? app(ClockInterface::class))->now();
        $expiresAt = $now->copy()->addDays($days);

        return DB::transaction(function () use ($violation, $actorId, $reason, $adrAnchor, $expiresAt): CoreBoundaryViolation {
            if ($violation->status === ViolationStatus::WAIVED) {
                $violation = $this->transitionAction->execute(
                    $violation,
                    ViolationStatus::TRIAGED,
                    $actorId,
                    'Pembukaan ulang sementara untuk perpanjangan waiver: '.$reason
                );
            }

            return $this->execute($violation, $actorId, $reason, $adrAnchor, $expiresAt);
        });
    }
}
