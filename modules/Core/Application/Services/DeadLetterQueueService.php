<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Application\Actions\TaskInbox\TaskInboxService;
use Modules\Core\Domain\Models\CoreEventDelivery;
use Modules\Core\Domain\StateMachines\EventDeliveryStateMachine;

/**
 * Service DeadLetterQueueService (W8-D40 / DR8-5)
 *
 * Mengelola antrean Dead-Letter Queue (DLQ), filter, inspeksi pesan,
 * replay batch dua langkah, dan pembuangan dengan alasan audit.
 */
class DeadLetterQueueService
{
    public function __construct(
        protected EventDeliveryStateMachine $stateMachine,
        protected ?TaskInboxService $taskInboxService = null
    ) {}

    /**
     * Mengambil daftar pesan DLQ dengan filter.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getMessagesQuery(array $filters = []): Builder
    {
        $query = CoreEventDelivery::query()->where('status', EventDeliveryStateMachine::STATUS_DEAD_LETTERED);

        if (! empty($filters['consumer_group'])) {
            $query->where('consumer_group', $filters['consumer_group']);
        }

        if (! empty($filters['topic'])) {
            $query->where('topic', 'like', "%{$filters['topic']}%");
        }

        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('message_id', 'like', "%{$term}%")
                    ->orWhere('last_error', 'like', "%{$term}%");
            });
        }

        return $query->orderBy('updated_at', 'desc');
    }

    /**
     * Paginasi pesan DLQ.
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->getMessagesQuery($filters)->paginate($perPage);
    }

    /**
     * Memutar ulang satu pesan DLQ.
     */
    public function replayMessage(int $deliveryId, string $reason, ?int $actorId = null): bool
    {
        $delivery = CoreEventDelivery::find($deliveryId);
        if (! $delivery) {
            return false;
        }

        $this->stateMachine->transition(
            $delivery,
            EventDeliveryStateMachine::STATUS_REPLAYED,
            $reason,
            $actorId
        );

        return true;
    }

    /**
     * Memutar ulang sekumpulan pesan DLQ (Batch Replay).
     *
     * @param  array<int, int>  $deliveryIds
     * @return array{success_count: int, failed_count: int}
     */
    public function replayBatch(array $deliveryIds, string $reason, ?int $actorId = null): array
    {
        $success = 0;
        $failed = 0;

        foreach ($deliveryIds as $id) {
            try {
                if ($this->replayMessage((int) $id, $reason, $actorId)) {
                    $success++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        return [
            'success_count' => $success,
            'failed_count' => $failed,
        ];
    }

    /**
     * Membuang (discard) pesan DLQ dengan alasan wajib.
     */
    public function discardMessage(int $deliveryId, string $reason, ?int $actorId = null): bool
    {
        if (trim($reason) === '') {
            throw new DomainException('Alasan pembuangan DLQ wajib diisi.');
        }

        $delivery = CoreEventDelivery::find($deliveryId);
        if (! $delivery) {
            return false;
        }

        $this->stateMachine->transition(
            $delivery,
            EventDeliveryStateMachine::STATUS_DISCARDED,
            $reason,
            $actorId
        );

        return true;
    }

    /**
     * Membuang sekumpulan pesan DLQ secara batch.
     * Jika batch > 20 pesan, memicu tugas persetujuan di Kotak Masuk "Tugas Saya".
     *
     * @param  array<int, int>  $deliveryIds
     * @return array{success_count: int, pending_approval: bool, task_id: int|null}
     */
    public function discardBatch(array $deliveryIds, string $reason, ?int $actorId = null): array
    {
        $count = count($deliveryIds);
        if ($count === 0) {
            return ['success_count' => 0, 'pending_approval' => false, 'task_id' => null];
        }

        // Jika batch > 20 pesan, butuh persetujuan berjenjang
        if ($count > 20) {
            $taskId = null;
            if ($this->taskInboxService) {
                $task = $this->taskInboxService->createTask([
                    'title' => "Persetujuan Pembuangan Massal DLQ ({$count} pesan)",
                    'type' => 'approval',
                    'module' => 'Core',
                    'reference_type' => 'dlq_batch_discard',
                    'reference_id' => (string) ($deliveryIds[0] ?? '0'),
                    'assigned_role' => 'spine_operator',
                    'payload' => [
                        'count' => $count,
                        'ids' => array_slice($deliveryIds, 0, 50),
                        'reason' => $reason,
                    ],
                ]);
                $taskId = $task->id ?? null;
            }

            return [
                'success_count' => 0,
                'pending_approval' => true,
                'task_id' => $taskId,
            ];
        }

        $success = 0;
        foreach ($deliveryIds as $id) {
            try {
                if ($this->discardMessage((int) $id, $reason, $actorId)) {
                    $success++;
                }
            } catch (\Throwable $e) {
                // Abaikan kesalahan tunggal dalam batch kecil
            }
        }

        return [
            'success_count' => $success,
            'pending_approval' => false,
            'task_id' => null,
        ];
    }
}
