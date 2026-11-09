<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use Modules\Platform\Domain\Models\SandboxEnvironment;
use Modules\Platform\Domain\Services\SandboxIsolationService;

/**
 * Aksi audit dan pembersihan kuota sandbox yang tidak aktif.
 */
class ManageSandboxQuotaAction
{
    public function __construct(
        protected SandboxIsolationService $isolation = new SandboxIsolationService,
        protected ExpireSandboxAction $expireAction = new ExpireSandboxAction
    ) {}

    /**
     * @return array{active_count: int, expired_cleaned: int, quota_status: string}
     */
    public function auditUserQuota(int $userId): array
    {
        $cleaned = $this->expireAction->expireOverdue();

        $active = SandboxEnvironment::where('owner_id', $userId)
            ->whereIn('status', ['requested', 'provisioning', 'seeded', 'ready', 'expiring'])
            ->count();

        $status = $active >= SandboxIsolationService::MAX_ACTIVE_PER_USER ? 'quota_reached' : 'quota_available';

        return [
            'active_count' => $active,
            'expired_cleaned' => $cleaned,
            'quota_status' => $status,
        ];
    }
}
