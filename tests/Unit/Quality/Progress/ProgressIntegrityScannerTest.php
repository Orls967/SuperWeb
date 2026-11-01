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
    $violations = $scanner->validate($phases, sys_get_temp_dir(), snapshot: ['phases' => ['55' => '🟠']]);
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
    file_put_contents($tempDir.'/docs/gates/fase-r1.md', <<<'MD'
# Quality Gate Report: Fase r1
## 5. Verifikasi
*(Bagian ini wajib diisi oleh verifikator independen)*
- **Tanggal:** 
- **Verifikator:** 
- **Commit yang diverifikasi:** 
- **Keputusan:** BELUM DIVERIFIKASI
MD
    );

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

it('passes valid redirected minus without blocking origin phase', function (): void {
    $tempDir = sys_get_temp_dir().'/progress_test_k_p9_valid_'.uniqid();
    mkdir($tempDir.'/docs/gates', 0777, true);
    file_put_contents($tempDir.'/docs/DECISIONS.md', "# Decisions\n\n## 2026-10-14-p9-minus-dialihkan\n");
    copy(base_path('docs/gates/fase-r0.md'), $tempDir.'/docs/gates/fase-r0.md');

    $markdown = <<<'MD'
### FASE R0 — PAGAR OTOMATIS
> **Status audit:** ✅ SELESAI

- [x] R0.1 Pagar awal
  Bukti:
    - jenis: konfigurasi
    - commit: 4171528
    - file: composer.json
    - test: tests/Unit/SampleTest.php::it passes
    - gate: docs/gates/fase-r0.md

#### Register Minus Fase R0
| ID | Item | Minus | Dampak | Prioritas | Rencana |
|---|---|---|---|---|---|
| M-R0-6 | R1.2 | Saldo akun warisan terbalik | Data saldo terbalik | P0 | ↪️ dialihkan ke R1.2 (DECISIONS.md#2026-10-14-p9-minus-dialihkan) |

### FASE R1 — LEDGER & UANG
> **Status audit:** ⬜ BELUM

- [ ] R1.2 Koreksi saldo terbalik
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
    $violations = $scanner->validate($phases, repoRoot: $tempDir, textBaseline: ['R0:R0.1' => 'Pagar awal', 'R1:R1.2' => 'Koreksi saldo terbalik']);

    expect(implode("\n", $violations))->not->toContain('M-R0-6')
        ->and(implode("\n", $violations))->not->toContain('P0/P1 terbuka');
});

it('fails when redirected destination item does not exist in PROGRESS.md', function (): void {
    $markdown = <<<'MD'
### FASE R0 — PAGAR OTOMATIS
> **Status audit:** 🔨 DIKERJAKAN

- [ ] R0.1 Pagar awal

#### Register Minus Fase R0
| ID | Item | Minus | Dampak | Prioritas | Rencana |
|---|---|---|---|---|---|
| M-R0-6 | R99.99 | Saldo akun warisan terbalik | Data saldo terbalik | P0 | ↪️ dialihkan ke R99.99 (DECISIONS.md#2026-10-14-p9-minus-dialihkan) |
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, textBaseline: ['R0:R0.1' => 'Pagar awal']);

    expect(implode("\n", $violations))->toContain('tujuan pengalihan tidak ada')
        ->and(implode("\n", $violations))->toContain('R99.99');
});

it('fails when redirected minus lacks DECISIONS anchor', function (): void {
    $markdown = <<<'MD'
### FASE R0 — PAGAR OTOMATIS
> **Status audit:** 🔨 DIKERJAKAN

- [ ] R0.1 Pagar awal

#### Register Minus Fase R0
| ID | Item | Minus | Dampak | Prioritas | Rencana |
|---|---|---|---|---|---|
| M-R0-6 | R1.2 | Saldo akun warisan terbalik | Data saldo terbalik | P0 | ↪️ dialihkan ke R1.2 |

### FASE R1 — LEDGER & UANG
> **Status audit:** ⬜ BELUM

- [ ] R1.2 Koreksi saldo terbalik
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, textBaseline: ['R0:R0.1' => 'Pagar awal', 'R1:R1.2' => 'Koreksi saldo terbalik']);

    expect(implode("\n", $violations))->toContain('wajib menyertakan anchor')
        ->and(implode("\n", $violations))->toContain('DECISIONS.md#');
});

it('fails when redirected anchor does not exist in docs/DECISIONS.md', function (): void {
    $markdown = <<<'MD'
### FASE R0 — PAGAR OTOMATIS
> **Status audit:** 🔨 DIKERJAKAN

- [ ] R0.1 Pagar awal

#### Register Minus Fase R0
| ID | Item | Minus | Dampak | Prioritas | Rencana |
|---|---|---|---|---|---|
| M-R0-6 | R1.2 | Saldo akun warisan terbalik | Data saldo terbalik | P0 | ↪️ dialihkan ke R1.2 (DECISIONS.md#non-existent-anchor-123456789) |

### FASE R1 — LEDGER & UANG
> **Status audit:** ⬜ BELUM

- [ ] R1.2 Koreksi saldo terbalik
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, textBaseline: ['R0:R0.1' => 'Pagar awal', 'R1:R1.2' => 'Koreksi saldo terbalik']);

    expect(implode("\n", $violations))->toContain('anchor')
        ->and(implode("\n", $violations))->toContain('tidak ditemukan di docs/DECISIONS.md');
});

it('fails when destination phase is verified ✅ while redirected minus remains unclosed', function (): void {
    $tempDir = sys_get_temp_dir().'/progress_test_dest_blocked_'.uniqid();
    mkdir($tempDir.'/docs/gates', 0777, true);
    file_put_contents($tempDir.'/docs/DECISIONS.md', "# Decisions\n\n## 2026-10-14-p9-minus-dialihkan\n");
    copy(base_path('docs/gates/fase-r0.md'), $tempDir.'/docs/gates/fase-r1.md');

    $markdown = <<<'MD'
### FASE R0 — PAGAR OTOMATIS
> **Status audit:** 🔵 SELESAI

- [ ] R0.1 Pagar awal

#### Register Minus Fase R0
| ID | Item | Minus | Dampak | Prioritas | Rencana |
|---|---|---|---|---|---|
| M-R0-6 | R1.2 | Saldo akun warisan terbalik | Data saldo terbalik | P0 | ↪️ dialihkan ke R1.2 (DECISIONS.md#2026-10-14-p9-minus-dialihkan) |

### FASE R1 — LEDGER & UANG
> **Status audit:** ✅ SELESAI

- [ ] R1.2 Koreksi saldo terbalik
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, repoRoot: $tempDir, textBaseline: ['R0:R0.1' => 'Pagar awal', 'R1:R1.2' => 'Koreksi saldo terbalik']);

    expect(implode("\n", $violations))->toContain('M-R0-6')
        ->and(implode("\n", $violations))->toContain('belum dicoret');
});

it('fails when deliverable item of origin phase is redirected', function (): void {
    $tempDir = sys_get_temp_dir().'/progress_test_deliverable_'.uniqid();
    mkdir($tempDir.'/docs', 0777, true);
    file_put_contents($tempDir.'/docs/DECISIONS.md', "# Decisions\n\n## 2026-10-14-p9-minus-dialihkan\n");

    $markdown = <<<'MD'
### FASE R0 — PAGAR OTOMATIS
> **Status audit:** 🔨 DIKERJAKAN

- [x] R0.12 Pagar mutasi
  Bukti:
    - jenis: tooling
    - commit: 4171528
    - file: app/Quality/Progress/ProgressIntegrityScanner.php
    - test: tests/Unit/SampleTest.php::it passes
    - gate: docs/gates/fase-r0.md

#### Register Minus Fase R0
| ID | Item | Minus | Dampak | Prioritas | Rencana |
|---|---|---|---|---|---|
| M-R0-20 | R0.12 | Skor mutasi GateRunner di bawah 60% | Pagar mutasi kurang kuat | P1 | ↪️ dialihkan ke R5.5 (DECISIONS.md#2026-10-14-p9-minus-dialihkan) |

### FASE R5 — AUDIT & HARDENING
> **Status audit:** ⬜ BELUM

- [ ] R5.5 Pagar mutasi lanjutan
MD;

    $mockInspector = new class extends GitCommitInspector
    {
        public function isValidCommit(string $hash): bool
        {
            return true;
        }

        public function changedFiles(string $hash): array
        {
            return ['app/Quality/Progress/ProgressIntegrityScanner.php'];
        }
    };

    $scanner = new ProgressIntegrityScanner(gitInspector: $mockInspector);
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, repoRoot: $tempDir, textBaseline: ['R0:R0.12' => 'Pagar mutasi', 'R5:R5.5' => 'Pagar mutasi lanjutan']);

    expect(implode("\n", $violations))->toContain('deliverable fase R0 yang sudah tercentang [x]')
        ->and(implode("\n", $violations))->toContain('pengalihan ditolak');
});

it('still fails when ordinary open P0 minus is present in verified phase (V-S3 regression)', function (): void {
    $tempDir = sys_get_temp_dir().'/progress_test_p0_ordinary_'.uniqid();
    mkdir($tempDir.'/docs/gates', 0777, true);
    copy(base_path('docs/gates/fase-r0.md'), $tempDir.'/docs/gates/fase-r0.md');

    $markdown = <<<'MD'
### FASE R0 — PAGAR OTOMATIS
> **Status audit:** ✅ SELESAI

- [ ] R0.1 Pagar awal

#### Register Minus Fase R0
| ID | Item | Minus | Dampak | Prioritas | Rencana |
|---|---|---|---|---|---|
| M-R0-99 | R0.1 | Bug fatal belum diperbaiki | Sistem crash | P0 | Belum ada rencana perbaikan |
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, repoRoot: $tempDir, textBaseline: ['R0:R0.1' => 'Pagar awal']);

    expect(implode("\n", $violations))->toContain('Fase R0 berstatus ✅ tetapi masih memiliki minus P0/P1 terbuka: M-R0-99');
});

it('parses phase headers, status icons, dates, and calculates line numbers accurately', function (): void {
    $markdown = <<<'MD'
### FASE R2 — LEDGER DUA  
> **Status audit 2026-10-15:** 🟡 SEDANG BERJALAN

- [x] R2.1 Item satu
  Bukti:
    - file: composer.json
- [ ] R2.2 Item dua
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);

    expect($phases)->toHaveKey('R2');
    $phase = $phases['R2'];
    expect($phase->id)->toBe('R2')
        ->and($phase->title)->toBe('LEDGER DUA')
        ->and($phase->statusIcon)->toBe('🟡')
        ->and($phase->statusDate)->toBe('2026-10-15')
        ->and($phase->items)->toHaveCount(2)
        ->and($phase->items[0]->id)->toBe('R2.1')
        ->and($phase->items[0]->isChecked)->toBeTrue()
        ->and($phase->items[0]->lineNumber)->toBe(4)
        ->and($phase->items[1]->id)->toBe('R2.2')
        ->and($phase->items[1]->isChecked)->toBeFalse()
        ->and($phase->items[1]->lineNumber)->toBe(7);

    // Test icons parsing
    $icons = ['✅', '🔨', '🔵', '⬜', '🟠', '🟡', '🔁'];
    foreach ($icons as $icon) {
        $mdIcon = "### FASE R8 — TEST\n> **Status audit:** {$icon} TEST\n";
        $p = $scanner->parse($mdIcon);
        expect($p['R8']->statusIcon)->toBe($icon);
    }
});

it('handles duplicate item IDs generating indexed uniqueKey for nested or sub items', function (): void {
    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** 🔨 DIKERJAKAN

- [ ] R1.1 Pertama
- [ ] R1.1 Kedua
- [ ] R1.1 Ketiga
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);

    $items = $phases['R1']->items;
    expect($items)->toHaveCount(3)
        ->and($items[0]->uniqueKey)->toBe('R1:R1.1')
        ->and($items[1]->uniqueKey)->toBe('R1:R1.1#2')
        ->and($items[2]->uniqueKey)->toBe('R1:R1.1#3');
});

it('parses proof block with various indentations and terminates proof on unindented line', function (): void {
    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** 🔨 DIKERJAKAN

- [x] R1.1 Item dengan bukti
  Bukti:
    - file: composer.json
    - file: package.json
    - test: tests/TestCase.php
Baris non indentasi penutup bukti
  - file: file_tercecer.txt
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);

    $item = $phases['R1']->items[0];
    expect($item->proof)->toHaveKey('file')
        ->and($item->proof['file'])->toBe(['composer.json', 'package.json'])
        ->and($item->proof)->toHaveKey('test')
        ->and($item->proof['test'])->toBe(['tests/TestCase.php'])
        ->and($item->proof['file'])->not->toContain('file_tercecer.txt');
});

it('parses register minus table with closed marker and clean fields', function (): void {
    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** 🔨 DIKERJAKAN

- [ ] R1.1 Item biasa

| ID | Item | Minus | Dampak | Prioritas | Rencana |
|---|---|---|---|---|---|
| ~~M-R1-1~~ | R1.1 | Kerusakan saldo | Akun tidak seimbang | ~~P0~~ | Selesai diperbaiki di commit abc |
| M-R1-2 | R1.2 | Masalah lain | Dampak ringan | P2 | Rencana nanti |
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);

    $minus = $phases['R1']->minusEntries;
    expect($minus)->toHaveCount(2)
        ->and($minus[0]['id'])->toBe('M-R1-1')
        ->and($minus[0]['item'])->toBe('R1.1')
        ->and($minus[0]['minus'])->toBe('Kerusakan saldo')
        ->and($minus[0]['dampak'])->toBe('Akun tidak seimbang')
        ->and($minus[0]['prioritas'])->toBe('P0')
        ->and($minus[0]['rencana'])->toBe('Selesai diperbaiki di commit abc')
        ->and($minus[0]['is_closed'])->toBeTrue()
        ->and($minus[1]['id'])->toBe('M-R1-2')
        ->and($minus[1]['prioritas'])->toBe('P2')
        ->and($minus[1]['is_closed'])->toBeFalse();
});

it('extracts text baseline sorted by key using extractTextBaseline', function (): void {
    $markdown = <<<'MD'
### FASE R2 — DUA
> **Status audit:** 🔨 DIKERJAKAN

- [ ] R2.1 Item dua

### FASE R1 — SATU
> **Status audit:** 🔨 DIKERJAKAN

- [ ] R1.2 Item satu b
- [ ] R1.1 Item satu a
MD;

    $scanner = new ProgressIntegrityScanner;
    $texts = $scanner->extractTextBaseline($markdown);

    expect(array_keys($texts))->toBe([
        'R1:R1.1',
        'R1:R1.2',
        'R2:R2.1',
    ])->and($texts['R1:R1.1'])->toBe('Item satu a')
        ->and($texts['R1:R1.2'])->toBe('Item satu b')
        ->and($texts['R2:R2.1'])->toBe('Item dua');
});

it('evaluates scope based on cutoff date 2026-10-10 for non-R phases', function (): void {
    $mdPast = <<<'MD'
### FASE 987 — LOGISTIK LAMA
> **Status audit 2026-10-09:** 🔨 DIKERJAKAN

- [x] 987.1 Item tanpa bukti masa lalu
MD;

    $scanner = new ProgressIntegrityScanner;
    $phasesPast = $scanner->parse($mdPast);
    $violationsPast = $scanner->validate($phasesPast, snapshot: ['phases' => [], 'items' => []]);
    // Not in scope because phase is non-R, date <= 2026-10-10, and no snapshot transitions
    expect($violationsPast)->toBe([]);

    $mdFuture = <<<'MD'
### FASE 987 — LOGISTIK BARU
> **Status audit 2026-10-11:** 🔨 DIKERJAKAN

- [x] 987.1 Item tanpa bukti masa depan
MD;

    $phasesFuture = $scanner->parse($mdFuture);
    $violationsFuture = $scanner->validate($phasesFuture, snapshot: ['phases' => [], 'items' => []]);
    // In scope because phase date is after 2026-10-10
    expect($violationsFuture)->toContain('Item 987.1 tercentang [x] tanpa blok Bukti: (PROGRESS.md §P2).');
});

it('validates gate report verification details (missing date, commit, verifikator, and invalid C-items)', function (): void {
    $tempDir = sys_get_temp_dir().'/progress_gate_details_'.uniqid();
    mkdir($tempDir.'/docs/gates', 0777, true);
    $gatePath = $tempDir.'/docs/gates/fase-r1.md';

    $scanner = new ProgressIntegrityScanner;
    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** ✅ SELESAI

- [ ] R1.1 Item satu
MD;

    // 1. Missing tanggal
    file_put_contents($gatePath, <<<'MD'
# Quality Gate: Fase R1
## 5. Verifikasi
- **Verifikator:** Auditor
- **Commit yang diverifikasi:** 1a2b3c4
MD
    );
    $phases = $scanner->parse($markdown);
    $v1 = $scanner->validate($phases, repoRoot: $tempDir, textBaseline: ['R1:R1.1' => 'Item satu']);
    expect($v1)->toContain("Fase R1 berstatus ✅ tetapi bagian Verifikasi di {$gatePath} tidak sah: tanggal verifikasi kosong atau tidak ditemukan.");

    // 2. Missing commit
    file_put_contents($gatePath, <<<'MD'
# Quality Gate: Fase R1
## 5. Verifikasi
- **Tanggal:** 2026-10-14
- **Verifikator:** Auditor
MD
    );
    $v2 = $scanner->validate($phases, repoRoot: $tempDir, textBaseline: ['R1:R1.1' => 'Item satu']);
    expect($v2)->toContain("Fase R1 berstatus ✅ tetapi bagian Verifikasi di {$gatePath} tidak sah: commit yang diverifikasi kosong atau tidak ditemukan.");

    // 3. Missing verifikator
    file_put_contents($gatePath, <<<'MD'
# Quality Gate: Fase R1
## 5. Verifikasi
- **Tanggal:** 2026-10-14
- **Commit yang diverifikasi:** 1a2b3c4
MD
    );
    $v3 = $scanner->validate($phases, repoRoot: $tempDir, textBaseline: ['R1:R1.1' => 'Item satu']);
    expect($v3)->toContain("Fase R1 berstatus ✅ tetapi bagian Verifikasi di {$gatePath} tidak sah: identitas verifikator kosong atau tidak ditemukan.");

    // 4. Missing C5
    $cTable = "- **Tanggal:** 2026-10-14\n- **Verifikator:** Auditor\n- **Commit yang diverifikasi:** 1a2b3c4\n";
    for ($i = 1; $i <= 14; $i++) {
        if ($i === 5) {
            continue; // omit C5
        }
        $cTable .= "| C{$i} | LULUS |\n";
    }
    file_put_contents($gatePath, "# Quality Gate: Fase R1\n## 5. Verifikasi\n{$cTable}");
    $v4 = $scanner->validate($phases, repoRoot: $tempDir, textBaseline: ['R1:R1.1' => 'Item satu']);
    expect($v4)->toContain("Fase R1 berstatus ✅ tetapi bagian Verifikasi di {$gatePath} tidak sah: butir C5 tidak ditemukan di tabel verifikasi.");

    // 5. C7 not pass/fail
    $cTableUnverified = "- **Tanggal:** 2026-10-14\n- **Verifikator:** Auditor\n- **Commit yang diverifikasi:** 1a2b3c4\n";
    for ($i = 1; $i <= 14; $i++) {
        $status = $i === 7 ? 'belum diverifikasi' : 'LULUS';
        $cTableUnverified .= "| C{$i} | {$status} |\n";
    }
    file_put_contents($gatePath, "# Quality Gate: Fase R1\n## 5. Verifikasi\n{$cTableUnverified}");
    $v5 = $scanner->validate($phases, repoRoot: $tempDir, textBaseline: ['R1:R1.1' => 'Item satu']);
    expect($v5)->toContain("Fase R1 berstatus ✅ tetapi bagian Verifikasi di {$gatePath} tidak sah: butir C7 belum bertanda lulus/gagal ('belum diverifikasi').");
});

it('validates physical file existence and test file or method presence', function (): void {
    $tempDir = sys_get_temp_dir().'/progress_files_'.uniqid();
    mkdir($tempDir.'/tests', 0777, true);
    file_put_contents($tempDir.'/tests/RealTest.php', "<?php\nit('existing test method', function () {});\n");

    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** 🔨 DIKERJAKAN

- [x] R1.1 Item file & test
  Bukti:
    - jenis: tooling
    - file: non/existent/script.sh
    - test: tests/NonExistentTest.php::it runs
    - test: tests/RealTest.php::it missing test
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, repoRoot: $tempDir, textBaseline: ['R1:R1.1' => 'Item file & test']);

    expect($violations)->toContain("Item R1.1: file 'non/existent/script.sh' tidak ditemukan di filesystem.")
        ->and($violations)->toContain("Item R1.1: file test 'tests/NonExistentTest.php' tidak ditemukan di filesystem.")
        ->and($violations)->toContain("Item R1.1: nama test 'it missing test' tidak ditemukan di dalam 'tests/RealTest.php'.");
});

it('validates route existence and route role helper methods comprehensively', function (): void {
    Route::middleware('role:auditor,finance')->get('/test-progress-multi-role', fn () => 'ok');

    $route = ProgressIntegrityScanner::findMatchingRoute('GET', '/test-progress-multi-role');
    expect($route)->not->toBeNull();
    expect(ProgressIntegrityScanner::findMatchingRoute('POST', '/test-progress-multi-role'))->toBeNull();
    expect(ProgressIntegrityScanner::findMatchingRoute('GET', '/non-existent-route'))->toBeNull();

    expect(ProgressIntegrityScanner::routeHasExpectedRoles($route, ['auditor']))->toBeTrue();
    expect(ProgressIntegrityScanner::routeHasExpectedRoles($route, ['auditor', 'finance']))->toBeTrue();
    expect(ProgressIntegrityScanner::routeHasExpectedRoles($route, ['superadmin']))->toBeFalse();

    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** 🔨 DIKERJAKAN

- [x] R1.1 Endpoint hilang
  Bukti:
    - jenis: fitur
    - test: tests/TestCase.php
    - akses: route POST /non-existent-route [role: admin]
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, textBaseline: ['R1:R1.1' => 'Endpoint hilang']);

    expect($violations)->toContain("Item R1.1: akses route 'POST /non-existent-route' tidak ditemukan di router.");
});

it('validates command registration and audit command helper methods', function (): void {
    expect(ProgressIntegrityScanner::isCommandRegistered('inspire'))->toBeTrue();
    expect(ProgressIntegrityScanner::isCommandRegistered('unknown:non:existent'))->toBeFalse();

    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** 🔨 DIKERJAKAN

- [x] R1.1 Command hilang
  Bukti:
    - jenis: tooling
    - test: tests/TestCase.php
    - akses: command unknown:non:existent
    - audit: non:existent:audit
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, textBaseline: ['R1:R1.1' => 'Command hilang']);

    expect($violations)->toContain("Item R1.1: akses command 'unknown:non:existent' tidak terdaftar di Artisan.")
        ->and($violations)->toContain("Item R1.1: audit command 'non:existent:audit' tidak terdaftar atau tidak memiliki fixture korupsi.");
});

it('validates item text downgrade reason length and DECISIONS cross reference', function (): void {
    $tempDir = sys_get_temp_dir().'/progress_downgrade_'.uniqid();
    mkdir($tempDir.'/docs', 0777, true);
    file_put_contents($tempDir.'/docs/DECISIONS.md', "# Decisions\n\nTanpa menyebut item apapun\n");

    $markdown9 = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** 🔨 DIKERJAKAN

- [ ] R1.1 Teks diubah ⬇️ diturunkan: 123456789
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases9 = $scanner->parse($markdown9);
    $v9 = $scanner->validate($phases9, repoRoot: $tempDir, textBaseline: ['R1:R1.1' => 'Teks awal']);
    expect($v9)->toContain("Teks item R1.1 (R1:R1.1) diubah tanpa penanda '⬇️ diturunkan: <alasan>' dengan alasan minimal 10 karakter (X18 / P10).");

    $markdown10 = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** 🔨 DIKERJAKAN

- [ ] R1.1 Teks diubah ⬇️ diturunkan: 1234567890
MD;

    $phases10 = $scanner->parse($markdown10);
    $v10 = $scanner->validate($phases10, repoRoot: $tempDir, textBaseline: ['R1:R1.1' => 'Teks awal']);
    expect($v10)->toContain('Teks item R1.1 diturunkan tetapi ID item tidak disebutkan di docs/DECISIONS.md.');
});

it('detects missing gate report file for item gate proof and invalid item types', function (): void {
    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** 🔨 DIKERJAKAN

- [x] R1.1 Item gate hilang
  Bukti:
    - jenis: tipe_aneh
    - test: tests/TestCase.php
    - gate: docs/gates/fase-non-existent.md
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, textBaseline: ['R1:R1.1' => 'Item gate hilang']);

    expect($violations)->toContain("Item R1.1: file laporan gate 'docs/gates/fase-non-existent.md' tidak ditemukan.")
        ->and($violations)->toContain("Item R1.1: jenis 'tipe_aneh' tidak valid (hanya fitur, tooling, konfigurasi, dokumen).");
});

it('detects missing test in feature item', function (): void {
    Route::get('/test-feature-no-test', fn () => 'ok');

    $markdown = <<<'MD'
### FASE R1 — LEDGER & UANG
> **Status audit:** 🔨 DIKERJAKAN

- [x] R1.1 Fitur tanpa test
  Bukti:
    - jenis: fitur
    - akses: route GET /test-feature-no-test
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, textBaseline: ['R1:R1.1' => 'Fitur tanpa test']);

    expect($violations)->toContain("Item fitur R1.1 wajib memiliki minimal satu entri 'test' di blok Bukti (PROGRESS.md §P2).");
});

it('supports PHPUnit method declaration in testNameExistsInFile', function (): void {
    $tempDir = sys_get_temp_dir().'/progress_phpunit_'.uniqid();
    mkdir($tempDir.'/tests', 0777, true);
    $testFile = $tempDir.'/tests/PhpUnitTest.php';
    file_put_contents($testFile, <<<'PHP'
<?php
class PhpUnitTest {
    public function test_custom_user_workflow() {}
}
PHP
    );

    expect(ProgressIntegrityScanner::testNameExistsInFile($testFile, 'custom user workflow'))->toBeTrue()
        ->and(ProgressIntegrityScanner::testNameExistsInFile($testFile, 'test custom user workflow'))->toBeTrue()
        ->and(ProgressIntegrityScanner::testNameExistsInFile($testFile, 'unrelated test'))->toBeFalse()
        ->and(ProgressIntegrityScanner::testNameExistsInFile('non_existent.php', 'custom user workflow'))->toBeFalse();
});

// ============================================================================
// II.1 Test Fixtures Pagar ProgressIntegrityScanner
// ============================================================================

it('fails when old phase lacks proof block when R6 is verified', function (): void {
    $markdown = <<<'MD'
### FASE R6 — VERIFIKASI ULANG ITEM FASE 26–63
> **Status audit:** ✅ TERVERIFIKASI (2026-10-17)

- [x] R6.1 Item R6 valid
  Bukti:
    - jenis: dokumen
    - file: docs/PROGRESS.md
    - gate: docs/gates/fase-r6.md

### FASE 45 — LOGISTIK
> **Status audit:** 🔨 DIKERJAKAN

- [x] 45.1 Item lama centang tanpa bukti sama sekali
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, textBaseline: [
        'R6:R6.1' => 'Item R6 valid',
        '45:45.1' => 'Item lama centang tanpa bukti sama sekali',
    ]);

    expect($violations)->toContain('Item 45.1 tercentang [x] tanpa blok Bukti: (PROGRESS.md §P2).');
});

it('fails when gate report is stale with non-docs code changes between gate commit and HEAD', function (): void {
    $tempDir = sys_get_temp_dir().'/progress_gate_stale_'.uniqid();
    mkdir($tempDir.'/docs/gates', 0777, true);
    mkdir($tempDir.'/tests', 0777, true);

    file_put_contents($tempDir.'/docs/gates/fase-r1.md', <<<'MD'
# Gate Report R1
- **Commit:** `c0ffee1`
## 2. Hasil Gate
`test_example` PASS 🟢
MD
    );

    file_put_contents($tempDir.'/tests/ExampleTest.php', "<?php\ntest('example', function () {});");

    $markdown = <<<'MD'
### FASE R1 — LEDGER
> **Status audit:** 🔵 SELESAI

- [x] R1.1 Item R1
  Bukti:
    - jenis: tooling
    - test: tests/ExampleTest.php::example
    - gate: docs/gates/fase-r1.md
MD;

    $mockInspector = new class extends GitCommitInspector
    {
        public function __construct()
        {
            parent::__construct(sys_get_temp_dir());
        }

        public function commitExists(string $hash): bool
        {
            return true;
        }

        public function diffFiles(string $fromCommit, string $toCommit = 'HEAD'): array
        {
            return ['app/Models/User.php', 'docs/PROGRESS.md']; // non-docs change exists
        }
    };

    $scanner = new ProgressIntegrityScanner(gitInspector: $mockInspector);
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, repoRoot: $tempDir, textBaseline: ['R1:R1.1' => 'Item R1']);

    expect($violations)->toContain('Item R1.1: terdapat perubahan di luar docs/ antara commit gate (c0ffee1) dan HEAD: app/Models/User.php.');
});

it('passes when gate report has only docs changes between gate commit and HEAD', function (): void {
    $tempDir = sys_get_temp_dir().'/progress_gate_docs_only_'.uniqid();
    mkdir($tempDir.'/docs/gates', 0777, true);
    mkdir($tempDir.'/tests', 0777, true);

    file_put_contents($tempDir.'/docs/gates/fase-r1.md', <<<'MD'
# Gate Report R1
- **Commit:** `c0ffee1`
## 2. Hasil Gate
`test_example` PASS 🟢
MD
    );

    file_put_contents($tempDir.'/tests/ExampleTest.php', "<?php\ntest('example', function () {});");

    $markdown = <<<'MD'
### FASE R1 — LEDGER
> **Status audit:** 🔵 SELESAI

- [x] R1.1 Item R1
  Bukti:
    - jenis: tooling
    - test: tests/ExampleTest.php::example
    - gate: docs/gates/fase-r1.md
MD;

    $mockInspector = new class extends GitCommitInspector
    {
        public function __construct()
        {
            parent::__construct(sys_get_temp_dir());
        }

        public function commitExists(string $hash): bool
        {
            return true;
        }

        public function diffFiles(string $fromCommit, string $toCommit = 'HEAD'): array
        {
            return ['docs/PROGRESS.md', 'docs/audits/report.md']; // only docs!
        }
    };

    $scanner = new ProgressIntegrityScanner(gitInspector: $mockInspector);
    $phases = $scanner->parse($markdown);
    $violations = $scanner->validate($phases, repoRoot: $tempDir, textBaseline: ['R1:R1.1' => 'Item R1']);

    $gateStaleViolations = array_filter($violations, fn ($v) => str_contains($v, 'terdapat perubahan di luar docs/'));
    expect($gateStaleViolations)->toBeEmpty();
});

it('fails testNameExistsInFile when test name appears only inside a comment', function (): void {
    $tempFile = tempnam(sys_get_temp_dir(), 'test_comment_').'.php';
    file_put_contents($tempFile, <<<'PHP'
<?php
// it('this test is only commented out and does not exist')
/* function test_commented_method_should_fail() */
class NormalClass {}
PHP
    );

    expect(ProgressIntegrityScanner::testNameExistsInFile($tempFile, 'this test is only commented out and does not exist'))->toBeFalse()
        ->and(ProgressIntegrityScanner::testNameExistsInFile($tempFile, 'test_commented_method_should_fail'))->toBeFalse();
    @unlink($tempFile);
});

it('fails testNameExistsInFile when function name is not a test method', function (): void {
    $tempFile = tempnam(sys_get_temp_dir(), 'test_func_').'.php';
    file_put_contents($tempFile, <<<'PHP'
<?php
class HelperClass {
    public function calculate_payroll_total(): int { return 100; }
}
PHP
    );

    expect(ProgressIntegrityScanner::testNameExistsInFile($tempFile, 'calculate_payroll_total'))->toBeFalse();
    @unlink($tempFile);
});

it('passes testNameExistsInFile when exact Pest it or test declaration is present', function (): void {
    $tempFile = tempnam(sys_get_temp_dir(), 'test_pest_').'.php';
    file_put_contents($tempFile, <<<'PHP'
<?php
it('calculates exact interest rate with decimal precision', function () {});
test('ensures ledger is strictly balanced', function () {});
PHP
    );

    expect(ProgressIntegrityScanner::testNameExistsInFile($tempFile, 'calculates exact interest rate with decimal precision'))->toBeTrue()
        ->and(ProgressIntegrityScanner::testNameExistsInFile($tempFile, 'ensures ledger is strictly balanced'))->toBeTrue()
        ->and(ProgressIntegrityScanner::testNameExistsInFile($tempFile, 'non-existent pest test'))->toBeFalse();
    @unlink($tempFile);
});

it('passes testNameExistsInFile when exact PHPUnit test method is present in snake_case or camelCase', function (): void {
    $tempFile = tempnam(sys_get_temp_dir(), 'test_phpunit_').'.php';
    file_put_contents($tempFile, <<<'PHP'
<?php
class AccountPostingTest {
    public function test_journal_entry_creates_valid_audit_trail(): void {}
    public function testReconciliationDetectsMismatch(): void {}
}
PHP
    );

    expect(ProgressIntegrityScanner::testNameExistsInFile($tempFile, 'test_journal_entry_creates_valid_audit_trail'))->toBeTrue()
        ->and(ProgressIntegrityScanner::testNameExistsInFile($tempFile, 'journal entry creates valid audit trail'))->toBeTrue()
        ->and(ProgressIntegrityScanner::testNameExistsInFile($tempFile, 'reconciliation detects mismatch'))->toBeTrue()
        ->and(ProgressIntegrityScanner::testNameExistsInFile($tempFile, 'testReconciliationDetectsMismatch'))->toBeTrue();
    @unlink($tempFile);
});

it('correctly parses and audits variant status audit labels such as Status audit 2026-10-23', function (): void {
    $markdown = <<<'MD'
### FASE 88 — EKONOMI KREATIF
> **Status audit 2026-10-23:** ✅ TERVERIFIKASI (2026-10-23)

- [x] 88.1 Creative item
MD;

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($markdown);

    expect(isset($phases['88']))->toBeTrue()
        ->and($phases['88']->isVerified())->toBeTrue()
        ->and($phases['88']->statusDate)->toBe('2026-10-23');
});
