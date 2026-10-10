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
