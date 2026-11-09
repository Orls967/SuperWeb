<?php

declare(strict_types=1);

namespace Modules\Hospital\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hospital\Domain\Models\MedicalWasteManifest;
use Modules\Hospital\Domain\Services\FestronikRegulatoryComplianceEngine;
use Modules\Hospital\Domain\Services\MedicalWasteManifestService;

class FestronikAuditCommand extends Command
{
    protected $signature = 'hsp:festronik-scan {--limit=25 : Batas jumlah manifest yang dipindai}';

    protected $description = 'Audit kepatuhan regulasi TPS B3 dan verifikasi integritas rantai segel hash Festronik';

    public function handle(
        MedicalWasteManifestService $wasteService,
        FestronikRegulatoryComplianceEngine $complianceEngine
    ): int {
        $this->info('Memulai Audit Festronik & Rantai Kustodi Limbah Medis B3...');

        $limit = max(1, (int) $this->option('limit'));
        $manifests = MedicalWasteManifest::with('custodyLogs')->latest('id')->limit($limit)->get();

        if ($manifests->isEmpty()) {
            $this->warn('Tidak ada manifest limbah B3 ditemukan.');

            return self::SUCCESS;
        }

        $rows = [];
        $corruptCount = 0;
        $overdueCount = 0;

        foreach ($manifests as $mnf) {
            $isChainValid = $wasteService->verifyCustodyChainIntegrity($mnf);
            if (! $isChainValid) {
                $corruptCount++;
            }

            // Hitung durasi sejak dibuat
            $hoursStored = (int) $mnf->created_at->diffInHours(now());
            $compliance = $complianceEngine->verifyTpsStorageCompliance(
                wasteCategory: $mnf->waste_category,
                tpsStorageTempCelsius: 25.0, // Asumsi suhu ambient
                storedDurationHours: $hoursStored
            );

            if (! $compliance['is_compliant']) {
                $overdueCount++;
            }

            $rows[] = [
                $mnf->manifest_number,
                $mnf->waste_category,
                number_format((float) $mnf->weight_kg, 2).' kg',
                $mnf->status,
                $isChainValid ? '<info>VALID / INTACT</info>' : '<error>TAMPERED</error>',
                $compliance['compliance_status'],
            ];
        }

        $this->table(
            ['Nomor Manifest', 'Kategori', 'Berat', 'Status', 'Integritas Segel SHA-256', 'Kepatuhan Permen LHK'],
            $rows
        );

        if ($corruptCount > 0) {
            $this->error("Peringatan Keamanan: Terdeteksi {$corruptCount} manifest dengan rantai hash tidak cocok!");

            return self::FAILURE;
        }

        $this->info('Audit Festronik Selesai: '.count($rows).' manifest diaudit. Seluruh rantai segel digital valid.');

        return self::SUCCESS;
    }
}
