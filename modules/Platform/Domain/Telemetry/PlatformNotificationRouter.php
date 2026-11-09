<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Telemetry;

/**
 * Router notifikasi insiden keandalan dan peringatan masa aktif sandbox.
 */
class PlatformNotificationRouter
{
    /**
     * @return array{channel: string, payload: array<string, mixed>, dispatched: bool}
     */
    public function routeAlert(string $eventType, string $title, string $message, string $severity = 'info'): array
    {
        $channel = match ($severity) {
            'critical' => 'pager_webhook',
            'warning' => 'slack_platform_alerts',
            default => 'system_event_stream',
        };

        $payload = [
            'event_type' => $eventType,
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'timestamp' => date('c'),
        ];

        return [
            'channel' => $channel,
            'payload' => $payload,
            'dispatched' => true,
        ];
    }
}

