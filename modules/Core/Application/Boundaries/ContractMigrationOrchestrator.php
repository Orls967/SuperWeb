<?php

declare(strict_types=1);

namespace Modules\Core\Application\Boundaries;

use Modules\Core\Domain\Enums\ViolationStatus;
use Modules\Core\Domain\Models\CoreBoundaryViolation;

/**
 * Orchestrator for progressive, zero-downtime contract migrations across modules (DR4-4).
 *
 * Manages phased rollout:
 * 1. Interface Specification & Container Registration
 * 2. Parallel Dual-Run (Canary verification)
 * 3. Callsite Refactoring by Module Wave
 * 4. Verification Checkpoint & Status Transition
 * 5. Baseline Ratchet Update
 */
final class ContractMigrationOrchestrator
{
    public function __construct(
        private readonly TransitionViolationAction $transitionAction = new TransitionViolationAction,
        private readonly VerifyViolationAction $verifyAction = new VerifyViolationAction,
    ) {}

    /**
     * Advance a boundary violation to the next migration phase.
     *
     * @param  array<string, mixed>  $metadata
     * @return array{
     *     violationId: int,
     *     previousStatus: string,
     *     currentStatus: string,
     *     isTerminal: bool,
     *     nextActionRequired: string
     * }
     */
    public function advancePhase(
        CoreBoundaryViolation $violation,
        string $actorId,
        string $reason,
        array $metadata = []
    ): array {
        $fromStatus = $violation->status instanceof \BackedEnum
            ? $violation->status->value
            : (string) $violation->status;

        $targetStatus = match ($fromStatus) {
            'detected' => ViolationStatus::TRIAGED,
            'triaged' => ViolationStatus::CONTRACT_DESIGNED,
            'contract_designed' => ViolationStatus::MIGRATING,
            'migrating' => ViolationStatus::VERIFIED,
            'verified' => ViolationStatus::CLOSED,
            default => throw new \DomainException("Status {$fromStatus} tidak dapat dimajukan lebih lanjut."),
        };

        if ($targetStatus === ViolationStatus::VERIFIED) {
            $updated = $this->verifyAction->execute($violation, $actorId, $reason);
        } else {
            $updated = $this->transitionAction->execute($violation, $targetStatus, $actorId, $reason, $metadata);
        }

        $nextStatus = $updated->status instanceof \BackedEnum
            ? $updated->status->value
            : (string) $updated->status;

        $isTerminal = $nextStatus === 'closed';

        $nextAction = match ($nextStatus) {
            'triaged' => 'Rancang antarmuka kontrak di Modules/{Owner}/Contracts/',
            'contract_designed' => 'Mulai refaktorisasi use statement di modul klien',
            'migrating' => 'Jalankan unit test dan periksa eliminasi dari arch:scan',
            'verified' => 'Jalankan arch:scan --update-baseline dan commit ke git',
            'closed' => 'Migrasi selesai. Kontrak aktif sepenuhnya.',
            default => 'Lanjutkan pemantauan.',
        };

        return [
            'violationId' => (int) $updated->id,
            'previousStatus' => $fromStatus,
            'currentStatus' => $nextStatus,
            'isTerminal' => $isTerminal,
            'nextActionRequired' => $nextAction,
        ];
    }

    /**
     * Batch advance all migrating violations whose baseline entries have been removed.
     *
     * @return array{advancedCount: int, verifiedIds: list<int>}
     */
    public function batchVerifyMigrated(string $actorId = 'system:orchestrator'): array
    {
        $migrating = CoreBoundaryViolation::where('status', 'migrating')->get();
        $verifiedIds = [];

        foreach ($migrating as $violation) {
            try {
                $this->advancePhase(
                    $violation,
                    $actorId,
                    'Batch verification: baseline cleared and unit test passed'
                );
                $verifiedIds[] = (int) $violation->id;
            } catch (\Throwable) {
                // Skip violations whose guards are not yet satisfied
                continue;
            }
        }

        return [
            'advancedCount' => count($verifiedIds),
            'verifiedIds' => $verifiedIds,
        ];
    }
}
