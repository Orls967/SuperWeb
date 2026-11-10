<?php

declare(strict_types=1);

namespace Modules\Core\Application\Archival;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Pemulih Arsip Penyimpanan Dingin (SpineArchiveRestorer)
 *
 * Membaca kembali berkas arsip NDJSON dari cold storage dan memverifikasi integritas checksum
 * sebelum menyisipkan kembali pesan ke dalam aliran `sim_event_spine` untuk audit retroaktif.
 */
class SpineArchiveRestorer
{
    /**
     * Memulihkan data dari berkas arsip ke tabel event spine.
     *
     * @return array{
     *     archive_file: string,
     *     verified: bool,
     *     records_read: int,
     *     records_inserted: int,
     *     records_skipped: int
     * }
     */
    public function restoreFromFile(string $archiveFilePath, ?string $expectedChecksum = null): array
    {
        if (! file_exists($archiveFilePath)) {
            throw new \InvalidArgumentException("Berkas arsip tidak ditemukan: {$archiveFilePath}");
        }

        if ($expectedChecksum !== null) {
            $actualChecksum = hash_file('sha256', $archiveFilePath);
            if (! hash_equals($expectedChecksum, $actualChecksum)) {
                throw new \RuntimeException("Checksum integritas tidak cocok! Arsip berpotensi korup.");
            }
        }

        $handle = fopen($archiveFilePath, 'r');
        if (! $handle) {
            throw new \RuntimeException("Tidak dapat membaca berkas arsip: {$archiveFilePath}");
        }

        $recordsRead = 0;
        $inserted = 0;
        $skipped = 0;

        DB::beginTransaction();

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $recordsRead++;
                $data = json_decode($line, true);
                if (! is_array($data)) {
                    continue;
                }

                $eventId = $data['event_id'] ?? null;
                $exists = false;
                if ($eventId) {
                    $exists = SimEventSpine::query()->where('event_id', $eventId)->exists();
                }

                if ($exists) {
                    $skipped++;
                    continue;
                }

                SimEventSpine::create([
                    'event_id' => $data['event_id'] ?? (string) \Illuminate\Support\Str::uuid(),
                    'topic' => $data['topic'] ?? 'restored.archive.event',
                    'aggregate_type' => $data['aggregate_type'] ?? null,
                    'aggregate_id' => $data['aggregate_id'] ?? null,
                    'sequence' => (int) ($data['sequence'] ?? 1),
                    'payload' => $data['payload'] ?? [],
                    'created_at' => $data['created_at'] ?? now(),
                ]);

                $inserted++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            throw $e;
        }

        fclose($handle);

        return [
            'archive_file' => $archiveFilePath,
            'verified' => true,
            'records_read' => $recordsRead,
            'records_inserted' => $inserted,
            'records_skipped' => $skipped,
        ];
    }
}
