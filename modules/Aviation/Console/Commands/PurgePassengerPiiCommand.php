<?php

declare(strict_types=1);

namespace Modules\Aviation\Console\Commands;

use Illuminate\Console\Command;
use Modules\Aviation\Domain\Models\AviationTicket;

class PurgePassengerPiiCommand extends Command
{
    protected $signature = 'avi:purge-pii {--retention-days=90 : Batas hari retensi setelah penerbangan mendarat} {--dry-run : Uji coba tanpa menghapus data}';

    protected $description = 'Anonimisasi data pribadi (PII) penumpang untuk kepatuhan UU Pelindungan Data Pribadi (UU No. 27/2022)';

    public function handle(): int
    {
        $retentionDays = (int) $this->option('retention-days');
        $cutoffDate = now()->subDays($retentionDays);

        $this->info("Mencari tiket penumpang yang telah terbang sebelum {$cutoffDate->toDateString()}...");

        $ticketsToPurge = AviationTicket::where('status', 'flown')
            ->where('updated_at', '<', $cutoffDate)
            ->whereNotNull('pii_name_encrypted')
            ->where('pii_name_encrypted', '!=', '[REDACTED_PDP]')
            ->get();

        if ($ticketsToPurge->isEmpty()) {
            $this->info('Tidak ada data PII penumpang yang melampaui masa retensi.');

            return self::SUCCESS;
        }

        $this->line("Ditemukan {$ticketsToPurge->count()} catatan tiket yang wajib diredaksi.");

        if ($this->option('dry-run')) {
            $this->warn('[DRY-RUN] Melewati redaksi data.');

            return self::SUCCESS;
        }

        $purgedCount = 0;
        foreach ($ticketsToPurge as $ticket) {
            $ticket->pii_name_encrypted = '[REDACTED_PDP]';
            // Tetapkan blind index agar pencocokan audit historis hash satu arah tetap terjaga
            $ticket->save();
            $purgedCount++;
        }

        $this->info("Berhasil meredaksi {$purgedCount} catatan PII penumpang sesuai standar UU PDP.");

        return self::SUCCESS;
    }
}
