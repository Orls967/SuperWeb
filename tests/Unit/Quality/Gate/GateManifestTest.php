<?php

declare(strict_types=1);

namespace Tests\Unit\Quality\Gate;

use App\Quality\Gate\GateManifest;

it('rejects incomplete steps and steps named empty string or single letter like p (V6)', function (): void {
    $manifestData = [
        'commit' => '1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b',
        'is_dirty' => false,
        'timestamp' => '2026-10-11T00:00:00+00:00',
        'overall_status' => 'PASS',
        'steps' => [
            [
                'name' => 'p',
                'command' => 'p',
                'exit_code' => 0,
                'duration' => 0.01,
                'status' => 'PASS',
            ],
            [
                'name' => '',
                'command' => '',
                'exit_code' => 0,
                'duration' => 0.01,
                'status' => 'PASS',
            ],
        ],
    ];

    $manifest = GateManifest::fromJson(json_encode($manifestData));
    expect($manifest)->not->toBeNull();

    $missing = $manifest->missingRequiredSteps();

    // V6: Must reject short or empty names like "p" or "" and report missing required steps
    expect($missing)->toContain('pest')
        ->and($missing)->toContain('composer validate --strict')
        ->and($missing)->toContain('pint --test');
});

it('tests serialization, deserialization and file reading in GateManifest', function (): void {
    expect(GateManifest::fromJson('invalid-json'))->toBeNull();

    $tempFile = tempnam(sys_get_temp_dir(), 'manifest_');
    $data = [
        'commit' => 'abcdef1234567890abcdef1234567890abcdef12',
        'is_dirty' => false,
        'timestamp' => '2026-10-10T12:00:00+00:00',
        'overall_status' => 'PASS',
        'steps' => [
            [
                'name' => 'composer validate --strict',
                'command' => 'composer validate --strict',
                'exit_code' => 0,
                'duration' => 0.15,
                'status' => 'PASS',
            ],
        ],
    ];
    file_put_contents($tempFile, json_encode($data));

    expect(GateManifest::fromFile('/path/that/does/not/exist/foo.json'))->toBeNull();

    $manifest = GateManifest::fromFile($tempFile);
    expect($manifest)->not->toBeNull()
        ->and($manifest->commit)->toBe('abcdef1234567890abcdef1234567890abcdef12')
        ->and($manifest->isDirty)->toBeFalse()
        ->and($manifest->timestamp)->toBe('2026-10-10T12:00:00+00:00')
        ->and($manifest->overallStatus)->toBe('PASS')
        ->and($manifest->steps)->toHaveCount(1);

    $arr = $manifest->toArray();
    expect($arr)->toBe([
        'commit' => 'abcdef1234567890abcdef1234567890abcdef12',
        'is_dirty' => false,
        'timestamp' => '2026-10-10T12:00:00+00:00',
        'overall_status' => 'PASS',
        'steps' => [
            [
                'name' => 'composer validate --strict',
                'command' => 'composer validate --strict',
                'exit_code' => 0,
                'duration' => 0.15,
                'status' => 'PASS',
            ],
        ],
    ]);

    @unlink($tempFile);
});

it('evaluates isClean and firstFailedStep correctly', function (): void {
    $cleanManifest = new GateManifest(
        commit: 'abc',
        isDirty: false,
        timestamp: 'now',
        overallStatus: 'PASS',
        steps: [
            ['name' => 'pest', 'command' => 'pest', 'exit_code' => 0, 'duration' => 1.0, 'status' => 'PASS'],
            ['name' => 'pint --test', 'command' => 'pint --test', 'exit_code' => 0, 'duration' => 1.0, 'status' => 'PASS'],
        ]
    );

    expect($cleanManifest->isClean())->toBeTrue()
        ->and($cleanManifest->firstFailedStep())->toBeNull();

    // Overall status not PASS
    $dirtyStatusManifest = new GateManifest(
        commit: 'abc',
        isDirty: false,
        timestamp: 'now',
        overallStatus: 'FAIL',
        steps: [
            ['name' => 'pest', 'command' => 'pest', 'exit_code' => 0, 'duration' => 1.0, 'status' => 'PASS'],
        ]
    );
    expect($dirtyStatusManifest->isClean())->toBeFalse();

    // Step exit code non-zero
    $failedExitManifest = new GateManifest(
        commit: 'abc',
        isDirty: false,
        timestamp: 'now',
        overallStatus: 'PASS',
        steps: [
            ['name' => 'pest', 'command' => 'pest', 'exit_code' => 2, 'duration' => 1.0, 'status' => 'PASS'],
        ]
    );
    expect($failedExitManifest->isClean())->toBeFalse()
        ->and($failedExitManifest->firstFailedStep())->toBe(['name' => 'pest', 'command' => 'pest', 'exit_code' => 2, 'duration' => 1.0, 'status' => 'PASS']);

    // Step status FAIL
    $failedStatusManifest = new GateManifest(
        commit: 'abc',
        isDirty: false,
        timestamp: 'now',
        overallStatus: 'PASS',
        steps: [
            ['name' => 'pest', 'command' => 'pest', 'exit_code' => 0, 'duration' => 1.0, 'status' => 'FAIL'],
        ]
    );
    expect($failedStatusManifest->isClean())->toBeFalse()
        ->and($failedStatusManifest->firstFailedStep())->toBe(['name' => 'pest', 'command' => 'pest', 'exit_code' => 0, 'duration' => 1.0, 'status' => 'FAIL']);

    // Step missing status or exit_code defaults to fail
    $missingStatusManifest = new GateManifest(
        commit: 'abc',
        isDirty: false,
        timestamp: 'now',
        overallStatus: 'PASS',
        steps: [
            ['name' => 'step1'],
        ]
    );
    expect($missingStatusManifest->isClean())->toBeFalse()
        ->and($missingStatusManifest->firstFailedStep())->toBe(['name' => 'step1']);
});

it('tests matchesCommit and isWorkingTreeClean', function (): void {
    $manifestClean = new GateManifest(
        commit: '1234567890abcdef',
        isDirty: false,
        timestamp: 'now',
        overallStatus: 'PASS'
    );
    expect($manifestClean->isWorkingTreeClean())->toBeTrue()
        ->and($manifestClean->matchesCommit('1234567890abcdef'))->toBeTrue()
        ->and($manifestClean->matchesCommit('1234567890abcdef1234567890'))->toBeTrue()
        ->and($manifestClean->matchesCommit('12345678'))->toBeTrue()
        ->and($manifestClean->matchesCommit('different'))->toBeFalse()
        ->and($manifestClean->matchesCommit(''))->toBeFalse();

    $emptyCommitManifest = new GateManifest(commit: '', isDirty: true, timestamp: '', overallStatus: 'FAIL');
    expect($emptyCommitManifest->isWorkingTreeClean())->toBeFalse()
        ->and($emptyCommitManifest->matchesCommit('1234567890abcdef'))->toBeFalse();
});

it('matches required steps using commands and name prefixes', function (): void {
    $manifest = new GateManifest(
        commit: 'abc',
        isDirty: false,
        timestamp: 'now',
        overallStatus: 'PASS',
        steps: [
            ['name' => 'composer validate --strict', 'command' => 'composer validate --strict', 'exit_code' => 0, 'duration' => 0.1, 'status' => 'PASS'],
            ['name' => 'custom-pest', 'command' => 'vendor/bin/pest --parallel', 'exit_code' => 0, 'duration' => 0.1, 'status' => 'PASS'],
            ['name' => 'pint', 'command' => 'pint --test', 'exit_code' => 0, 'duration' => 0.1, 'status' => 'PASS'],
            ['name' => 'arch', 'command' => 'php artisan arch:scan --json', 'exit_code' => 0, 'duration' => 0.1, 'status' => 'PASS'],
            ['name' => 'migrate', 'command' => 'php artisan migrate:fresh --seed --force', 'exit_code' => 0, 'duration' => 0.1, 'status' => 'PASS'],
            ['name' => 'bank', 'command' => 'php artisan bank:reconcile', 'exit_code' => 0, 'duration' => 0.1, 'status' => 'PASS'],
            ['name' => 'chain', 'command' => 'php artisan chain:audit-all', 'exit_code' => 0, 'duration' => 0.1, 'status' => 'PASS'],
            ['name' => 'health', 'command' => 'php artisan super:health-check', 'exit_code' => 0, 'duration' => 0.1, 'status' => 'PASS'],
            ['name' => 'build', 'command' => 'npm run build', 'exit_code' => 0, 'duration' => 0.1, 'status' => 'PASS'],
        ]
    );

    expect($manifest->missingRequiredSteps())->toBeEmpty();
});
