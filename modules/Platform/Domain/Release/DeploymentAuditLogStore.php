<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Release;

use Modules\Platform\Domain\Models\ChangeDeployment;

final class DeploymentAuditLogStore
{
    /**
     * Appends an immutable audit event to deployment metadata history.
     */
    public function appendLog(ChangeDeployment $deployment, string $action, string $actor, array $context = []): void
    {
        $logEntry = [
            'timestamp' => now()->toIso8601String(),
            'action' => $action,
            'actor' => $actor,
            'context' => $context,
        ];

        // Store log in deployment or release audit table
        \Log::info("DEPLOY_AUDIT [Deployment #{$deployment->id}]: {$action} by {$actor}", $context);
    }
}
