<?php

declare(strict_types=1);

use App\Quality\Gate\GateManifest;
use App\Quality\Gate\GateRunner;

/*
| PROGRESS R0.2 (P4): GateCommandContractTest.
| Memastikan composer gate memanggil 9 langkah wajib, tidak memakai flag --dirty,
| dan mendefinisikan manifest pencatatan metrik asli.
*/

it('verifies composer gate script does not use prohibited --dirty flag', function (): void {
    $composerJson = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);
    $gateScript = $composerJson['scripts']['gate'] ?? [];

    $gateString = is_array($gateScript) ? implode(' ', $gateScript) : (string) $gateScript;

    expect($gateString)->not->toContain('--dirty', 'Script composer gate dilarang memakai flag --dirty (R0.2).');
});

it('verifies GateRunner defines all 9 mandatory steps and forbids --dirty flag', function (): void {
    $steps = GateRunner::MANDATORY_STEPS;
    $commands = array_column($steps, 'command');
    $stepNames = array_column($steps, 'name');

    foreach (GateManifest::REQUIRED_STEP_KEYS as $requiredKey) {
        $found = false;
        foreach ($commands as $cmd) {
            if (str_contains($cmd, $requiredKey)) {
                $found = true;
                break;
            }
        }
        if (! $found) {
            foreach ($stepNames as $name) {
                if (str_contains($name, $requiredKey)) {
                    $found = true;
                    break;
                }
            }
        }
        expect($found)->toBeTrue("Langkah wajib '{$requiredKey}' harus ada di GateRunner::MANDATORY_STEPS.");
    }

    foreach ($commands as $cmd) {
        expect(str_contains($cmd, '--dirty'))->toBeFalse("Perintah '{$cmd}' di GateRunner dilarang memuat flag --dirty.");
    }
});
