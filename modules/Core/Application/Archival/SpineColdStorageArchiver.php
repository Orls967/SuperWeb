<?php

declare(strict_types=1);

namespace Modules\Core\Application\Archival;

use Illuminate\Support\Facades\File;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Pengarsip Penyimpanan Dingin Event Spine (SpineColdStorageArchiver)
 *
 * Mengekspor batch event historis yang telah terkirim ke berkas arsip berformat NDJSON
 * (Newline Delimited JSON) dengan hash SHA-256 untuk retensi jangka panjang berbiaya rendah.
 */
class SpineColdStorageArchiver
{
    public function __construct(
        protected string $archiveBasePath = ''
    ) {
        $this->archiveBasePath = $archiveBasePath ?: storage_path('app/spine_archives');
    }

    /**
     * Mengarsipkan event spine berdasarkan rentang ID ke dalam berkas terindeks.
     *
     * @return array{
     *     archive_file: string,
     *     record_count: int,
     *     from_id: int,
     *     to_id: int,
     *     sha256_checksum: string,
     *     file_size_bytes: int
     * }
     */
    public function archiveRange(int $fromId, int $toId, ?string $topic = null): array
    {
        if (! File::isDirectory($this->archiveBasePath)) {
            File::makeDirectory($this->archiveBasePath, 0755, true);
        }

        $events = SimEventSpine::query()
            ->whereBetween('id', [$fromId, $toId])
            ->when($topic !== null, fn ($q) => $q->where('topic', $topic))
            ->orderBy('id', 'asc')
            ->get();

        $count = $events->count();
        $timestamp = date('Ymd_His');
        $topicSlug = $topic ? str_replace('.', '_', $topic) : 'all';
        $fileName = "spine_archive_{$topicSlug}_{$fromId}_{$toId}_{$timestamp}.ndjson";
        $filePath = "{$this->archiveBasePath}/{$fileName}";

        $handle = fopen($filePath, 'w');
        if (! $handle) {
            throw new \RuntimeException("Gagal membuka berkas arsip untuk penulisan: {$filePath}");
        }

        foreach ($events as $event) {
            $line = json_encode([
                'id' => $event->id,
                'event_id' => $event->event_id,
                'topic' => $event->topic,
                'aggregate_type' => $event->aggregate_type,
                'aggregate_id' => $event->aggregate_id,
                'sequence' => $event->sequence,
                'payload' => $event->payload,
                'created_at' => $event->created_at?->toIso8601String(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            fwrite($handle, $line . "\n");
        }

        fclose($handle);

        $checksum = (string) hash_file('sha256', $filePath);
        $fileSize = (int) filesize($filePath);

        return [
            'archive_file' => $filePath,
            'record_count' => $count,
            'from_id' => $fromId,
            'to_id' => $toId,
            'sha256_checksum' => $checksum,
            'file_size_bytes' => $fileSize,
        ];
    }
}
