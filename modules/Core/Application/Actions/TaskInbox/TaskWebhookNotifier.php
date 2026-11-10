<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use Modules\Core\Domain\Models\CoreTask;

/**
 * Service pengiriman payload webhook mutasi tugas ke endpoint terdaftar (Outbound Webhooks).
 */
class TaskWebhookNotifier
{
    /**
     * Membentuk payload event webhook dengan tanda tangan HMAC-SHA256 untuk verifikasi penerima.
     *
     * @return array{
     *     event: string,
     *     task_id: string,
     *     payload: array<string, mixed>,
     *     signature: string,
     *     timestamp: int
     * }
     */
    public function buildSignedWebhookPayload(CoreTask $task, string $event, string $secretKey): array
    {
        $timestamp = time();
        $payload = [
            'id' => $task->id,
            'title' => $task->title,
            'module' => $task->module,
            'status' => $task->status,
            'priority' => $task->priority,
            'assignee_user_id' => $task->assignee_user_id,
            'escalation_level' => $task->escalation_level,
            'due_at' => $task->due_at?->toIso8601String(),
        ];

        $signedString = sprintf('%s.%d.%s', $event, $timestamp, json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $signedString, $secretKey);

        return [
            'event' => $event,
            'task_id' => $task->id,
            'payload' => $payload,
            'signature' => $signature,
            'timestamp' => $timestamp,
        ];
    }
}
