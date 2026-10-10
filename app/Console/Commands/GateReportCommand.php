<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Quality\Gate\GateManifest;
use App\Quality\Gate\GateReportGenerator;
use App\Quality\Gate\JUnitParser;
use Illuminate\Console\Command;
use Throwable;

/**
 * Generates official quality gate reports docs/gates/fase-{N}.md (PROGRESS R0.2, K-02, K-04).
 *
 * Enforces strict rejection rules:
 * - Manifest exists and valid
 * - All mandatory steps present
 * - Gate steps all passed (exit code 0)
 * - Manifest commit matches HEAD
 * - Working tree was clean when gate ran
 * - JUnit XML exists, valid, and clean (no failures or errors)
 * - All tests cited in target phase proof blocks passed in JUnit
 */
final class GateReportCommand extends Command
{
    protected $signature = 'gate:report
        {--fase= : Identifikasi fase (mis. R0, 1, 87)}
        {--manifest=storage/logs/gate-manifest.json : Path ke file manifest gate}
        {--junit=storage/logs/pest-junit.xml : Path ke file log JUnit XML}
        {--output= : Path file output markdown (bawaan: docs/gates/fase-{fase}.md)}
        {--force : Abaikan penolakan (hanya untuk keperluan pengujian khusus)}
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
        $root = base_path();
        $isForce = (bool) $this->option('force');

        // 1. Validasi manifest
        $manifestRel = (string) $this->option('manifest');
        $manifestPath = str_starts_with($manifestRel, '/') ? $manifestRel : base_path($manifestRel);

        if (! is_file($manifestPath)) {
            $this->error("File gate manifest tidak ditemukan di: {$manifestPath}\nJalankan 'composer gate' terlebih dahulu.");

            return self::FAILURE;
        }

        $manifest = GateManifest::fromFile($manifestPath);
        if ($manifest === null) {
            $this->error("Format gate manifest rusak atau tidak valid di: {$manifestPath}");

            return self::FAILURE;
        }

        // 2. Validasi langkah wajib di manifest
        $missingSteps = $manifest->missingRequiredSteps();
        if ($missingSteps !== [] && ! $isForce) {
            $this->error('Langkah wajib gate hilang dari manifest: '.implode(', ', $missingSteps));

            return self::FAILURE;
        }

        // 3. Validasi kebersihan hasil eksekusi gate
        if (! $manifest->isClean() && ! $isForce) {
            $failedStep = $manifest->firstFailedStep();
            $stepName = $failedStep['name'] ?? 'tidak diketahui';
            $exitCode = $failedStep['exit_code'] ?? 1;
            $this->error("Quality gate GAGAL: langkah '{$stepName}' gagal dengan exit code {$exitCode}.");

            return self::FAILURE;
        }

        // 4. Validasi commit manifest vs HEAD
        $headCommit = $this->resolveCurrentHeadCommit($root);
        if (! $manifest->matchesCommit($headCommit) && ! $isForce) {
            $this->error("Commit pada manifest ({$manifest->commit}) tidak cocok dengan HEAD ({$headCommit}). Laporan gate basi.");

            return self::FAILURE;
        }

        // 5. Validasi kebersihan working tree saat gate berjalan
        if (! $manifest->isWorkingTreeClean() && ! $isForce) {
            $this->error('Gate dijalankan pada working tree yang kotor (dirty). Laporan hanya sah dari commit bersih.');

            return self::FAILURE;
        }

        // 6. Validasi JUnit XML
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

        if (! $junitSummary->isClean() && ! $isForce) {
            $this->error("Quality gate GAGAL: JUnit mencatat {$junitSummary->totalFailures} kegagalan dan {$junitSummary->totalErrors} error.");

            return self::FAILURE;
        }

        // 7. Validasi status test blok Bukti fase target
        $generator = new GateReportGenerator($root);
        $phaseBuktiTests = $generator->extractPhaseBuktiTests($root, $phaseId, $junitSummary);

        if (! $isForce) {
            foreach ($phaseBuktiTests as $bt) {
                if ($bt['status'] !== 'PASS') {
                    $this->error("Test bukti '{$bt['testName']}' pada item {$bt['item']} gagal atau tidak ditemukan di JUnit ({$bt['status']}).");

                    return self::FAILURE;
                }
            }
        }

        // Seluruh penolakan lolos, generate laporan
        $reportContent = $generator->generate(
            phaseId: $phaseId,
            junitSummary: $junitSummary,
            manifest: $manifest,
        );

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

    private function resolveCurrentHeadCommit(string $root): string
    {
        $hash = shell_exec('git -C '.escapeshellarg($root).' rev-parse HEAD 2>/dev/null');
        if ($hash !== null && trim($hash) !== '') {
            return trim($hash);
        }

        return '0000000000000000000000000000000000000000';
    }
}
