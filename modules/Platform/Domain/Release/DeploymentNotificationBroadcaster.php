<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Release;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Domain\Models\ChangeDeployment;

final class DeploymentNotificationBroadcaster
{
    /**
     * @param  array<string, mixed>  $extraContext
     * @return array{
     *     dispatched: bool,
     *     channel: string,
     *     payload: array<string, mixed>
     * }
     */
    public function broadcastDeploymentEvent(
        ChangeDeployment $deployment,
        string $eventType,
        array $extraContext = []
    ): array {
        $changeNumber = $deployment->changeRequest?->change_number ?? 'CR-UNKNOWN';
        $summary = "Deployment {$deployment->deployment_code} [{$deployment->environment}] status: {$deployment->status}";

        $payload = [
            'event' => $eventType,
            'deployment_code' => $deployment->deployment_code,
            'change_number' => $changeNumber,
            'environment' => $deployment->environment,
            'status' => $deployment->status,
            'summary' => $summary,
            'timestamp' => CarbonImmutable::now()->toIso8601String(),
            'context' => $extraContext,
        ];

        Log::info("[DEPLOYMENT-EVENT] {$eventType}: {$summary}", $payload);

        return [
            'dispatched' => true,
            'channel' => 'platform-deployments',
            'payload' => $payload,
        ];
    }
}
