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
        // If method doesn't exist yet, fail
        expect(false)->toBeTrue('GateRunner must have prepareGateDatabase method isolating DB to storage/gate/gate.sqlite (V7)');
    }
});
