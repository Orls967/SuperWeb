<?php

declare(strict_types=1);

namespace Modules\Core\Application\Boundaries;

use Modules\Core\Application\Actions\TaskInbox\TaskInboxService;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\Core\Domain\Enums\ViolationStatus;
use Modules\Core\Domain\Models\CoreBoundaryViolation;
use Modules\Core\Domain\StateMachines\BoundaryViolationStateMachine;

final class ExpireWaiversAction
{
    public function __construct(
        private readonly BoundaryViolationStateMachine $stateMachine = new BoundaryViolationStateMachine,
        private readonly ?TaskInboxService $taskInbox = null,
        private readonly ?OutboxBusInterface $outboxBus = null,
    ) {}

    public function execute(): int
    {
        $expiredViolations = CoreBoundaryViolation::where('status', ViolationStatus::WAIVED->value)
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;

        foreach ($expiredViolations as $violation) {
            $this->stateMachine->transition(
                $violation,
                ViolationStatus::TRIAGED,
                'system:expire-waivers',
                "Waiver expired on {$violation->expires_at?->toIso8601String()}",
            );

            // Notify task inbox
            if ($this->taskInbox !== null) {
                try {
                    $this->taskInbox->createTask([
                        'title' => "Waiver Kedaluwarsa: {$violation->rule_id} pada {$violation->client_module}",
                        'description' => "Waiver arsitektur untuk {$violation->signature} ({$violation->file_path}:{$violation->line_number}) telah kedaluwarsa. Status dikembalikan ke triaged.",
                        'assigned_role' => 'platform_architect',
                        'priority' => 'high',
                        'source' => 'boundary:expire-waivers',
                        'status' => 'pending',
                    ]);
                } catch (\Throwable) {
                    // Non-blocking notification failure
                }
            }

            // Publish outbox event
            if ($this->outboxBus !== null) {
                try {
                    $this->outboxBus->publish(
                        'core.boundary.waiver_expired.v1',
                        [
                            'violation_id' => $violation->id,
                            'rule_id' => $violation->rule_id,
                            'client_module' => $violation->client_module,
                            'owner_module' => $violation->owner_module,
                            'signature' => $violation->signature,
                            'expired_at' => now()->toIso8601String(),
                        ],
                        'Core',
                        'CoreBoundaryViolation',
                        (string) $violation->id,
                    );
                } catch (\Throwable) {
                    // Non-blocking outbox failure
                }
            }

            $count++;
        }

        return $count;
    }
}
