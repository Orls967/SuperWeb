<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\EventSchema\EventSchemaEvolutionSimulator;

/**
 * Perintah Simulasi Evolusi Skema Event (SpineSchemaEvolutionCommand)
 *
 * Menguji apakah file skema JSON baru memenuhi aturan aditif A6 dan aman
 * terhadap sampel event historis sebelum diajukan ke pipeline produksi.
 */
class SpineSchemaEvolutionCommand extends Command
{
    protected $signature = 'spine:schema-evolve
                            {--topic= : Nama topik event yang diuji}
                            {--schema-file= : Path berkas JSON definisi skema baru}
                            {--samples=50 : Jumlah sampel event historis yang diuji}
                            {--json : Tampilkan keluaran dalam format JSON murni}';

    protected $description = 'Menyimulasikan dampak perubahan skema terhadap data historis dan konsumen terdaftar';

    public function handle(EventSchemaEvolutionSimulator $simulator): int
    {
        $topic = (string) $this->option('topic');
        $schemaFile = (string) $this->option('schema-file');
        $samples = max(5, (int) $this->option('samples'));
        $asJson = (bool) $this->option('json');

        if (empty($topic)) {
            $this->error('Opsi --topic wajib ditentukan.');

            return self::FAILURE;
        }

        if (empty($schemaFile) || ! file_exists($schemaFile)) {
            $this->error("Berkas skema tidak ditemukan pada path: '{$schemaFile}'.");

            return self::FAILURE;
        }

        $rawContent = (string) file_get_contents($schemaFile);
        $proposedSchema = json_decode($rawContent, true);

        if (! is_array($proposedSchema)) {
            $this->error('Berkas skema tidak memuat JSON yang valid.');

            return self::FAILURE;
        }

        $this->info("Menjalankan simulasi evolusi skema untuk topik '{$topic}'...");

        $result = $simulator->simulate($topic, $proposedSchema, $samples);

        if ($asJson) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $result['status'] === 'BREAKING_BLOCKED' ? self::FAILURE : self::SUCCESS;
        }

        $this->table(
            ['Parameter Simulasi', 'Hasil'],
            [
                ['Topik', $topic],
                ['Status Evaluasi', $result['status']],
                ['Kompatibilitas Aditif (A6)', $result['compatibility_check']['compatible'] ? 'LOLOS (KOMPATIBEL)' : 'GAGAL (BREAKING)'],
                ['Total Sampel Historis Diuji', $result['historical_validation']['total_sampled']],
                ['Sampel Lolos Validasi', $result['historical_validation']['passed_count']],
                ['Sampel Gagal Validasi', $result['historical_validation']['failed_count']],
                ['Rekomendasi', $result['recommendation']],
            ]
        );

        if (! empty($result['compatibility_check']['violations'])) {
            $this->newLine();
            $this->error('Pelanggaran Aturan Aditif A6 Ditemukan:');
            foreach ($result['compatibility_check']['violations'] as $v) {
                $this->warn("  * {$v}");
            }
        }

        $this->newLine();
        $this->info('Dampak Konsumen:');
        $impactRows = [];
        foreach ($result['consumer_impact'] as $consumer => $impact) {
            $impactRows[] = [$consumer, $impact['status'], $impact['reason']];
        }
        $this->table(['Konsumen', 'Status', 'Analisis Risiko'], $impactRows);

        if ($result['status'] === 'BREAKING_BLOCKED') {
            $this->error('Simulasi GAGAL: Usulan skema ditolak oleh validator arsitektur.');

            return self::FAILURE;
        }

        $this->info('Simulasi BERHASIL: Skema baru aman untuk diterapkan.');

        return self::SUCCESS;
    }
}

