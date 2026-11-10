<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use Modules\Core\Domain\Models\CoreTask;

/**
 * Factory pembentukan tugas kotak masuk terstandarisasi untuk template umum operasional.
 */
class TaskTemplateFactory
{
    public function __construct(
        protected TaskInboxService $inboxService,
    ) {}

    /**
     * Membuat tugas verifikasi pembayaran faktur invoice.
     */
    public function createInvoiceApprovalTask(string $invoiceId, int $amountCents, ?string $approverUserId = null): CoreTask
    {
        return $this->inboxService->createTask([
            'title' => 'Persetujuan Pembayaran Faktur #'.$invoiceId,
            'description' => sprintf('Verifikasi pencairan pembayaran invoice senilai IDR %s.', number_format($amountCents / 100, 2)),
            'module' => 'Finance',
            'source_entity_type' => 'InvoicePayment',
            'source_entity_id' => $invoiceId,
            'assignee_user_id' => $approverUserId,
            'assignee_role' => 'finance_manager',
            'priority' => ($amountCents > 100000000) ? 'urgent' : 'high',
            'due_at' => now()->addHours(24),
            'action_url' => '/finance/invoices/'.$invoiceId,
            'payload' => ['amount_cents' => $amountCents],
        ]);
    }

    /**
     * Membuat tugas inspeksi keselamatan berkala (safety check).
     */
    public function createSafetyInspectionTask(string $equipmentId, string $location, ?string $inspectorUserId = null): CoreTask
    {
        return $this->inboxService->createTask([
            'title' => 'Inspeksi K3 & Keselamatan Peralatan: '.$equipmentId,
            'description' => 'Pemeriksaan rutin K3 unit operasional di lokasi '.$location,
            'module' => 'Operations',
            'source_entity_type' => 'EquipmentInspection',
            'source_entity_id' => $equipmentId,
            'assignee_user_id' => $inspectorUserId,
            'assignee_role' => 'safety_officer',
            'priority' => 'normal',
            'due_at' => now()->addDays(3),
            'action_url' => '/operations/safety/'.$equipmentId,
            'payload' => ['location' => $location],
        ]);
    }
}
