<?php

declare(strict_types=1);

namespace App\Quality\Gate;

use App\Quality\Progress\ProgressIntegrityScanner;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Generates quality gate report docs/gates/fase-{N}.md from genuine tool outputs (PROGRESS R0.2).
 */
final class GateReportGenerator
{
    public function __construct(
        private readonly ?string $repoRoot = null,
    ) {}

    /**
     * Generates gate report markdown content.
     *
     * @param  array<string, int>  $commandExitCodes  Map of P4 command to exit code
     */
    public function generate(
        string $phaseId,
        JUnitSummary $junitSummary,
        array $commandExitCodes = [],
        ?string $commitHash = null,
        ?string $timestamp = null,
        ?GateManifest $manifest = null,
        ?string $existingContent = null,
        ?string $progressPath = null,
    ): string {
        $root = $this->repoRoot ?? (function_exists('app') && app()->has('path.base') ? base_path() : dirname(__DIR__, 3));

        $commit = $commitHash ?? ($manifest !== null ? $manifest->commit : $this->resolveCommitHash($root));
        $shortCommit = substr($commit, 0, 7);
        $date = $timestamp ?? ($manifest !== null ? $manifest->timestamp : date('Y-m-d H:i:s T'));
        $phpVersion = PHP_VERSION;
        $dbInfo = $this->resolveDatabaseInfo();

        $gateStatus = ($junitSummary->isClean() && ($manifest === null || $manifest->isClean())) ? 'PASS 🟢' : 'FAIL 🔴';

        // Extract Bukti tests for the target phase
        $buktiTests = $this->extractPhaseBuktiTests($root, $phaseId, $junitSummary, $progressPath);

        // Architecture scan summary
        $archSummary = $this->resolveArchScanSummary($root);

        // Build Markdown
        $md = [];
        $md[] = "# Quality Gate Report: Fase {$phaseId}";
        $md[] = '';
        $md[] = "- **Commit:** `{$commit}` ({$shortCommit})";
        $md[] = "- **Tanggal:** {$date}";
        $md[] = "- **PHP:** {$phpVersion}";
        $md[] = "- **Database:** {$dbInfo}";
        $md[] = "- **Status Keseluruhan:** {$gateStatus}";
        $md[] = '';
        $md[] = '## 1. Ringkasan Test Suite (Pest / JUnit)';
        $md[] = "- **Total Test:** {$junitSummary->totalTests}";
        $md[] = "- **Total Assertion:** {$junitSummary->totalAssertions}";
        $md[] = '- **Durasi:** '.number_format($junitSummary->duration, 2).'s';
        $md[] = "- **Gagal / Error:** {$junitSummary->totalFailures} failures, {$junitSummary->totalErrors} errors";
        $md[] = '';
        $md[] = '## 2. Hasil Perintah Protokol P4';
        $md[] = '| Perintah | Deskripsi | Exit Code | Status |';
        $md[] = '|---|---|---|---|';

        if ($manifest !== null && ! empty($manifest->steps)) {
            foreach ($manifest->steps as $step) {
                $code = (int) ($step['exit_code'] ?? 0);
                $status = $code === 0 ? 'PASS 🟢' : "FAIL 🔴 (code {$code})";
                $name = (string) ($step['name'] ?? '');
                $cmd = (string) ($step['command'] ?? $name);
                $dur = isset($step['duration']) ? ' ('.number_format((float) $step['duration'], 2).'s)' : '';
                $md[] = "| `{$cmd}` | Langkah gate: {$name}{$dur} | {$code} | {$status} |";
            }
        } else {
            $defaultP4 = [
                'composer gate' => 'Test suite penuh + lint Pint + arch test + npm build',
                'php artisan arch:scan' => 'Pemindaian aturan arsitektur A1–A13 (ratchet)',
                'php artisan bank:reconcile' => 'Rekonsiliasi double-entry ledger & bank',
                'php artisan chain:audit-all' => 'Audit integritas semua rantai transaksi',
                'php artisan super:health-check' => 'Pemeriksaan kesehatan sistem pilar',
            ];

            foreach ($defaultP4 as $cmd => $desc) {
                $code = $commandExitCodes[$cmd] ?? 0;
                $status = $code === 0 ? 'PASS 🟢' : "FAIL 🔴 (code {$code})";
                $md[] = "| `{$cmd}` | {$desc} | {$code} | {$status} |";
            }
        }

        $md[] = '';
        $md[] = "## 3. Status Test Blok Bukti Fase {$phaseId}";
        if ($buktiTests === []) {
            $md[] = "_Tidak ada rujukan test spesifik pada blok Bukti: fase {$phaseId} (atau fase belum selesai)._";
        } else {
            $md[] = '| Item | Nama Test | File | Status (JUnit) | Durasi |';
            $md[] = '|---|---|---|---|---|';
            foreach ($buktiTests as $bt) {
                $statusIcon = $bt['status'] === 'PASS' ? 'PASS 🟢' : ($bt['status'] === 'FAIL' ? 'FAIL 🔴' : "{$bt['status']} ⚠️");
                $timeSec = number_format($bt['time'], 3).'s';
                $md[] = "| {$bt['item']} | `{$bt['testName']}` | `{$bt['file']}` | {$statusIcon} | {$timeSec} |";
            }
        }

        $md[] = '';
        $md[] = '## 4. Ringkasan Arsitektur (`arch:scan`)';
        $md[] = $archSummary;

        $existingVerification = $this->extractExistingVerification($existingContent);
        $md[] = '';
        if ($existingVerification !== null) {
            $md[] = $existingVerification;
        } else {
            $md[] = '## 5. Verifikasi';
            $md[] = '*(Bagian ini wajib diisi oleh verifikator independen sebelum mengubah status fase ke ✅ sesuai P7).*';
            $md[] = '';
            $md[] = '- **Tanggal:** ';
            $md[] = '- **Verifikator:** ';
            $md[] = '- **Commit yang diverifikasi:** ';
            $md[] = '- **Keputusan:** BELUM DIVERIFIKASI';
            $md[] = '';
            $md[] = '| # | Hasil | Bukti (command / commit / output) | Catatan |';
            $md[] = '|---|---|---|---|';
            for ($i = 1; $i <= 14; $i++) {
                $md[] = "| C{$i} | BELUM DIVERIFIKASI | | |";
            }
            $md[] = '';
            $md[] = '**Minus yang ditambahkan verifikator:** ';
            $md[] = '**Alasan 🔁 (bila ada):** ';
        }
        $md[] = '';

        return implode("\n", $md)."\n";
    }

    private function extractExistingVerification(?string $content): ?string
    {
        if ($content === null || $content === '') {
            return null;
        }

        if (preg_match('/(^#{1,3}\s+.*Verifikasi.*$)/mi', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1];

            return trim(substr($content, $pos));
        }

        return null;
    }

    private function resolveCommitHash(string $root): string
    {
        $hash = shell_exec('git -C '.escapeshellarg($root).' rev-parse HEAD 2>/dev/null');
        if ($hash !== null && trim($hash) !== '') {
            return trim($hash);
        }

        return '0000000000000000000000000000000000000000';
    }

    private function resolveDatabaseInfo(): string
    {
        if (! class_exists(DB::class)) {
            return 'SQLite (in-memory / test)';
        }

        try {
            $connection = config('database.default', 'sqlite');
            $pdo = DB::connection()->getPdo();
            $serverVersion = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);

            return "{$connection} (v{$serverVersion})";
        } catch (Throwable) {
            return (string) config('database.default', 'sqlite');
        }
    }

    /**
     * @return list<array{item: string, testName: string, file: string, status: string, time: float}>
     */
    public function extractPhaseBuktiTests(string $root, string $phaseId, JUnitSummary $junitSummary, ?string $progressPath = null): array
    {
        $filePath = $progressPath ?? ($root.'/docs/PROGRESS.md');
        if (! is_file($filePath)) {
            return [];
        }

        $scanner = new ProgressIntegrityScanner;
        $phases = $scanner->parse((string) file_get_contents($filePath));

        $targetPhase = $phases[$phaseId] ?? null;
        if ($targetPhase === null) {
            // Case-insensitive fallback
            foreach ($phases as $id => $p) {
                if (strcasecmp($id, $phaseId) === 0) {
                    $targetPhase = $p;
                    break;
                }
            }
        }

        if ($targetPhase === null) {
            return [];
        }

        $results = [];

        foreach ($targetPhase->items as $item) {
            if (empty($item->proof['test'])) {
                continue;
            }

            foreach ($item->proof['test'] as $testEntry) {
                $parts = explode('::', $testEntry, 2);
                $file = $parts[0];
                $name = $parts[1] ?? basename($file);

                $match = $junitSummary->findTestCase($name, $file);

                $results[] = [
                    'item' => $item->id,
                    'testName' => $name,
                    'file' => $file,
                    'status' => $match['status'] ?? 'NOT_RECORDED',
                    'time' => $match['time'] ?? 0.0,
                ];
            }
        }

        return $results;
    }

    private function resolveArchScanSummary(string $root): string
    {
        $baselinePath = $root.'/tests/Architecture/baselines/arch-scan.json';
        if (! is_file($baselinePath)) {
            return '- Baseline `arch-scan.json`: Tidak ditemukan.';
        }

        $base = json_decode((string) file_get_contents($baselinePath), true) ?: [];
        $rules = $base['rules'] ?? [];
        $ruleDescriptions = [
            'A1' => 'Import/penggunaan Domain modul lain',
            'A2' => 'Tabel berprefiks modul lain / tidak terdaftar',
            'A3' => 'Uang bertipe float / kolom IDR decimal',
            'A4' => 'Idempotency key dari nilai acak/waktu',
            'A5' => 'Tipe posting ledger > 32 char / di luar registry',
            'A6' => 'Parameter bool kontrol di Application/',
            'A7' => 'Hash tanpa kunci untuk data sensitif',
            'A8' => 'Carbon/Date::setTestNow di produksi',
            'A9' => 'Kernel bergantung pada modul bisnis',
            'A10' => 'Model dengan $guarded = []',
            'A11' => 'File PHP tanpa declare(strict_types=1)',
            'A12' => 'Kolom *_id di migrasi tanpa FK',
            'A13' => 'back()->errors() (HTTP 500 di jalur galat)',
        ];

        $totalViolations = 0;
        $tableRows = [];

        foreach ($rules as $rule => $data) {
            $count = (int) ($data['total'] ?? 0);
            $totalViolations += $count;
            $desc = $ruleDescriptions[$rule] ?? "Aturan arsitektur {$rule}";
            $tableRows[] = "| `{$rule}` | {$desc} | {$count} |";
        }

        $lines = [];
        $lines[] = "- **Status Baseline:** Terpasang ({$totalViolations} pelanggaran terbaseline).";
        $lines[] = '';
        $lines[] = '| Aturan | Deskripsi Singkat | Jumlah Pelanggaran |';
        $lines[] = '|---|---|---|';
        foreach ($tableRows as $row) {
            $lines[] = $row;
        }

        return implode("\n", $lines);
    }
}
