<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Bridge;

use Modules\Core\Domain\Models\CoreTask;
use Modules\Core\Domain\Models\CoreSpineGap;
use Modules\Core\Domain\Models\CoreEventDelivery;
use Modules\Core\Domain\Models\CorePoisonMessage;
use Modules\Core\Application\Actions\TaskInbox\TaskInboxService;

/**
 * Jembatan Integrasi Event Spine ke Task Inbox (SpineTaskInboxBridgeService)
 *
 * Menerbitkan tiket tugas operasional ke kotak masuk "Tugas Saya" operator/admin
 * ketika terjadi insiden kritis di Universal Event Spine:
 * - Kegagalan DLQ berulang
 * - Isolasi pesan beracun (poison message)
 * - Celah nomor urut aggregate tak teratasi (sequence gap)
 */
class SpineTaskInboxBridgeService
{
    public function __construct(
        protected TaskInboxService $taskInboxService
    ) {}

    /**
     * Membuat tugas investigasi untuk rekaman Dead-Letter Queue.
     */
    public function notifyDeadLetter(CoreEventDelivery $delivery): CoreTask
    {
        $topic = (string) ($delivery->event?->topic ?? 'unknown.event');
        $errorPreview = substr((string) $delivery->last_error, 0, 150);

        return $this->taskInboxService->createTask([
            'title' => "[DLQ Kritis] Pengiriman Event Gagal: {$topic}",
            'description' => "Pengiriman event (ID: {$delivery->id}) ke consumer '{$delivery->consumer_group}' gagal setelah {$delivery->retry_count} percobaan. Galat: {$errorPreview}",
            'module' => 'Core',
            'source_entity_type' => 'CoreEventDelivery',
            'source_entity_id' => (string) $delivery->id,
            'assignee_role' => 'spine_operator',
            'priority' => $delivery->retry_count >= 5 ? 'urgent' : 'high',
            'action_url' => "/admin/spine/dlq?id={$delivery->id}",
            'payload' => [
                'delivery_id' => $delivery->id,
                'consumer_group' => $delivery->consumer_group,
                'retry_count' => $delivery->retry_count,
                'topic' => $topic,
            ],
        ]);
    }

    /**
     * Membuat tugas investigasi darurat untuk pesan beracun yang diisolasi.
     */
    public function notifyPoisonMessage(CorePoisonMessage $poison): CoreTask
    {
        return $this->taskInboxService->createTask([
            'title' => "[POISON MESSAGE] Karantina Pesan Beracun: {$poison->topic}",
            'description' => "Pesan dengan kunci '{$poison->message_key}' diisolasi ke karantina non-blocking karena memicu eksepsi fatal: {$poison->exception_class}.",
            'module' => 'Core',
            'source_entity_type' => 'CorePoisonMessage',
            'source_entity_id' => (string) $poison->id,
            'assignee_role' => 'spine_operator',
            'priority' => 'urgent',
            'action_url' => "/admin/spine/dlq?view=poison&id={$poison->id}",
            'payload' => [
                'poison_id' => $poison->id,
                'topic' => $poison->topic,
                'exception_class' => $poison->exception_class,
                'message_key' => $poison->message_key,
            ],
        ]);
    }

    /**
     * Membuat tugas investigasi celah urutan aggregate.
     */
    public function notifyAggregateGap(CoreSpineGap $gap): CoreTask
    {
        return $this->taskInboxService->createTask([
            'title' => "[GAP SEQUENCE] Celah Urutan Terdeteksi: {$gap->aggregate_type}#{$gap->aggregate_id}",
            'description' => "Ditemukan celah nomor urut pada aggregate '{$gap->aggregate_type}:{$gap->aggregate_id}' (urutan hilang: {$gap->missing_from} s.d. {$gap->missing_to}).",
            'module' => 'Core',
            'source_entity_type' => 'CoreSpineGap',
            'source_entity_id' => (string) $gap->id,
            'assignee_role' => 'spine_operator',
            'priority' => 'high',
            'action_url' => "/admin/spine/gaps?id={$gap->id}",
            'payload' => [
                'gap_id' => $gap->id,
                'aggregate_type' => $gap->aggregate_type,
                'aggregate_id' => $gap->aggregate_id,
                'missing_from' => $gap->missing_from,
                'missing_to' => $gap->missing_to,
            ],
        ]);
    }
}
