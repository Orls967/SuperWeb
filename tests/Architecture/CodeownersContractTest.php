<?php

declare(strict_types=1);

/*
| PROGRESS R0.7: CodeownersContractTest (DoR R0 #2).
| Memvalidasi keberadaan dan isi .github/CODEOWNERS dengan owner @Orls967
| untuk seluruh file baseline, konfigurasi gate, dokumentasi, dan tooling kualitas.
*/

it('enforces .github/CODEOWNERS exists and protects all mandatory sensitive paths with @Orls967', function (): void {
    $codeownersPath = dirname(__DIR__, 2).'/.github/CODEOWNERS';

    expect(file_exists($codeownersPath))->toBeTrue('.github/CODEOWNERS wajib ada.');

    $content = (string) file_get_contents($codeownersPath);

    $mandatoryPaths = [
        'tests/Architecture/baselines/',
        'tests/Architecture/route-roles.php',
        '.github/',
        'phpunit.xml',
        'composer.json',
        'composer.lock',
        'docs/PROGRESS.md',
        'docs/DECISIONS.md',
        'docs/gates/',
        'app/Quality/',
    ];

    foreach ($mandatoryPaths as $path) {
        $pattern = '/^\/?'.preg_quote($path, '/').'\s+@Orls967/m';
        expect((bool) preg_match($pattern, $content))
            ->toBeTrue("Path '{$path}' wajib terdaftar di .github/CODEOWNERS dengan owner @Orls967.");
    }
});
