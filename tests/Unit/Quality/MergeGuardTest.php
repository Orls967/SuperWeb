<?php

declare(strict_types=1);

use Tests\TestCase;

/*
| Kontrak Satuan: MergeGuardTest (F-01, KONSEP §A14).
| Memastikan skrip scripts/merge-guard.sh memvalidasi status check-runs
| dari PR sebelum mengizinkan instruksi merge.
*/

uses(TestCase::class);

it('fails when no PR number argument is provided to merge-guard.sh', function (): void {
    $script = base_path('scripts/merge-guard.sh');
    $output = shell_exec("bash {$script} 2>&1");
    $exitCode = null;

    exec("bash {$script} 2>&1", $lines, $exitCode);

    expect($exitCode)->toBe(1);
    expect(implode("\n", $lines))->toContain('Penggunaan:');
});

it('fails when check runs JSON is empty (no CI jobs have run)', function (): void {
    $script = base_path('scripts/merge-guard.sh');
    $tmpJson = tempnam(sys_get_temp_dir(), 'mg_empty_').'.json';
    file_put_contents($tmpJson, json_encode(['check_runs' => []]));

    $exitCode = null;
    $lines = [];
    exec("MERGE_GUARD_CHECK_RUNS_JSON={$tmpJson} bash {$script} 99 2>&1", $lines, $exitCode);

    @unlink($tmpJson);

    expect($exitCode)->toBe(1);
    expect(implode("\n", $lines))->toContain('Belum ada check-run sama sekali');
});

it('fails and displays table when one or more CI jobs failed or cancelled', function (): void {
    $script = base_path('scripts/merge-guard.sh');
    $tmpJson = tempnam(sys_get_temp_dir(), 'mg_fail_').'.json';
    $data = [
        'check_runs' => [
            [
                'name' => 'PHP 8.4 × SQLite (Full Gate Suite)',
                'status' => 'completed',
                'conclusion' => 'failure',
            ],
            [
                'name' => 'PHP 8.4 × MySQL 8 (DB Portability)',
                'status' => 'completed',
                'conclusion' => 'cancelled',
            ],
            [
                'name' => 'Static & Architecture Gate',
                'status' => 'completed',
                'conclusion' => 'success',
            ],
        ],
    ];
    file_put_contents($tmpJson, json_encode($data));

    $exitCode = null;
    $lines = [];
    exec("MERGE_GUARD_CHECK_RUNS_JSON={$tmpJson} bash {$script} 25 2>&1", $lines, $exitCode);

    @unlink($tmpJson);

    $out = implode("\n", $lines);
    expect($exitCode)->toBe(1);
    expect($out)->toContain('=== TABEL CHECK-RUNS PR #25 ===');
    expect($out)->toContain('failure');
    expect($out)->toContain('cancelled');
    expect($out)->toContain('Dilarang me-merge PR dengan status CI merah');
});

it('fails when CI jobs are still pending or in progress', function (): void {
    $script = base_path('scripts/merge-guard.sh');
    $tmpJson = tempnam(sys_get_temp_dir(), 'mg_inprog_').'.json';
    $data = [
        'check_runs' => [
            [
                'name' => 'PHP 8.4 × SQLite (Full Gate Suite)',
                'status' => 'in_progress',
                'conclusion' => null,
            ],
        ],
    ];
    file_put_contents($tmpJson, json_encode($data));

    $exitCode = null;
    $lines = [];
    exec("MERGE_GUARD_CHECK_RUNS_JSON={$tmpJson} bash {$script} 30 2>&1", $lines, $exitCode);

    @unlink($tmpJson);

    $out = implode("\n", $lines);
    expect($exitCode)->toBe(1);
    expect($out)->toContain('Terdapat check-run yang gagal, dibatalkan, atau masih berjalan');
});

it('passes when all CI jobs are completed with success or skipped, and outputs merge command without executing it', function (): void {
    $script = base_path('scripts/merge-guard.sh');
    $tmpJson = tempnam(sys_get_temp_dir(), 'mg_pass_').'.json';
    $data = [
        'check_runs' => [
            [
                'name' => 'PHP 8.4 × SQLite (Full Gate Suite)',
                'status' => 'completed',
                'conclusion' => 'success',
            ],
            [
                'name' => 'PHP 8.4 × MySQL 8 (DB Portability)',
                'status' => 'completed',
                'conclusion' => 'success',
            ],
            [
                'name' => 'Static & Architecture Gate',
                'status' => 'completed',
                'conclusion' => 'skipped',
            ],
        ],
    ];
    file_put_contents($tmpJson, json_encode($data));

    $exitCode = null;
    $lines = [];
    exec("MERGE_GUARD_CHECK_RUNS_JSON={$tmpJson} bash {$script} 42 2>&1", $lines, $exitCode);

    @unlink($tmpJson);

    $out = implode("\n", $lines);
    expect($exitCode)->toBe(0);
    expect($out)->toContain('SEMUA CHECK-RUN HIJAU (LULUS)');
    expect($out)->toContain('gh pr merge 42 --merge');
    expect($out)->toContain('(Peringatan: JANGAN jalankan perintah merge di atas secara otomatis)');
});
