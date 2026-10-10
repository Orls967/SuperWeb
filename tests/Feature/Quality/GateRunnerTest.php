<?php

declare(strict_types=1);

namespace Tests\Feature\Quality;

use App\Quality\Gate\GateRunner;
use Illuminate\Support\Facades\File;

it('isolates gate db commands to storage/gate/gate.sqlite and recreates it (V7)', function (): void {
    $tempDir = sys_get_temp_dir().'/gate_runner_test_'.uniqid();
    File::ensureDirectoryExists($tempDir.'/storage/logs');
    File::ensureDirectoryExists($tempDir.'/storage/gate');

    $gateDb = $tempDir.'/storage/gate/gate.sqlite';
    File::put($gateDb, 'old_corrupted_content');

    $runner = new GateRunner($tempDir);

    // Reflection check on preparation of gate db
    $reflection = new \ReflectionClass($runner);
    if ($reflection->hasMethod('prepareGateDatabase')) {
        $method = $reflection->getMethod('prepareGateDatabase');
        $method->setAccessible(true);
        $preparedDb = $method->invoke($runner, $tempDir);

        expect($preparedDb)->toBe($gateDb)
            ->and(File::exists($gateDb))->toBeTrue()
            ->and(File::get($gateDb))->toBe(''); // recreated empty
    } else {
        expect(false)->toBeTrue('GateRunner must have prepareGateDatabase method isolating DB to storage/gate/gate.sqlite (V7)');
    }

    File::deleteDirectory($tempDir);
});

it('correctly resolves git commit hash and falls back to zeros when not in a git repo', function (): void {
    $runner = new GateRunner();
    $reflection = new \ReflectionClass($runner);
    $method = $reflection->getMethod('resolveCommitHash');
    $method->setAccessible(true);

    $actualHash = $method->invoke($runner, base_path());
    expect($actualHash)->toBeString()
        ->and(strlen($actualHash))->toBe(40)
        ->and($actualHash)->not->toBe('0000000000000000000000000000000000000000');

    $tempNonGitDir = sys_get_temp_dir().'/non_git_'.uniqid();
    File::ensureDirectoryExists($tempNonGitDir);
    $fallbackHash = $method->invoke($runner, $tempNonGitDir);
    expect($fallbackHash)->toBe('0000000000000000000000000000000000000000');
    File::deleteDirectory($tempNonGitDir);
});

it('accurately detects dirty working tree while ignoring sqlite and storage/logs (PROGRESS R0.2, Bagian I)', function (): void {
    $tempGitDir = sys_get_temp_dir().'/git_test_'.uniqid();
    File::ensureDirectoryExists($tempGitDir.'/app');
    File::ensureDirectoryExists($tempGitDir.'/storage/gate');
    File::ensureDirectoryExists($tempGitDir.'/storage/logs');
    File::ensureDirectoryExists($tempGitDir.'/database');

    // Inisialisasi repo git
    shell_exec('git -C '.escapeshellarg($tempGitDir).' init -b main 2>/dev/null || git -C '.escapeshellarg($tempGitDir).' init 2>/dev/null');
    shell_exec('git -C '.escapeshellarg($tempGitDir).' config user.email "test@example.com"');
    shell_exec('git -C '.escapeshellarg($tempGitDir).' config user.name "Test Runner"');

    File::put($tempGitDir.'/app/Initial.php', "<?php\n");
    shell_exec('git -C '.escapeshellarg($tempGitDir).' add app/Initial.php');
    shell_exec('git -C '.escapeshellarg($tempGitDir).' commit -m "initial commit"');

    $runner = new GateRunner($tempGitDir);
    $reflection = new \ReflectionClass($runner);
    $method = $reflection->getMethod('resolveIsDirty');
    $method->setAccessible(true);

    // Keadaan bersih
    expect($method->invoke($runner, $tempGitDir))->toBeFalse();

    // 1. Perubahan di storage/gate/gate.sqlite -> dirty=false
    File::put($tempGitDir.'/storage/gate/gate.sqlite', 'db');
    expect($method->invoke($runner, $tempGitDir))->toBeFalse();

    // 2. Perubahan di storage/logs/arch-scan.json -> dirty=false
    File::put($tempGitDir.'/storage/logs/arch-scan.json', '{}');
    expect($method->invoke($runner, $tempGitDir))->toBeFalse();

    // 3. Perubahan di database/database.sqlite -> dirty=false
    File::put($tempGitDir.'/database/database.sqlite', 'sq');
    expect($method->invoke($runner, $tempGitDir))->toBeFalse();

    // 4. File *.sqlite atau .sqlite-wal -> dirty=false
    File::put($tempGitDir.'/custom.sqlite', 'sq');
    File::put($tempGitDir.'/db.sqlite-wal', 'wal');
    expect($method->invoke($runner, $tempGitDir))->toBeFalse();

    // 5. Bagian I1: Perubahan pada file terlacak di app/ -> dirty=TRUE!
    File::put($tempGitDir.'/app/Initial.php', "<?php\n// modified\n");
    expect($method->invoke($runner, $tempGitDir))->toBeTrue();

    // Kembalikan file app/Initial.php
    shell_exec('git -C '.escapeshellarg($tempGitDir).' checkout app/Initial.php');
    expect($method->invoke($runner, $tempGitDir))->toBeFalse();

    // File baru terlacak di app/ -> dirty=TRUE!
    File::put($tempGitDir.'/app/NewClass.php', "<?php\n");
    expect($method->invoke($runner, $tempGitDir))->toBeTrue();
    File::delete($tempGitDir.'/app/NewClass.php');

    // 6. Boundary checks for prefix & suffix to kill mutators
    // File with prefix storage/gatekeeper.php must NOT be ignored (dirty = true)
    File::put($tempGitDir.'/storage/gatekeeper.php', "<?php\n");
    expect($method->invoke($runner, $tempGitDir))->toBeTrue();
    File::delete($tempGitDir.'/storage/gatekeeper.php');

    // File with prefix storage/logstash.json must NOT be ignored (dirty = true)
    File::put($tempGitDir.'/storage/logstash.json', '{}');
    expect($method->invoke($runner, $tempGitDir))->toBeTrue();
    File::delete($tempGitDir.'/storage/logstash.json');

    // File database/database.sqlite.bak must NOT be ignored (dirty = true)
    File::put($tempGitDir.'/database/database.sqlite.bak', 'bak');
    expect($method->invoke($runner, $tempGitDir))->toBeTrue();
    File::delete($tempGitDir.'/database/database.sqlite.bak');

    // File sqlite.txt must NOT be ignored (dirty = true)
    File::put($tempGitDir.'/sqlite.txt', 'txt');
    expect($method->invoke($runner, $tempGitDir))->toBeTrue();
    File::delete($tempGitDir.'/sqlite.txt');

    // File test.sqlite_bak (not containing .sqlite-) must NOT be ignored (dirty = true)
    File::put($tempGitDir.'/test.sqlite_bak', 'bak');
    expect($method->invoke($runner, $tempGitDir))->toBeTrue();
    File::delete($tempGitDir.'/test.sqlite_bak');

    File::deleteDirectory($tempGitDir);
});

it('executes gate steps via run, handles failures, streams logs, and generates gate manifest', function (): void {
    $tempDir = sys_get_temp_dir().'/runner_exec_'.uniqid();
    File::ensureDirectoryExists($tempDir);

    $runner = new GateRunner($tempDir);
    $logged = [];
    $manifestPath = $tempDir.'/nested/sub/custom-manifest.json';

    // Step pertama 'composer validate --strict' akan gagal di tempDir kosong
    $exitCode = $runner->run($manifestPath, function (string $type, string $buffer) use (&$logged): void {
        $logged[] = [$type, $buffer];
    });

    expect($exitCode)->toBeGreaterThan(0)
        ->and(File::exists($manifestPath))->toBeTrue();

    $rawJson = File::get($manifestPath);
    // Verifikasi formatting JSON_PRETTY_PRINT dan JSON_UNESCAPED_SLASHES
    expect($rawJson)->toContain("\n")
        ->and($rawJson)->not->toContain('\/');

    $content = json_decode($rawJson, true);
    expect($content)->toBeArray()
        ->and($content['overall_status'])->toBe('FAIL')
        ->and($content['steps'])->toHaveCount(1)
        ->and($content['steps'][0]['name'])->toBe('composer validate --strict')
        ->and($content['steps'][0]['command'])->toBe('composer validate --strict')
        ->and($content['steps'][0]['status'])->toBe('FAIL')
        ->and($content['steps'][0]['exit_code'])->toBeGreaterThan(0)
        ->and($content['steps'][0]['duration'])->toBeGreaterThanOrEqual(0.0)
        ->and($logged)->not->toBeEmpty();

    $logTypes = array_column($logged, 0);
    $logBuffers = implode("\n", array_column($logged, 1));
    expect($logTypes)->toContain('info')
        ->and($logTypes)->toContain('error')
        ->and($logBuffers)->toContain('==> [Gate Step]')
        ->and($logBuffers)->toContain("Step 'composer validate --strict' FAILED");

    File::deleteDirectory($tempDir);
});

it('strictly validates all 9 mandatory steps and commands in MANDATORY_STEPS', function (): void {
    expect(GateRunner::MANDATORY_STEPS)->toHaveCount(9);

    $steps = GateRunner::MANDATORY_STEPS;
    expect($steps[0])->toBe(['name' => 'composer validate --strict', 'command' => 'composer validate --strict'])
        ->and($steps[1])->toBe(['name' => 'pest', 'command' => 'vendor/bin/pest --parallel --log-junit=storage/logs/pest-junit.xml'])
        ->and($steps[2])->toBe(['name' => 'pint --test', 'command' => 'vendor/bin/pint --test'])
        ->and($steps[3])->toBe(['name' => 'arch:scan --json', 'command' => 'php artisan arch:scan --json'])
        ->and($steps[4])->toBe(['name' => 'migrate:fresh --seed', 'command' => 'php artisan migrate:fresh --seed --force'])
        ->and($steps[5])->toBe(['name' => 'bank:reconcile', 'command' => 'php artisan bank:reconcile'])
        ->and($steps[6])->toBe(['name' => 'chain:audit-all', 'command' => 'php artisan chain:audit-all'])
        ->and($steps[7])->toBe(['name' => 'super:health-check', 'command' => 'php artisan super:health-check'])
        ->and($steps[8])->toBe(['name' => 'npm run build', 'command' => 'npm run build']);
});

it('executes step with exit code 0 then fails on subsequent step, verifying PASS recording and environment isolation', function (): void {
    $tempDir = sys_get_temp_dir().'/runner_pass_fail_'.uniqid();
    File::ensureDirectoryExists($tempDir);

    // Buat composer.json minimal yang valid agar 'composer validate --strict' sukses
    $composerJson = [
        'name' => 'test/valid-fixture',
        'description' => 'valid fixture for testing gate runner',
        'license' => 'MIT',
    ];
    File::put($tempDir.'/composer.json', json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $runner = new GateRunner($tempDir);
    $logged = [];
    $manifestPath = $tempDir.'/storage/logs/gate-manifest.json';

    $exitCode = $runner->run($manifestPath, function (string $type, string $buffer) use (&$logged): void {
        $logged[] = [$type, $buffer];
    });

    // Step 1 lolos (exit 0, status PASS), step 2 pest gagal karena vendor/bin/pest tidak ada
    expect($exitCode)->toBeGreaterThan(0)
        ->and(File::exists($manifestPath))->toBeTrue();

    $content = json_decode(File::get($manifestPath), true);
    expect($content)->toBeArray()
        ->and($content['steps'])->toHaveCount(2)
        ->and($content['steps'][0]['name'])->toBe('composer validate --strict')
        ->and($content['steps'][0]['status'])->toBe('PASS')
        ->and($content['steps'][0]['exit_code'])->toBe(0)
        ->and($content['steps'][1]['name'])->toBe('pest')
        ->and($content['steps'][1]['status'])->toBe('FAIL')
        ->and($content['steps'][1]['exit_code'])->toBeGreaterThan(0)
        ->and($content['overall_status'])->toBe('FAIL');

    File::deleteDirectory($tempDir);
});

it('executes full 9-step gate pipeline successfully and produces clean manifest and arch-scan output', function (): void {
    $tempDir = sys_get_temp_dir().'/runner_full_pass_'.uniqid();
    File::ensureDirectoryExists($tempDir.'/vendor/bin');
    File::ensureDirectoryExists($tempDir.'/storage/logs');

    // 1. composer.json
    File::put($tempDir.'/composer.json', json_encode([
        'name' => 'stub/gate-all',
        'description' => 'stub for testing gate runner all steps pass',
        'license' => 'MIT',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // 2. vendor/bin/pest & pint
    $pestStub = $tempDir.'/vendor/bin/pest';
    File::put($pestStub, "#!/bin/sh\nexit 0\n");
    chmod($pestStub, 0755);

    $pintStub = $tempDir.'/vendor/bin/pint';
    File::put($pintStub, "#!/bin/sh\nexit 0\n");
    chmod($pintStub, 0755);

    // 3. artisan
    $artisanStub = $tempDir.'/artisan';
    File::put($artisanStub, "#!/usr/bin/env php\n<?php\nif (in_array('--json', \$argv, true)) { echo json_encode(['violations' => []]); }\nexit(0);\n");
    chmod($artisanStub, 0755);

    // 4. package.json
    File::put($tempDir.'/package.json', json_encode([
        'name' => 'stub-all',
        'scripts' => [
            'build' => 'node -e "process.exit(0)"',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $runner = new GateRunner($tempDir);
    $logged = [];
    $manifestPath = $tempDir.'/storage/logs/gate-manifest.json';

    $exitCode = $runner->run($manifestPath, function (string $type, string $buffer) use (&$logged): void {
        $logged[] = [$type, $buffer];
    });

    expect($exitCode)->toBe(0)
        ->and(File::exists($manifestPath))->toBeTrue()
        ->and(File::exists($tempDir.'/storage/logs/arch-scan.json'))->toBeTrue();

    $content = json_decode(File::get($manifestPath), true);
    expect($content)->toBeArray()
        ->and($content['overall_status'])->toBe('PASS')
        ->and($content['steps'])->toHaveCount(9);

    foreach ($content['steps'] as $step) {
        expect($step['status'])->toBe('PASS')
            ->and($step['exit_code'])->toBe(0);
    }

    File::deleteDirectory($tempDir);
});



