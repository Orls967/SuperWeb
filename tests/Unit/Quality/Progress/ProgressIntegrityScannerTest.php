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

    expect($violations)->toContain("Teks item R0.1 (R0:R0.1) diubah tanpa penanda '⬇️ diturunkan' (X18 / P10).");
});

it('passes item text change when downgrade marker ⬇️ is present', function (): void {
    $markdown = <<<'MD'
### FASE R0 — LINGKUNGAN
> **Status audit:** 🔨 DIKERJAKAN

- [ ] R0.1 Teks baru ⬇️ diturunkan sesuai keputusan pemilik
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
    $violations = $scanner->validate($phases);

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
