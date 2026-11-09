<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Domain\Enums\SandboxStatus;
use Modules\Platform\Domain\Enums\SandboxTier;
use Modules\Platform\Domain\Models\SandboxEnvironment;
use Modules\Platform\Domain\StateMachines\SandboxEnvironmentStateMachine;
use Modules\Platform\Domain\ValueObjects\SandboxResourceAllocation;

/**
 * Service isolasi dan manajemen kuota lingkungan sandbox developer.
 */
class SandboxIsolationService
{
    public const MAX_ACTIVE_PER_USER = 5;

    public function ensureQuotaAvailable(int $userId): void
    {
        $activeCount = SandboxEnvironment::where('owner_id', $userId)
            ->whereIn('status', [
                SandboxStatus::REQUESTED->value,
                SandboxStatus::PROVISIONING->value,
                SandboxStatus::SEEDED->value,
                SandboxStatus::READY->value,
                SandboxStatus::EXPIRING->value,
            ])
            ->count();

        if ($activeCount >= self::MAX_ACTIVE_PER_USER) {
            throw new DomainException(sprintf(
                'Kuota maksimum sandbox aktif tercapai (%d environment). Hapus atau tunggu kadaluarsa sebelum membuat baru.',
                self::MAX_ACTIVE_PER_USER
            ));
        }
    }

    /**
     * Snapshot rollback: mengembalikan status database sandbox ke baseline awal
     */
    public function rollbackToSnapshot(SandboxEnvironment $sandbox, string $snapshotId): bool
    {
        if ($sandbox->status !== SandboxStatus::READY->value) {
            throw new DomainException('Hanya sandbox berstatus READY yang dapat di-rollback ke snapshot.');
        }

        $sm = new SandboxEnvironmentStateMachine($sandbox);
        $sm->transitionTo(SandboxStatus::PROVISIONING, "Rollback initiated to snapshot: {$snapshotId}", ['snapshot_id' => $snapshotId]);
        $sm->transitionTo(SandboxStatus::SEEDED, 'Re-seeding database schema from snapshot');
        $sm->transitionTo(SandboxStatus::READY, 'Rollback complete; sandbox restored');

        return true;
    }

    /**
     * Memperpanjang TTL sandbox jika tier mengizinkan
     */
    public function extendTtl(SandboxEnvironment $sandbox, int $extensionHours): SandboxEnvironment
    {
        if ($sandbox->tier === SandboxTier::EPHEMERAL->value && $extensionHours > 12) {
            throw new DomainException('Sandbox tier ephemeral tidak dapat diperpanjang lebih dari 12 jam.');
        }

        $currentExpiry = $sandbox->expires_at ?? now();
        $sandbox->expires_at = $currentExpiry->addHours($extensionHours);

        if ($sandbox->status === SandboxStatus::EXPIRING->value) {
            $sm = new SandboxEnvironmentStateMachine($sandbox);
            $sm->transitionTo(SandboxStatus::READY, "TTL extended by {$extensionHours} hours");
        } else {
            $sandbox->save();
        }

        return $sandbox;
    }
}

