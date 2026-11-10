<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use Modules\Core\Domain\Models\CoreTask;

/**
 * Bridge adaptor antara ApprovalEngine / Notification dengan Kotak Masuk "Tugas Saya".
 */
class ApprovalEngineTaskBridge
{
    public function __construct(
        protected TaskInboxService $taskInboxService,
    ) {}

    /**
     * Mendaftarkan permintaan approval menjadi tugas di kotak masuk pengguna.
     *
     * @param  array<string, mixed>  $approvalRequest
     */
    public function syncApprovalRequest(array $approvalRequest): CoreTask
    {
        return $this->taskInboxService->createTask([
            'title' => 'Persetujuan: '.($approvalRequest['title'] ?? 'Dokumen Baru'),
            'description' => $approvalRequest['description'] ?? null,
            'module' => $approvalRequest['module'] ?? 'Core',
            'source_entity_type' => $approvalRequest['entity_type'] ?? 'ApprovalRequest',
            'source_entity_id' => (string) ($approvalRequest['entity_id'] ?? uniqid()),
            'assignee_user_id' => $approvalRequest['approver_id'] ?? null,
            'assignee_role' => $approvalRequest['approver_role'] ?? null,
            'status' => 'pending',
            'priority' => $approvalRequest['priority'] ?? 'normal',
            'due_at' => $approvalRequest['due_at'] ?? now()->addDays(2),
            'action_url' => $approvalRequest['action_url'] ?? null,
            'payload' => $approvalRequest['payload'] ?? null,
        ]);
    }
}
