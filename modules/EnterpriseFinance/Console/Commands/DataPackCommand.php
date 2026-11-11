<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Console\Commands;

use Illuminate\Console\Command;
use Modules\EnterpriseFinance\Application\Services\AuditDataPackGeneratorService;

class DataPackCommand extends Command
{
    protected $signature = 'enterprise:data-pack {period? : Periode fiskal YYYY-MM} {--entity=CORP-HOLDING : Kode entitas} {--out= : Direktori keluaran}';

    protected $description = 'Menghasilkan paket bukti audit KAP berformat ZIP beserta manifest ringkasan SHA-256';

    public function handle(AuditDataPackGeneratorService $service): int
    {
        $period = (string) ($this->argument('period') ?: date('Y-m', strtotime('-1 month')));
        $entity = (string) $this->option('entity');
        $out = $this->option('out') ? (string) $this->option('out') : null;

        $this->info("Membangun paket data bukti audit untuk entitas {$entity} periode {$period}...");

        $pack = $service->generateAuditPack($entity, $period, $out);

        $this->info("Paket data audit berhasil dibuat: {$pack['zip_path']}");
        $this->line("Jumlah berkas: {$pack['file_count']}");

        $manifestRows = [];
        foreach ($pack['manifest'] as $file => $sha) {
            $manifestRows[] = [$file, $sha];
        }
        $this->table(['Nama Berkas', 'SHA-256 Checksum'], $manifestRows);

        // Verifikasi integritas seketika
        $service->verifyManifest($pack['zip_path']);
        $this->info('Verifikasi integritas manifest SHA-256 lulus (100% cocok).');

        return Command::SUCCESS;
    }
}

