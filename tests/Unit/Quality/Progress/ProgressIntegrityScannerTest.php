<?php

declare(strict_types=1);

use App\Quality\Progress\GitCommitInspector;
use App\Quality\Progress\ProgressIntegrityScanner;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class);

/*
| PROGRESS R0.4: ProgressIntegrityScannerTest (fixture positif & negatif).
| Memvalidasi parser PROGRESS.md dan aturan integritas blok Bukti: (P2, KONSEP §A14.2).
*/

it('detects checked items lacking a Bukti block', function (): void {
    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** 🔨 DIKERJAKAN

- [x] R1.1 Konvensi tanda tunggal
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases);

    expect($violations)->toContain('Item R1.1 tercentang [x] tanpa blok Bukti: (PROGRESS.md §P2).');
});

it('detects commit not found in git history', function (): void {
    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** 🔨 DIKERJAKAN

- [x] R1.1 Konvensi tanda tunggal
  Bukti:
    - commit: non_existent_commit_hash
    - file: composer.json
MD;

    $mockInspector = new class extends GitCommitInspector
    {
        public function isValidCommit(string $hash): bool
        {
            return false;
        }

        public function changedFiles(string $hash): array
        {
            return [];
        }
    };

    $scanner = new ProgressIntegrityScanner(gitInspector: $mockInspector);
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases);

    expect($violations)->toContain("Item R1.1: commit 'non_existent_commit_hash' tidak ditemukan di riwayat git.");
});

it('detects commit that does not touch any mentioned file', function (): void {
    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** 🔨 DIKERJAKAN

- [x] R1.1 Konvensi tanda tunggal
  Bukti:
    - commit: a1b2c3d
    - file: composer.json
MD;

    $mockInspector = new class extends GitCommitInspector
    {
        public function isValidCommit(string $hash): bool
        {
            return true;
        }

        public function changedFiles(string $hash): array
        {
            return ['some/unrelated/file.txt'];
        }
    };

    $scanner = new ProgressIntegrityScanner(gitInspector: $mockInspector);
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases);

    expect($violations)->toContain("Item R1.1: commit 'a1b2c3d' tidak menyentuh file apa pun yang disebut di blok Bukti.");
});

it('detects route without the required role protection', function (): void {
    Route::get('/test-progress-unprotected-endpoint', fn () => 'ok');

    $markdown = <<<'MD'
### FASE 99 — TESTING PHASE
> **Status audit 2026-10-15:** 🔨 DIKERJAKAN

- [x] 99.1 Endpoint Baru
  Bukti:
    - commit: validhash
    - file: composer.json
    - test: tests/TestCase.php
    - akses: route GET /test-progress-unprotected-endpoint [role: auditor]
MD;

    $mockInspector = new class extends GitCommitInspector
    {
        public function isValidCommit(string $hash): bool
        {
            return true;
        }

        public function changedFiles(string $hash): array
        {
            return ['composer.json'];
        }
    };

    $scanner = new ProgressIntegrityScanner(gitInspector: $mockInspector);
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases);

    expect($violations)->toContain("Item 99.1: akses route 'GET /test-progress-unprotected-endpoint' tidak dilindungi role [auditor].");
});

it('detects verified phase ✅ without Verifikasi section in gate report', function (): void {
    $tempDir = sys_get_temp_dir().'/progress_test_'.uniqid();
    mkdir($tempDir.'/docs/gates', 0777, true);
    file_put_contents($tempDir.'/docs/gates/fase-r1.md', "# Gate Fase R1\nLaporan tanpa bagian verifikasi.");

    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** ✅ SELESAI

- [ ] R1.1 Konvensi tanda tunggal
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, repoRoot: $tempDir);

    expect($violations)->toContain('Fase R1 berstatus ✅ tetapi docs/gates/fase-R1.md tidak memiliki bagian Verifikasi.');
});

it('detects item text modification without downgrade marker ⬇️', function (): void {
    $markdown = <<<'MD'
### FASE R0 — LINGKUNGAN
> **Status audit:** 🔨 DIKERJAKAN

- [ ] R0.1 Teks baru diam-diam diubah tanpa izin
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $baseline = [
        'R0:R0.1' => 'Teks asli baseline',
    ];

    $violations = $scanner->validate($phases, textBaseline: $baseline);

    expect($violations)->toContain("Teks item R0.1 (R0:R0.1) diubah tanpa penanda '⬇️ diturunkan: <alasan>' dengan alasan minimal 10 karakter (X18 / P10).");
});

it('passes item text change when downgrade marker ⬇️ is present', function (): void {
    $markdown = <<<'MD'
### FASE R0 — LINGKUNGAN
> **Status audit:** 🔨 DIKERJAKAN

- [ ] R0.1 Teks baru ⬇️ diturunkan: sesuai keputusan pemilik nomor K01
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $baseline = [
        'R0:R0.1' => 'Teks asli baseline',
    ];

    $violations = $scanner->validate($phases, textBaseline: $baseline);

    expect($violations)->toBe([]);
});

it('passes when valid proof block is provided for a checked item', function (): void {
    Route::middleware('role:admin')->get('/test-progress-protected-endpoint', fn () => 'ok');

    $markdown = <<<'MD'
### FASE R0 — PAGAR OTOMATIS
> **Status audit:** 🔨 DIKERJAKAN

- [x] R0.1 Selaraskan versi PHP
  Bukti:
    - commit: 1a2b3c4
    - file: composer.json
    - test: tests/TestCase.php
    - akses: route GET /test-progress-protected-endpoint [role: admin]
MD;

    $mockInspector = new class extends GitCommitInspector
    {
        public function isValidCommit(string $hash): bool
        {
            return true;
        }

        public function changedFiles(string $hash): array
        {
            return ['composer.json'];
        }
    };

    $scanner = new ProgressIntegrityScanner(gitInspector: $mockInspector);
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, textBaseline: ['R0:R0.1' => 'Selaraskan versi PHP']);

    expect($violations)->toBe([]);
});

it('detects verified phase ✅ with open P0 or P1 minus', function (): void {
    $tempDir = sys_get_temp_dir().'/progress_test_'.uniqid();
    mkdir($tempDir.'/docs/gates', 0777, true);
    file_put_contents($tempDir.'/docs/gates/fase-r1.md', "# Gate Fase R1\n## Verifikasi\nSemua verifikasi lengkap.");

    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** ✅ SELESAI

- [ ] R1.1 Konvensi tanda tunggal

| # | Item | Minus | Dampak | Prioritas | Rencana mitigasi |
|---|---|---|---|---|---|
| M-R1-1 | R1.1 | Posting ganda | Saldo tidak seimbang | P0 | Perbaiki di R1.2 |
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, repoRoot: $tempDir);

    expect($violations)->toContain('Fase R1 berstatus ✅ tetapi masih memiliki minus P0/P1 terbuka: M-R1-1 (PROGRESS.md §P7, P9).');
});

it('strictly matches exact test names rejecting partial or fuzzy names', function (): void {
    $tempDir = sys_get_temp_dir().'/progress_test_'.uniqid();
    mkdir($tempDir.'/tests', 0777, true);
    $testFilePath = $tempDir.'/tests/ExampleTest.php';
    file_put_contents($testFilePath, <<<'PHP'
<?php
it('runs exact test with precision', function () {});
PHP);

    // Exact name with "it " prefix
    expect(ProgressIntegrityScanner::testNameExistsInFile($testFilePath, 'it runs exact test with precision'))->toBeTrue();

    // Exact name without "it " prefix
    expect(ProgressIntegrityScanner::testNameExistsInFile($testFilePath, 'runs exact test with precision'))->toBeTrue();

    // Substring / partial match MUST fail
    expect(ProgressIntegrityScanner::testNameExistsInFile($testFilePath, 'exact test'))->toBeFalse();
    expect(ProgressIntegrityScanner::testNameExistsInFile($testFilePath, 'runs exact test'))->toBeFalse();
    expect(ProgressIntegrityScanner::testNameExistsInFile($testFilePath, 'it runs exact test'))->toBeFalse();

    // Case difference / fuzzy MUST fail
    expect(ProgressIntegrityScanner::testNameExistsInFile($testFilePath, 'RUNS EXACT TEST WITH PRECISION'))->toBeFalse();
});

it('catches sabotage (a) checking item 55.1 without Bukti block even when phase date is absent', function (): void {
    $markdown = <<<'MD'
### FASE 55 — INTEGRASI LOGISTIK
> **Status audit:** 🔨 DIKERJAKAN

- [x] 55.1 Item tanpa bukti
MD;
    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases);
    expect($violations)->toContain('Item 55.1 tercentang [x] tanpa blok Bukti: (PROGRESS.md §P2).');
});

it('catches sabotage (b) setting phase 55 to verified without gate report or Verifikasi', function (): void {
    $markdown = <<<'MD'
### FASE 55 — INTEGRASI LOGISTIK
> **Status audit:** ✅ SELESAI

- [ ] 55.1 Item belum selesai
MD;
    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases);
    expect($violations)->toContain('Fase 55 berstatus ✅ tetapi docs/gates/fase-55.md tidak ditemukan.');
});

it('catches sabotage (c) item R1.1 marked with jenis tooling but missing test entry', function (): void {
    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** 🔨 DIKERJAKAN

- [x] R1.1 Tooling tanpa test
  Bukti:
    - jenis: tooling
    - file: composer.json
MD;
    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases);
    expect($violations)->toContain("Item tooling R1.1 wajib memiliki minimal satu entri 'test' di blok Bukti (PROGRESS.md §P2).");
});

it('catches sabotage (d) item R1.2 text modified with bare ⬇️ marker without reason or DECISIONS entry', function (): void {
    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** 🔨 DIKERJAKAN

- [ ] R1.2 Teks dipersempit ⬇️
MD;
    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases);
    expect($violations)->toContain("Teks item R1.2 (R1:R1.2) diubah tanpa penanda '⬇️ diturunkan: <alasan>' dengan alasan minimal 10 karakter (X18 / P10).");
});

it('validates audit command requires Artisan registration and corruption fixture (V2)', function (): void {
    // Negative: command exists in Artisan but has no fixture
    expect(ProgressIntegrityScanner::isAuditCommandValid('inspire'))->toBeFalse();
    // Negative: non-existent command
    expect(ProgressIntegrityScanner::isAuditCommandValid('non:existent:audit'))->toBeFalse();
    // Positive: registered and has corruption fixture
    expect(ProgressIntegrityScanner::isAuditCommandValid('bank:reconcile'))->toBeTrue();
});

it('validates item type jenis requirements and reason length (V3)', function (): void {
    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** 🔨 DIKERJAKAN

- [x] R1.1 Dokumen tanpa file
  Bukti:
    - jenis: dokumen
    - alasan: pendek

- [x] R1.2 Konfigurasi tanpa test
  Bukti:
    - jenis: konfigurasi
    - file: composer.json
MD;
    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases);
    expect($violations)->toContain("Item dokumen R1.1 wajib memiliki minimal satu entri 'file' di blok Bukti (PROGRESS.md §P2).")
        ->and($violations)->toContain("Item R1.1: alasan 'pendek' kurang dari 10 karakter.")
        ->and($violations)->toContain("Item konfigurasi R1.2 wajib memiliki minimal satu entri 'test' di blok Bukti (PROGRESS.md §P2).");
});

it('validates gate report test pass status and detects non-docs diff against HEAD (V4)', function (): void {
    $tempDir = sys_get_temp_dir().'/progress_test_gate_'.uniqid();
    mkdir($tempDir.'/docs/gates', 0777, true);
    mkdir($tempDir.'/tests', 0777, true);
    file_put_contents($tempDir.'/tests/SomeTest.php', "<?php\nit('test pass', function () {});\nit('test fail', function () {});\n");

    $gateReportContent = <<<'MD'
# Quality Gate Report: Fase R1
- **Commit:** `1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b` (1a2b3c4)
## 3. Status Test Blok Bukti Fase R1
| Item | Nama Test | File | Status (JUnit) | Durasi |
|---|---|---|---|---|
| R1.1 | `it test pass` | `tests/SomeTest.php` | PASS 🟢 | 0.01s |
MD;
    file_put_contents($tempDir.'/docs/gates/fase-r1.md', $gateReportContent);

    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** 🔨 DIKERJAKAN

- [x] R1.1 Item dengan test tidak lulus di gate
  Bukti:
    - jenis: tooling
    - test: tests/SomeTest.php::it test fail
    - gate: docs/gates/fase-r1.md
MD;

    $mockInspector = new class extends GitCommitInspector
    {
        public function diffFiles(string $fromCommit, string $toCommit = 'HEAD'): array
        {
            return ['app/Services/SecretService.php', 'docs/PROGRESS.md'];
        }
    };

    $scanner = new ProgressIntegrityScanner(gitInspector: $mockInspector);
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, repoRoot: $tempDir);

    expect($violations)->toContain("Item R1.1: test 'it test fail' tidak tercatat LULUS di laporan gate 'docs/gates/fase-r1.md'.")
        ->and(implode("\n", $violations))->toContain('terdapat perubahan di luar docs/ antara commit gate');
});

it('rejects sabotage S3 item with jenis tooling followed by reason but lacking test entry', function (): void {
    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** 🔨 DIKERJAKAN

- [x] R1.1 Konvensi tanda tunggal
  Bukti:
    - jenis: tooling — alasan yang cukup panjang
    - file: composer.json
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, textBaseline: ['R1:R1.1' => 'Konvensi tanda tunggal']);

    expect($violations)->toContain("Item tooling R1.1 wajib memiliki minimal satu entri 'test' di blok Bukti (PROGRESS.md §P2).");
});

it('rejects sabotage S7 phase marked verified ✅ when gate report verification section is only a template or lacks valid C1-C14 table', function (): void {
    $tempDir = sys_get_temp_dir().'/progress_test_s7_'.uniqid();
    mkdir($tempDir.'/docs/gates', 0777, true);
    copy(base_path('docs/gates/fase-r0.md'), $tempDir.'/docs/gates/fase-r1.md');

    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** ✅ SELESAI

- [ ] R1.1 Konvensi tanda tunggal
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, repoRoot: $tempDir, textBaseline: ['R1:R1.1' => 'Konvensi tanda tunggal']);

    expect(implode("\n", $violations))->toContain('bagian Verifikasi di')
        ->and(implode("\n", $violations))->toContain('tidak sah');
});
