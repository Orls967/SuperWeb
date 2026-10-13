<?php

declare(strict_types=1);

use App\Console\Commands\MutationTestCommand;
use Tests\TestCase;

/*
| PROGRESS R0.12: MutationTestingContractTest (K-27, KONSEP §A14.8).
| Memvalidasi konfigurasi dan kontrak mutation testing Pest --mutate dengan ambang 60%.
*/

uses(TestCase::class);

it('verifies MutationTestCommand is registered and constructs correct Pest CLI invocation', function (): void {
    $this->artisan('test:mutate', [
        '--dry-run' => true,
        '--class' => ['App\\Quality\\Progress\\ProgressIntegrityScanner'],
    ])
        ->expectsOutputToContain('vendor/bin/pest --mutate --min=60 --ignore-min-score-on-zero-mutations --class=App\\Quality\\Progress\\ProgressIntegrityScanner')
        ->assertSuccessful();
});

it('verifies CI workflow defines mutation testing job with PCOV and 60% minimum threshold', function (): void {
    $root = dirname(__DIR__, 2);
    $workflowPath = $root.'/.github/workflows/ci.yml';

    expect(is_file($workflowPath))->toBeTrue();

    $content = (string) file_get_contents($workflowPath);

    expect($content)->toContain('mutation-testing:')
        ->and($content)->toContain('coverage: pcov')
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
