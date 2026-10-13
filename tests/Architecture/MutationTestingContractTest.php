<?php

declare(strict_types=1);

use App\Console\Commands\MutationTestCommand;
use App\Quality\Mutation\MutationTargetResolver;
use Tests\TestCase;

/*
| PROGRESS R0.12: MutationTestingContractTest (K-27, K-MUT, KONSEP §A14.8).
| Memvalidasi konfigurasi dan kontrak mutation testing Pest --mutate dengan ambang 60%,
| pemetaan kelas per modul/kualitas, dan filter ketat path git diff.
*/

uses(TestCase::class);

it('verifies MutationTestCommand is registered and constructs correct Pest CLI invocation', function (): void {
    $this->artisan('test:mutate', [
        '--dry-run' => true,
        '--class' => ['App\\Quality\\Progress\\ProgressIntegrityScanner'],
    ])
        ->expectsOutputToContain("Perintah: vendor/bin/pest tests/Unit/Quality/Progress/ProgressIntegrityScannerTest.php --mutate --min=60 --class='App\\Quality\\Progress\\ProgressIntegrityScanner'")
        ->assertSuccessful();
});

it('verifies CI workflow defines mutation testing job with PCOV and 60% minimum threshold', function (): void {
    $root = dirname(__DIR__, 2);
    $workflowPath = $root.'/.github/workflows/ci.yml';

    expect(is_file($workflowPath))->toBeTrue();

    $content = (string) file_get_contents($workflowPath);

    expect($content)->toContain('mutation-testing:')
        ->and($content)->toContain('coverage: pcov')
        ->and($content)->toContain('timeout-minutes: 45')
        ->and($content)->toContain('memory_limit=1G')
        ->and($content)->toContain('--min=60');
});

it('verifies MutationTestCommand defaults to master base and fails on invalid base branch', function (): void {
    // 1. Default base is master
    $cmd = app(MutationTestCommand::class);
    $definition = $cmd->getDefinition();
    expect($definition->getOption('base')->getDefault())->toBe('master');

    // 2. Non-zero exit code when git merge-base/diff fails
    $this->artisan('test:mutate', [
        '--git-diff' => true,
        '--base' => 'nonexistent_branch_definitely_missing_xyz',
    ])
        ->expectsOutputToContain('Gagal menjalankan git merge-base/diff')
        ->assertExitCode(1);
});

it('verifies Pest CLI supports mutation testing options', function (): void {
    $output = shell_exec('vendor/bin/pest --help 2>&1');

    expect($output)->not->toBeNull()
        ->and((string) $output)->toContain('--mutate')
        ->and((string) $output)->toContain('--min');
});

it('filters git diff changed files strictly to modules/*/Application/** and app/Quality/** non-migration non-test', function (): void {
    $fixtureFiles = [
        'modules/Hospital/Application/Actions/AdmitPatientAction.php',
        'modules/Billing/Application/Services/PaymentService.php',
        'app/Quality/Progress/ProgressIntegrityScanner.php',
        // Excluded:
        'modules/Hospital/Domain/Models/Patient.php',
        'modules/Core/Actions/LegacyAction.php',
        'modules/Hospital/tests/Feature/AdmitPatientTest.php',
        'database/migrations/2026_10_10_000000_create_test_table.php',
        'app/Http/Controllers/UserController.php',
        'docs/PROGRESS.md',
    ];

    $classes = MutationTargetResolver::resolveClasses($fixtureFiles);

    expect($classes)->toBe([
        'App\Quality\Progress\ProgressIntegrityScanner',
        'Modules\Billing\Application\Services\PaymentService',
        'Modules\Hospital\Application\Actions\AdmitPatientAction',
    ]);
});

it('outputs expected message and exits 0 when no classes are changed in git diff', function (): void {
    $this->artisan('test:mutate', [
        '--dry-run' => true,
        '--class' => [],
    ])
        ->expectsOutput('tidak ada kelas Action/Service berubah')
        ->assertExitCode(0);
});
