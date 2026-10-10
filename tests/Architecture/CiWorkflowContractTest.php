<?php

declare(strict_types=1);

/*
| PROGRESS R0.3: CiWorkflowContractTest (K-04, K-28).
| Memvalidasi kontrak konfigurasi CI GitHub Actions (.github/workflows/ci.yml)
| dan isolasi phpunit.xml.
*/

it('enforces workflow triggers include master branch for push and pull_request', function (): void {
    $workflowPath = dirname(__DIR__, 2).'/.github/workflows/ci.yml';
    $content = (string) file_get_contents($workflowPath);

    expect($content)->toMatch('/push:\s*\n\s*branches:\s*\n\s*-\s*master\b/')
        ->and($content)->toMatch('/pull_request:\s*\n\s*branches:\s*\n\s*-\s*master\b/');
});

it('enforces fetch-depth 0 in CI checkout steps', function (): void {
    $workflowPath = dirname(__DIR__, 2).'/.github/workflows/ci.yml';
    $content = (string) file_get_contents($workflowPath);

    // Harus ada 3 kali fetch-depth: 0 (pada gate-sqlite, portability-mysql, mutation-testing)
    expect(substr_count($content, 'fetch-depth: 0'))->toBeGreaterThanOrEqual(3);
});

it('enforces setup environment with app key generation before gate and portability tests', function (): void {
    $workflowPath = dirname(__DIR__, 2).'/.github/workflows/ci.yml';
    $content = (string) file_get_contents($workflowPath);

    expect($content)->toContain('cp .env.example .env')
        ->and($content)->toContain('php artisan key:generate --ansi');
});

it('enforces MySQL 8.4 image and rejects MySQL 8.0 and 5.7', function (): void {
    $workflowPath = dirname(__DIR__, 2).'/.github/workflows/ci.yml';
    $content = (string) file_get_contents($workflowPath);

    expect($content)->toContain('image: mysql:8.4')
        ->and($content)->not->toContain('mysql:8.0')
        ->and($content)->not->toContain('mysql:5.7');
});

it('enforces mysqladmin ping health check with password', function (): void {
    $workflowPath = dirname(__DIR__, 2).'/.github/workflows/ci.yml';
    $content = (string) file_get_contents($workflowPath);

    expect($content)->toContain('mysqladmin ping -h 127.0.0.1 -proot');
});

it('enforces migrate:fresh --seed with force flag on MySQL portability job', function (): void {
    $workflowPath = dirname(__DIR__, 2).'/.github/workflows/ci.yml';
    $content = (string) file_get_contents($workflowPath);

    expect($content)->toContain('php artisan migrate:fresh --seed --force')
        ->and($content)->toContain('vendor/bin/pest --group=db-portability');
});

it('enforces gate-sqlite does not duplicate arch:scan or audit commands outside composer gate', function (): void {
    $workflowPath = dirname(__DIR__, 2).'/.github/workflows/ci.yml';
    $content = (string) file_get_contents($workflowPath);

    // Pastikan tidak ada step terpisah di luar composer gate
    expect($content)->not->toContain('Run Architecture Scan')
        ->and($content)->not->toContain('Run Core Audit Commands');
});

it('enforces artifact upload includes manifest, report, junit, and arch-scan with error on missing', function (): void {
    $workflowPath = dirname(__DIR__, 2).'/.github/workflows/ci.yml';
    $content = (string) file_get_contents($workflowPath);

    expect($content)->toContain('storage/logs/gate-manifest.json')
        ->and($content)->toContain('storage/logs/pest-junit.xml')
        ->and($content)->toContain('storage/logs/arch-scan.json')
        ->and($content)->toContain('docs/gates/fase-r0.md')
        ->and($content)->toContain('if-no-files-found: error');
});

it('enforces composer gate full execution without skip-build flag', function (): void {
    $workflowPath = dirname(__DIR__, 2).'/.github/workflows/ci.yml';
    $content = (string) file_get_contents($workflowPath);

    expect($content)->toContain('composer gate')
        ->and($content)->not->toContain('--skip-build');
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

it('enforces mutation testing uses base_ref merge base and forbids covered-only', function (): void {
    $workflowPath = dirname(__DIR__, 2).'/.github/workflows/ci.yml';
    $content = (string) file_get_contents($workflowPath);

    expect($content)->toContain('test:mutate --git-diff')
        ->and($content)->toContain('--base="${{ github.base_ref || \'master\' }}"')
        ->and($content)->not->toContain('--covered-only');
});
