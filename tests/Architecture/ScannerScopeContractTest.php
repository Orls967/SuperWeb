<?php

declare(strict_types=1);

use App\Quality\Progress\ProgressIntegrityScanner;
use Tests\TestCase;

/*
| Kontrak Arsitektur: ScannerScopeContractTest
| Memastikan ProgressIntegrityScanner mempertahankan cakupan penuh (allPhasesInScope)
| saat Fase R6 terverifikasi ✅, serta mendeteksi sabotase jika variabel dinonaktifkan.
*/

uses(TestCase::class);

it('enforces that ProgressIntegrityScanner code binds allPhasesInScope strictly to R6 verification without bypasses', function (): void {
    $scannerCode = (string) file_get_contents(base_path('app/Quality/Progress/ProgressIntegrityScanner.php'));

    // 1. Must contain strict R6 binding
    expect($scannerCode)->toContain("\$allPhasesInScope = isset(\$phases['R6']) && \$phases['R6']->isVerified();");

    // 2. Must not contain the sabotaged false assignment
    expect($scannerCode)->not->toMatch('/\$allPhasesInScope\s*=\s*false\s*;/');

    // 3. Must not contain hasSubsequentActivePhase bypass
    expect($scannerCode)->not->toContain('$hasSubsequentActivePhase');
});

it('ensures scanner audits all phases in PROGRESS.md when R6 is verified', function (): void {
    $fixtureWithR6Verified = <<<'MD'
# PROGRESS

### FASE R6 — VERIFIKASI ULANG ITEM FASE 26–63
> **Status audit:** ✅ TERVERIFIKASI (2026-10-17)

- [x] R6.1 Item R6 valid
  - Bukti:
    - commit: `37aebb76`
    - file: `composer.json`
    - test: `tests/Architecture/ProgressIntegrityTest.php::test_progress_integrity`
    - gate: `docs/gates/fase-r6.md`

### FASE 1 — ARSITEKTUR AWAL
> **Status audit:** ⬜ BELUM DIMULAI (2026-09-01)

- [x] 1.1 Item lama tanpa blok bukti sama sekali
MD;

    $gateContent = <<<'MD'
# Gate Report Fase R6
- **Commit:** `37aebb76`
## 5. Verifikasi
- **Tanggal:** 2026-10-17
- **Commit:** `37aebb76`
- **Verifikator:** Auditor Independen
| C1 | Lulus |
| C2 | Lulus |
| C3 | Lulus |
| C4 | Lulus |
| C5 | Lulus |
| C6 | Lulus |
| C7 | Lulus |
| C8 | Lulus |
| C9 | Lulus |
| C10 | Lulus |
| C11 | Lulus |
| C12 | Lulus |
| C13 | Lulus |
| C14 | Lulus |
- `tests/Architecture/ProgressIntegrityTest.php::test_progress_integrity` PASS
MD;

    $tempDir = sys_get_temp_dir().'/scanner_scope_test_'.uniqid();
    mkdir($tempDir.'/docs/gates', 0777, true);
    mkdir($tempDir.'/tests/Architecture', 0777, true);
    file_put_contents($tempDir.'/composer.json', '{}');
    file_put_contents($tempDir.'/docs/gates/fase-r6.md', $gateContent);
    file_put_contents($tempDir.'/docs/DECISIONS.md', "# DECISIONS\n");
    file_put_contents($tempDir.'/tests/Architecture/ProgressIntegrityTest.php', "<?php\ntest('test_progress_integrity', fn() => true);");

    // Snapshot where phase 1 was already ⬜ and item 1.1 was checked
    $snapshot = [
        'phases' => ['1' => '⬜', 'R6' => '✅'],
        'items' => ['1:1.1' => ['checked' => true, 'text' => '1.1 Item lama tanpa blok bukti sama sekali']],
    ];

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($fixtureWithR6Verified);

    $violations = $scanner->validate(
        $phases,
        repoRoot: $tempDir,
        textBaseline: ['1:1.1' => '1.1 Item lama tanpa blok bukti sama sekali', 'R6:R6.1' => 'R6.1 Item R6 valid'],
        snapshot: $snapshot
    );

    // Because R6 is verified, all phases must be in scope -> item 1.1 must fail!
    $phase1Violations = array_filter($violations, fn (string $v) => str_contains($v, 'Item 1.1'));
    expect($phase1Violations)->not->toBeEmpty();
    expect(array_values($phase1Violations)[0])->toContain('Item 1.1 tercentang [x] tanpa blok Bukti: (PROGRESS.md §P2).');

    // Clean up tempDir
    shell_exec('rm -rf '.escapeshellarg($tempDir));
});

it('fails sabotage check when allPhasesInScope is sabotaged to false', function (): void {
    $fixtureWithR6Verified = <<<'MD'
# PROGRESS

### FASE R6 — VERIFIKASI ULANG ITEM FASE 26–63
> **Status audit:** ✅ TERVERIFIKASI (2026-10-17)

- [x] R6.1 Item R6 valid
  - Bukti:
    - commit: `37aebb76`
    - file: `composer.json`
    - test: `tests/Architecture/ProgressIntegrityTest.php::test_progress_integrity`
    - gate: `docs/gates/fase-r6.md`

### FASE 1 — ARSITEKTUR AWAL
> **Status audit:** ⬜ BELUM DIMULAI (2026-09-01)

- [x] 1.1 Item lama tanpa blok bukti sama sekali
MD;

    // Under snapshot where 1.1 was already checked and 1 was ⬜:
    $snapshot = [
        'phases' => ['1' => '⬜', 'R6' => '✅'],
        'items' => ['1:1.1' => ['checked' => true, 'text' => '1.1 Item lama tanpa blok bukti sama sekali']],
    ];

    $fixtureWithR6Sabotaged = str_replace('✅ TERVERIFIKASI (2026-10-17)', '🔨 DALAM PROGRES', $fixtureWithR6Verified);

    $scanner = new ProgressIntegrityScanner;
    $phasesSabotaged = $scanner->parse($fixtureWithR6Sabotaged);

    $tempDir = sys_get_temp_dir().'/scanner_sabotage_test_'.uniqid();
    mkdir($tempDir, 0777, true);

    $violationsSabotaged = $scanner->validate(
        $phasesSabotaged,
        repoRoot: $tempDir,
        textBaseline: ['1:1.1' => '1.1 Item lama tanpa blok bukti sama sekali', 'R6:R6.1' => 'R6.1 Item R6 valid'],
        snapshot: $snapshot
    );

    $phase1ViolationsSabotaged = array_filter($violationsSabotaged, fn (string $v) => str_contains($v, 'Item 1.1'));

    // In sabotaged state (allPhasesInScope = false), Fase 1 was skipped:
    expect($phase1ViolationsSabotaged)->toBeEmpty();

    // Now verify that on the real codebase, allPhasesInScope is active and will not allow skipping:
    $scannerReal = new ProgressIntegrityScanner;
    $phasesReal = $scannerReal->parse($fixtureWithR6Verified);
    $violationsReal = $scannerReal->validate(
        $phasesReal,
        repoRoot: $tempDir,
        textBaseline: ['1:1.1' => '1.1 Item lama tanpa blok bukti sama sekali', 'R6:R6.1' => 'R6.1 Item R6 valid'],
        snapshot: $snapshot
    );

    $phase1ViolationsReal = array_filter($violationsReal, fn (string $v) => str_contains($v, 'Item 1.1'));
    expect($phase1ViolationsReal)->not->toBeEmpty();

    shell_exec('rm -rf '.escapeshellarg($tempDir));
});
