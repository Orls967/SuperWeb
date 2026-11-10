<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Archival\SpineColdStorageArchiver;

/**
 * Perintah Pengarsipan Dingin Event Spine (SpineArchiveCommand)
 *
 * Mengekspor batch event terkirim ke berkas NDJSON cold storage dengan checksum SHA-256.
 */
class SpineArchiveCommand extends Command
{
    protected $signature = 'spine:archive
                            {--from=1 : ID awal event stream}
                            {--to=1000 : ID akhir event stream}
                            {--topic= : Filter topik tertentu (opsional)}
                            {--json : Tampilkan keluaran dalam format JSON murni}';

    protected $description = 'Mengarsipkan rentang event spine historis ke berkas NDJSON cold storage';

    public function handle(SpineColdStorageArchiver $archiver): int
    {
        $fromId = max(1, (int) $this->option('from'));
        $toId = max($fromId, (int) $this->option('to'));
        $topic = $this->option('topic') ? (string) $this->option('topic') : null;
        $asJson = (bool) $this->option('json');

        $this->info("Memulai pengarsipan event spine dari ID {$fromId} s.d. {$toId}...");

        $result = $archiver->archiveRange($fromId, $toId, $topic);

        if ($asJson) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            ['Metrik Pengarsipan', 'Nilai'],
            [
                ['Berkas Arsip', $result['archive_file']],
                ['Jumlah Rekaman Disimpan', number_format($result['record_count'])],
                ['Rentang ID', "{$result['from_id']} - {$result['to_id']}"],
                ['Ukuran Berkas (Bytes)', number_format($result['file_size_bytes'])],
                ['SHA-256 Checksum', $result['sha256_checksum']],
            ]
        );

        $this->info('Pengarsipan ke cold storage berhasil diselesaikan.');

        return self::SUCCESS;
    }
}
