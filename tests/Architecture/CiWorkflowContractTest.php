<?php

declare(strict_types=1);

/*
| PROGRESS R0.3: CiWorkflowContractTest (K-04, K-28).
| Memvalidasi konfigurasi CI GitHub Actions: PHP 8.4 × {SQLite, MySQL 8},
| pemanggilan composer gate, arch:scan, dan @group db-portability.
*/

it('verifies CI workflow configuration exists and satisfies R0.3 specifications', function (): void {
    $root = dirname(__DIR__, 2);
    $workflowPath = $root.'/.github/workflows/ci.yml';

    expect(is_file($workflowPath))->toBeTrue('.github/workflows/ci.yml wajib ada untuk CI GitHub Actions.');

    $content = (string) file_get_contents($workflowPath);

    expect($content)->toContain("php-version: '8.4'")
        ->and($content)->toContain('mysql:8')
        ->and($content)->toContain('composer gate')
        ->and($content)->toContain('gate:report')
        ->and($content)->toContain('--group=db-portability');
});
