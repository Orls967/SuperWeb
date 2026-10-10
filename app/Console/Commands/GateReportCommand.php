<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Quality\Gate\GateReportGenerator;
use App\Quality\Gate\JUnitParser;
use Illuminate\Console\Command;
use Throwable;

/**
 * Generates official quality gate reports docs/gates/fase-{N}.md (PROGRESS R0.2, K-02, K-04).
 *
 * Enforces that reports cannot be generated when the gate fails (test failures in JUnit).
 */
class GateReportCommand extends Command
{
    protected $signature = 'gate:report
        {--fase= : Identifikasi fase (mis. R0, 1, 87)}
        {--junit=storage/logs/pest-junit.xml : Path ke file log JUnit XML relatif ke base_path}
        {--output= : Path file output markdown (bawaan: docs/gates/fase-{fase}.md)}
        {--force : Paksa buat laporan meskipun ada kegagalan test di JUnit (hanya untuk pengujian)}
        {--dry-run : Cetak laporan ke stdout tanpa menulis ke file}';

    protected $description = 'Hasilkan laporan resmi quality gate docs/gates/fase-{N}.md dari output asli test suite';

    public function handle(): int
    {
        $phaseId = (string) $this->option('fase');
        if (trim($phaseId) === '') {
            $this->error('Opsi --fase= wajib diisi (mis. --fase=R0 atau --fase=87).');

            return self::FAILURE;
        }

        $phaseId = trim($phaseId);
        $junitRel = (string) $this->option('junit');
        $junitPath = str_starts_with($junitRel, '/') ? $junitRel : base_path($junitRel);

        if (! is_file($junitPath)) {
            $this->error("File log JUnit XML tidak ditemukan di: {$junitPath}\nJalankan 'composer gate' terlebih dahulu untuk menghasilkan output pengujian asli.");

            return self::FAILURE;
        }

        try {
            $junitSummary = JUnitParser::parseFile($junitPath);
        } catch (Throwable $e) {
            $this->error("Gagal mem-parse JUnit XML: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! $junitSummary->isClean() && ! $this->option('force')) {
            $this->line("Quality gate GAGAL: JUnit mencatat {$junitSummary->totalFailures} kegagalan dan {$junitSummary->totalErrors} error.");
            $this->error('Laporan resmi gate tidak dapat dibuat bila test suite gagal (PROGRESS R0.2).');

            return self::FAILURE;
        }

        $generator = new GateReportGenerator(base_path());
        $reportContent = $generator->generate($phaseId, $junitSummary);

        if ($this->option('dry-run')) {
            foreach (explode("\n", $reportContent) as $reportLine) {
                $this->line($reportLine);
            }

            return self::SUCCESS;
        }

        $outputOption = (string) $this->option('output');
        $outputPath = $outputOption !== ''
            ? (str_starts_with($outputOption, '/') ? $outputOption : base_path($outputOption))
            : base_path('docs/gates/fase-'.strtolower($phaseId).'.md');

        $dir = dirname($outputPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($outputPath, $reportContent);
        $this->info("Laporan quality gate fase {$phaseId} berhasil ditulis ke: {$outputPath}");

        return self::SUCCESS;
    }
}
