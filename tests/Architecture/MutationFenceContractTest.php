<?php

declare(strict_types=1);

use Tests\TestCase;

/*
| PROGRESS R0.12 / K-TEST: MutationFenceContractTest.
| Memastikan .github/workflows/ci.yml mendefinisikan eksekusi mutation testing
| untuk 5 kelas pagar Fase R0 dengan batas minimal --min=60 tanpa pelemahan error.
*/

uses(TestCase::class);

const FENCE_CLASSES = [
    'App\Quality\Progress\ProgressIntegrityScanner',
    'App\Quality\Gate\GateRunner',
    'App\Console\Commands\GateReportCommand',
    'App\Quality\Gate\GateManifest',
    'App\Quality\Routing\RouteAuthorizationScanner',
];

function validateMutationFenceWorkflowContent(string $yamlContent): array
{
    $violations = [];

    // 1. Periksa keberadaan 5 kelas pagar
    foreach (FENCE_CLASSES as $class) {
        $pattern = '/(?:--class=[\'"]?|--class\s+[\'"]?)'.preg_quote($class, '/').'[\'"]?/i';
        if (! preg_match($pattern, $yamlContent)) {
            $violations[] = "Workflow CI tidak memuat eksekusi mutasi untuk kelas {$class}";

            continue;
        }

        // Cari baris atau blok eksekusi untuk kelas ini
        $lines = explode("\n", $yamlContent);
        $commandLine = '';
        foreach ($lines as $line) {
            if (str_contains($line, $class)) {
                $commandLine = $line;
                break;
            }
        }

        // Periksa --min=60
        if (! preg_match('/--min=60\b/', $commandLine)) {
            $violations[] = "Ambang batas mutasi untuk kelas {$class} tidak memenuhi syarat minimal --min=60 (ditemukan: ".trim($commandLine).')';
        }

        // Periksa tanpa || true
        if (str_contains($commandLine, '|| true')) {
            $violations[] = "Perintah mutasi untuk {$class} tidak boleh menggunakan '|| true'";
        }
    }

    // 2. Periksa step mutasi tidak menggunakan continue-on-error: true
    if (preg_match('/name:\s*Mutation pagar R0[\s\S]*?(?=- name:|$)/', $yamlContent, $stepMatch)) {
        $stepContent = $stepMatch[0];
        if (preg_match('/continue-on-error:\s*true/i', $stepContent)) {
            $violations[] = 'Step mutasi pagar R0 tidak boleh menggunakan continue-on-error: true';
        }
    }

    return $violations;
}

it('verifies CI workflow defines mutation testing for all 5 fence classes with min 60% threshold', function (): void {
    $workflowPath = base_path('.github/workflows/ci.yml');
    expect(is_file($workflowPath))->toBeTrue();

    $content = (string) file_get_contents($workflowPath);
    $violations = validateMutationFenceWorkflowContent($content);

    expect($violations)->toBe([], 'Ditemukan pelanggaran kontrak pagar mutasi CI: '.implode('; ', $violations));
});

it('fails negative fixture when one of the fence classes is missing', function (): void {
    $content = (string) file_get_contents(base_path('.github/workflows/ci.yml'));

    // Hapus satu kelas dari konten (mis. GateRunner)
    $tampered = str_replace(
        "php -d memory_limit=1G vendor/bin/pest tests/Feature/Quality/GateRunnerTest.php --mutate --parallel --class='App\Quality\Gate\GateRunner' --min=60",
        '# GateRunner removed',
        $content
    );

    $violations = validateMutationFenceWorkflowContent($tampered);

    expect($violations)->toContain('Workflow CI tidak memuat eksekusi mutasi untuk kelas App\Quality\Gate\GateRunner');
});

it('fails negative fixture when min threshold is reduced to 50%', function (): void {
    $content = (string) file_get_contents(base_path('.github/workflows/ci.yml'));

    // Turunkan min dari 60 ke 50 pada salah satu kelas
    $tampered = str_replace(
        "--class='App\Quality\Progress\ProgressIntegrityScanner' --min=60",
        "--class='App\Quality\Progress\ProgressIntegrityScanner' --min=50",
        $content
    );

    $violations = validateMutationFenceWorkflowContent($tampered);

    expect(implode("\n", $violations))->toContain('tidak memenuhi syarat minimal --min=60');
});

it('fails negative fixture when continue-on-error or pipe true is present', function (): void {
    $content = (string) file_get_contents(base_path('.github/workflows/ci.yml'));

    // Tambahkan continue-on-error
    $tampered1 = str_replace(
        '- name: Mutation pagar R0',
        "- name: Mutation pagar R0\n        continue-on-error: true",
        $content
    );
    expect(validateMutationFenceWorkflowContent($tampered1))
        ->toContain('Step mutasi pagar R0 tidak boleh menggunakan continue-on-error: true');

    // Tambahkan || true
    $tampered2 = str_replace(
        "--class='App\Quality\Gate\GateManifest' --min=60",
        "--class='App\Quality\Gate\GateManifest' --min=60 || true",
        $content
    );
    expect(validateMutationFenceWorkflowContent($tampered2))
        ->toContain("Perintah mutasi untuk App\Quality\Gate\GateManifest tidak boleh menggunakan '|| true'");
});
