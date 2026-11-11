<?php

declare(strict_types=1);

namespace Modules\Core\Application\Boundaries;

use DateTimeInterface;
use DomainException;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Enums\ViolationStatus;
use Modules\Core\Domain\Models\CoreBoundaryViolation;
use Modules\Core\Domain\StateMachines\BoundaryViolationStateMachine;

final class WaiveViolationAction
{
    public function __construct(
        private readonly BoundaryViolationStateMachine $stateMachine = new BoundaryViolationStateMachine,
    ) {}

    public function execute(
        CoreBoundaryViolation $violation,
        string $actorId,
        string $reason,
        string $adrAnchor,
        DateTimeInterface|string $expiresAt,
    ): CoreBoundaryViolation {
        $expiresCarbon = Carbon::parse($expiresAt);

        if ($expiresCarbon->diffInDays(now()) > 90) {
            throw new DomainException('BOUND_WAIVER_EXCEEDS_90_DAYS: Durasi waiver tidak boleh melebihi 90 hari.');
        }

        // Verify DECISIONS.md anchor format or existence
        $cleanAnchor = ltrim(trim($adrAnchor), '#');
        if (str_contains($cleanAnchor, '#')) {
            $parts = explode('#', $cleanAnchor);
            $cleanAnchor = end($parts);
        }

        if (empty($cleanAnchor)) {
            throw new DomainException("BOUND_WAIVER_INVALID_ANCHOR: Anchor keputusan '{$adrAnchor}' tidak valid.");
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
                'waived_at' => now(),
                'expires_at' => $expiresCarbon,
            ]
        );
    }
}
