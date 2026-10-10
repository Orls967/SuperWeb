<?php

declare(strict_types=1);

/*
| PROGRESS R0.3: CiWorkflowContractTest (K-04, K-28).
| Memvalidasi kontrak konfigurasi CI GitHub Actions (.github/workflows/ci.yml)
| dan isolasi phpunit.xml.
*/

it('enforces MySQL 8 image in CI workflow and rejects MySQL 5.7', function (): void {
    $workflowPath = dirname(__DIR__, 2).'/.github/workflows/ci.yml';
    $content = (string) file_get_contents($workflowPath);

    expect($content)->toContain('image: mysql:8.')
        ->and($content)->not->toContain('mysql:5.7');
});

it('enforces composer gate full execution without skip-build flag', function (): void {
    $workflowPath = dirname(__DIR__, 2).'/.github/workflows/ci.yml';
    $content = (string) file_get_contents($workflowPath);

    expect($content)->toContain('composer gate')
        ->and($content)->not->toContain('--skip-build');
});

it('enforces db-portability group with migrate:fresh --seed on MySQL job', function (): void {
    $workflowPath = dirname(__DIR__, 2).'/.github/workflows/ci.yml';
    $content = (string) file_get_contents($workflowPath);

    expect($content)->toContain('php artisan migrate:fresh --seed')
        ->and($content)->toContain('vendor/bin/pest --group=db-portability');
});

it('enforces phpunit.xml excludes db-portability from default SQLite suite', function (): void {
    $phpunitPath = dirname(__DIR__, 2).'/phpunit.xml';
    $content = (string) file_get_contents($phpunitPath);

    expect($content)->toContain('<group>db-portability</group>')
        ->and($content)->toContain('<exclude>');
});

it('enforces PR checkout using PR head SHA instead of merge commit or main ref', function (): void {
    $workflowPath = dirname(__DIR__, 2).'/.github/workflows/ci.yml';
    $content = (string) file_get_contents($workflowPath);

    expect($content)->toContain('ref: ${{ github.event.pull_request.head.sha')
        ->and($content)->not->toMatch('/uses:\s*actions\/checkout@v4\s*\n\s*with:\s*\n\s*ref:\s*main\b/');
});

it('enforces gate report and junit artifact upload is always executed', function (): void {
    $workflowPath = dirname(__DIR__, 2).'/.github/workflows/ci.yml';
    $content = (string) file_get_contents($workflowPath);

    expect($content)->toContain('if: always()')
        ->and($content)->toContain('name: gate-report-fase-r0');
});
