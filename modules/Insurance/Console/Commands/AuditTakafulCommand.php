<?php

declare(strict_types=1);

namespace Modules\Insurance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Insurance\Application\Services\TakafulAuditor;
use Throwable;

final class AuditTakafulCommand extends Command
{
    protected $signature = 'ins:takaful-audit';

    protected $description = 'Menjalankan audit dua-sumber untuk Takaful Window dan Agri Insurance (Fase 160)';

    public function handle(TakafulAuditor $auditor): int
    {
        $this->info('Memulai audit dua-sumber Takaful Window & Agri-Insurance...');

        try {
            $report = $auditor->audit();

            $this->info('Audit Takaful & Agri BERSIH (exit 0). Seluruh invarian alokasi tabarru & offset pinjaman terpenuhi.');
            $this->table(['Metrik', 'Jumlah'], [
                ['Peserta Takaful', $report['total_participants']],
                ['Catatan Surplus', $report['total_surplus_records']],
                ['Klaim Pertanian', $report['total_agri_claims']],
                ['Mismatch Rasio Kontribusi', $report['contribution_ratio_mismatches']],
                ['Mismatch Alokasi Surplus', $report['surplus_allocation_mismatches']],
                ['Mismatch Offset Klaim Agri', $report['agri_claim_offset_mismatches']],
            ]);

            return 0;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }
}
