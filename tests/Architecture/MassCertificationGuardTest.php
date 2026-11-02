<?php

declare(strict_types=1);

use App\Quality\Progress\MassCertificationGuard;
use Tests\TestCase;

/*
| Kontrak Arsitektur: MassCertificationGuardTest (K-8, KONSEP §A14).
| Memastikan tidak ada sertifikasi massal (>15 centang atau >1 fase ✅ per commit)
| tanpa anchor DECISIONS.md, serta setiap verifikasi fase berstatus ✅ wajib
| berawalan 'docs(verifikasi-' dan hanya menyentuh direktori docs/**.
*/

uses(TestCase::class);

it('ensures no unauthorized mass certification commits exist since 8c8369d on current branch', function (): void {
    $guard = new MassCertificationGuard(base_path());
    $violations = $guard->validate();

    expect($violations)->toBe([], 'Ditemukan commit sertifikasi massal tidak berizin sejak 8c8369d.');
});

it('verifies that mass certification baseline ratchet is strictly maintained', function (): void {
    $baselinePath = base_path('tests/Architecture/baselines/mass-certification.json');
    expect(is_file($baselinePath))->toBeTrue();

    $baseline = json_decode((string) file_get_contents($baselinePath), true, 512, JSON_THROW_ON_ERROR);

    expect($baseline['anchor'])->toBe('DECISIONS.md#2026-10-30-pagar-sertifikasi-massal');
    expect($baseline['total'])->toBe(count($baseline['commits']));
    expect($baseline['total'])->toBeGreaterThanOrEqual(46);

    $decisions = (string) file_get_contents(base_path('docs/DECISIONS.md'));
    expect($decisions)->toContain('2026-10-30-pagar-sertifikasi-massal');
});

it('enforces mass certification rules on git commits using temporary git repository fixtures', function (): void {
    $tmpDir = sys_get_temp_dir().'/mass_cert_test_'.uniqid();
    mkdir($tmpDir.'/docs', 0777, true);
    mkdir($tmpDir.'/tests/Architecture/baselines', 0777, true);

    $run = function (string $cmd) use ($tmpDir): string {
        return (string) shell_exec("cd {$tmpDir} && {$cmd} 2>&1");
    };

    $run('git init -b master');
    $run('git config user.email "auditor@superweb.test"');
    $run('git config user.name "Auditor"');

    // Initial commit
    file_put_contents($tmpDir.'/docs/DECISIONS.md', "# DECISIONS\n\n## Anchor: 2026-10-30-izin-khusus\n");
    file_put_contents($tmpDir.'/docs/PROGRESS.md', "# PROGRESS\n\n### FASE 1 — CONTOH\n> **Status audit:** ⬜ BELUM DIMULAI\n\n- [ ] 1.1 Item 1\n");
    $run('git add docs/DECISIONS.md docs/PROGRESS.md && git commit -m "docs: inisialisasi"');
    $initSha = trim($run('git rev-parse HEAD'));

    $guard = new MassCertificationGuard($tmpDir);

    // Case 1: Adding 16 [x] without DECISIONS anchor -> FAILS
    $progress16 = "# PROGRESS\n\n### FASE 1 — CONTOH\n> **Status audit:** ⬜ BELUM DIMULAI\n\n";
    for ($i = 1; $i <= 16; $i++) {
        $progress16 .= "- [x] 1.{$i} Item {$i}\n";
    }
    file_put_contents($tmpDir.'/docs/PROGRESS.md', $progress16);
    $run('git add docs/PROGRESS.md && git commit -m "docs: centang 16 item sekaligus"');
    $c1Sha = trim($run('git rev-parse HEAD'));

    $violations1 = $guard->validate($tmpDir, $tmpDir.'/tests/Architecture/baselines/mass-certification.json', $initSha, $c1Sha);
    expect($violations1)->not->toBeEmpty();
    expect($violations1[0])->toContain('menambah 16 centang [x] (> 15) tanpa anchor DECISIONS.md#');

    // Reset back to init
    $run("git reset --hard {$initSha}");

    // Case 2: Adding 16 [x] WITH valid DECISIONS anchor -> PASSES
    file_put_contents($tmpDir.'/docs/PROGRESS.md', $progress16);
    $run('git add docs/PROGRESS.md && git commit -m "docs: centang 16 item (DECISIONS.md#2026-10-30-izin-khusus)"');
    $c2Sha = trim($run('git rev-parse HEAD'));

    $violations2 = $guard->validate($tmpDir, $tmpDir.'/tests/Architecture/baselines/mass-certification.json', $initSha, $c2Sha);
    expect($violations2)->toBeEmpty();

    $run("git reset --hard {$initSha}");

    // Case 3: Verifying 2 phases to ✅ without anchor -> FAILS
    $progress2Verified = "# PROGRESS\n\n### FASE 1 — CONTOH\n> **Status audit:** ✅ TERVERIFIKASI\n\n### FASE 2 — CONTOH\n> **Status audit:** ✅ TERVERIFIKASI\n";
    file_put_contents($tmpDir.'/docs/PROGRESS.md', $progress2Verified);
    $run('git add docs/PROGRESS.md && git commit -m "docs(verifikasi-fase): verifikasi 2 fase sekaligus"');
    $c3Sha = trim($run('git rev-parse HEAD'));

    $violations3 = $guard->validate($tmpDir, $tmpDir.'/tests/Architecture/baselines/mass-certification.json', $initSha, $c3Sha);
    expect($violations3)->not->toBeEmpty();
    expect($violations3[0])->toContain('memverifikasi 2 fase menjadi ✅ (> 1) tanpa anchor DECISIONS.md#');

    $run("git reset --hard {$initSha}");

    // Case 4: Verifying 1 phase to ✅ with wrong commit prefix -> FAILS
    $progress1Verified = "# PROGRESS\n\n### FASE 1 — CONTOH\n> **Status audit:** ✅ TERVERIFIKASI\n";
    file_put_contents($tmpDir.'/docs/PROGRESS.md', $progress1Verified);
    $run('git add docs/PROGRESS.md && git commit -m "feat(fase-1): verifikasi fase 1"');
    $c4Sha = trim($run('git rev-parse HEAD'));

    $violations4 = $guard->validate($tmpDir, $tmpDir.'/tests/Architecture/baselines/mass-certification.json', $initSha, $c4Sha);
    expect($violations4)->not->toBeEmpty();
    expect($violations4[0])->toContain("judul commit tidak berawalan 'docs(verifikasi-'");

    $run("git reset --hard {$initSha}");

    // Case 5: Verifying 1 phase to ✅ but modifying files outside docs/ -> FAILS
    mkdir($tmpDir.'/app', 0777, true);
    file_put_contents($tmpDir.'/app/Example.php', "<?php\n");
    file_put_contents($tmpDir.'/docs/PROGRESS.md', $progress1Verified);
    $run('git add app/Example.php docs/PROGRESS.md && git commit -m "docs(verifikasi-fase-1): verifikasi fase 1 dan ubah kode"');
    $c5Sha = trim($run('git rev-parse HEAD'));

    $violations5 = $guard->validate($tmpDir, $tmpDir.'/tests/Architecture/baselines/mass-certification.json', $initSha, $c5Sha);
    expect($violations5)->not->toBeEmpty();
    expect($violations5[0])->toContain('menyentuh file di luar docs/ (app/Example.php)');

    $run("git reset --hard {$initSha}");

    // Case 6: Verifying 1 phase to ✅ cleanly with prefix and docs-only changes -> PASSES
    mkdir($tmpDir.'/docs/gates', 0777, true);
    file_put_contents($tmpDir.'/docs/PROGRESS.md', $progress1Verified);
    file_put_contents($tmpDir.'/docs/gates/fase-1.md', "# Gate report 1\n");
    $run('git add docs/PROGRESS.md docs/gates/fase-1.md && git commit -m "docs(verifikasi-fase-1): verifikasi resmi fase 1"');
    $c6Sha = trim($run('git rev-parse HEAD'));

    $violations6 = $guard->validate($tmpDir, $tmpDir.'/tests/Architecture/baselines/mass-certification.json', $initSha, $c6Sha);
    expect($violations6)->toBeEmpty();

    shell_exec("rm -rf {$tmpDir}");
});
