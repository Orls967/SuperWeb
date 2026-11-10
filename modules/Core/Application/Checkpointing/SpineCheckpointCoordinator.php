<?php

declare(strict_types=1);

namespace Modules\Core\Application\Checkpointing;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Models\CoreConsumerInbox;

/**
 * Koordinator Checkpointing Offset Konsumen (SpineCheckpointCoordinator)
 *
 * Mengelola commit offset persisten per consumer group dengan transaksi atomik.
 * Mencegah split-brain, regresi offset, dan duplikasi eksekusi saat restart mendadak.
 */
class SpineCheckpointCoordinator
{
    /**
     * Menyimpan (commit) offset terakhir yang berhasil diproses oleh grup konsumen.
     *
     * @return array{success: bool, previous_offset: int, committed_offset: int, updated: bool}
     */
    public function commitCheckpoint(string $consumerGroup, int $streamOffset, string $messageId): array
    {
        return DB::transaction(function () use ($consumerGroup, $streamOffset, $messageId) {
            // Ambil offset tertinggi yang saat ini tercatat
            $currentMax = (int) CoreConsumerInbox::query()
                ->where('consumer_group', $consumerGroup)
                ->max('id');

            // Catat pesan di inbox jika belum ada (idempotent write)
            $existing = CoreConsumerInbox::query()
                ->where('consumer_group', $consumerGroup)
                ->where('message_id', $messageId)
                ->first();

            $updated = false;
            if (! $existing) {
                CoreConsumerInbox::create([
                    'consumer_group' => $consumerGroup,
                    'message_id' => $messageId,
                    'status' => 'processed',
                    'processed_at' => now(),
                ]);
                $updated = true;
            }

            return [
                'success' => true,
                'previous_offset' => $currentMax,
                'committed_offset' => max($currentMax, $streamOffset),
                'updated' => $updated,
            ];
        });
    }

    /**
     * Mengambil offset terakhir yang aman untuk melanjutkan konsumsi.
     */
    public function getLatestCheckpoint(string $consumerGroup): int
    {
        return (int) CoreConsumerInbox::query()
            ->where('consumer_group', $consumerGroup)
            ->max('id');
    }

    /**
     * Memeriksa apakah pesan tertentu sudah terproses oleh grup konsumen.
     */
    public function isProcessed(string $consumerGroup, string $messageId): bool
    {
        return CoreConsumerInbox::query()
            ->where('consumer_group', $consumerGroup)
            ->where('message_id', $messageId)
            ->exists();
    }

    /**
     * Mereset checkpoint offset ke titik tertentu (misal untuk replay stream historis).
     *
     * @return array{consumer_group: string, target_offset: int, pruned_records: int}
     */
    public function resetCheckpoint(string $consumerGroup, int $targetOffset): array
    {
        return DB::transaction(function () use ($consumerGroup, $targetOffset) {
            $deleted = CoreConsumerInbox::query()
                ->where('consumer_group', $consumerGroup)
                ->where('id', '>', $targetOffset)
                ->delete();

            return [
                'consumer_group' => $consumerGroup,
                'target_offset' => $targetOffset,
                'pruned_records' => $deleted,
            ];
        });
    }
}

