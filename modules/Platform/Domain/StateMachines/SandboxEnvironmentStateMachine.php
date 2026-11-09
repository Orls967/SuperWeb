<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\StateMachines;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Domain\Enums\SandboxStatus;
use Modules\Platform\Domain\Models\SandboxEnvironment;
use Modules\Platform\Domain\Models\SandboxTransition;

/**
 * State Machine 7 status ketat siklus hidup Sandbox Environment (W8-D05).
 * requested -> provisioning -> seeded -> ready -> expiring -> expired -> destroyed.
 */
class SandboxEnvironmentStateMachine
{
    /** @var array<string, list<string>> */
    public const ALLOWED_TRANSITIONS = [
        'requested' => ['provisioning', 'destroyed'],
        'provisioning' => ['seeded', 'destroyed'],
        'seeded' => ['ready', 'destroyed'],
        'ready' => ['expiring', 'expired', 'destroyed'],
        'expiring' => ['ready', 'expired', 'destroyed'],
        'expired' => ['destroyed', 'provisioning'],
        'destroyed' => [],
    ];

    public function __construct(
        protected SandboxEnvironment $sandbox
    ) {
    }

    public function getCurrentStatus(): SandboxStatus
    {
        return SandboxStatus::from($this->sandbox->status);
    }

    public function canTransitionTo(SandboxStatus $target): bool
    {
        $current = $this->sandbox->status;
        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];

        return in_array($target->value, $allowed, true);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function transitionTo(SandboxStatus $target, string $reason = '', array $context = []): SandboxEnvironment
    {
        if (! $this->canTransitionTo($target)) {
            throw new DomainException(sprintf(
                'Transisi status sandbox tidak sah dari %s ke %s (Sandbox ID: %s)',
                $this->sandbox->status,
                $target->value,
                (string) $this->sandbox->id
            ));
        }

        return DB::transaction(function () use ($target, $reason, $context): SandboxEnvironment {
            $from = $this->sandbox->status;

            $this->sandbox->status = $target->value;
            $this->sandbox->save();

            SandboxTransition::create([
                'sandbox_id' => $this->sandbox->id,
                'from_status' => $from,
                'to_status' => $target->value,
                'reason' => $reason !== '' ? $reason : "Transitioned to {$target->value}",
                'context' => $context,
                'created_at' => now(),
            ]);

            return $this->sandbox;
        });
    }
}

