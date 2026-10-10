<?php

declare(strict_types=1);

/*
| PROGRESS R0.5: DocsVersionConsistencyTest (K-02, K-03, DoR Fase R0).
| Memastikan dokumentasi bersih dari klaim versi usang dan akun palsu.
*/

it('ensures README.md and ARCHITECTURE.md do not reference outdated Laravel 11 or PHP 8.3 versions', function (): void {
    $root = dirname(__DIR__, 2);
    $readme = (string) file_get_contents($root.'/README.md');
    $arch = (string) file_get_contents($root.'/docs/ARCHITECTURE.md');

    expect($readme)->not->toContain('Laravel 11')
        ->and($arch)->not->toContain('Laravel 11')
        ->and($arch)->not->toContain('PHP 8.3');
});

it('ensures LAPORAN_AUDIT_GELOMBANG_2.md is explicitly marked as archived and invalid', function (): void {
    $root = dirname(__DIR__, 2);
    $laporan = (string) file_get_contents($root.'/docs/LAPORAN_AUDIT_GELOMBANG_2.md');

    expect($laporan)->toContain('[DIARSIPKAN]')
        ->and($laporan)->toContain('TIDAK VALID');
});

it('ensures CODEBASE.md does not reference removed non-existent accounts', function (): void {
    $root = dirname(__DIR__, 2);
    $codebase = (string) file_get_contents($root.'/docs/CODEBASE.md');

    expect($codebase)->not->toContain('agri:farmer_advance_receivable')
        ->and($codebase)->not->toContain('ast:cip_project:');
});

it('ensures composer.json and README.md align on PHP ^8.4 requirement', function (): void {
    $root = dirname(__DIR__, 2);
    $composer = json_decode((string) file_get_contents($root.'/composer.json'), true);
    $readme = (string) file_get_contents($root.'/README.md');

    expect($composer['require']['php'] ?? '')->toBe('^8.4')
        ->and($readme)->toContain('PHP ^8.4');
});
