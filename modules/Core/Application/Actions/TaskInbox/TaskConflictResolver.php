<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Models\CoreTask;

/**
 * Resolver konflik konkurensi (Optimistic Concurrency & Lock Handling)
 * untuk mencegah mutasi ganda pada aksi persetujuan tugas yang sama.
 */
class TaskConflictResolver
{
    /**
     * Mengeksekusi mutasi status dengan proteksi kunci pesimistik (SELECT FOR UPDATE)
     * untuk mencegah dua pengguna menyetujui tiket yang sama secara bersamaan.
     *
     * @template T
     *
     * @param  callable(CoreTask): T  $operation
     * @return T
     *
     * @throws DomainException jika status sudah berubah saat kunci didapat
     */
    public function executeWithLock(string $taskId, string $expectedStatus, callable $operation): mixed
    {
        return DB::transaction(function () use ($taskId, $expectedStatus, $operation) {
            /** @var CoreTask|null $lockedTask */
            $lockedTask = CoreTask::query()->where('id', $taskId)->lockForUpdate()->first();

            if ($lockedTask === null) {
                throw new DomainException(sprintf('Task dengan ID [%s] tidak ditemukan.', $taskId));
            }

            if ($lockedTask->status !== $expectedStatus) {
                throw new DomainException(
                    sprintf(
                        'Konflik status konkurensi: Task [%s] berstatus [%s], diharapkan [%s]. Aksi dibatalkan demi integritas.',
                        $taskId,
                        $lockedTask->status,
                        $expectedStatus
                    )
                );
            }

            return $operation($lockedTask);
        });
    }
}
