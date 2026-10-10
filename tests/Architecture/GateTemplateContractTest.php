<?php

declare(strict_types=1);

/*
| PROGRESS R0.7: GateTemplateContractTest.
| Memvalidasi ketersediaan dan integritas template PR (.github/pull_request_template.md)
| serta panduan direktori laporan gate (docs/gates/README.md).
*/

it('verifies pull request template contains V1-V12 and C1-C14 checklists', function (): void {
    $root = dirname(__DIR__, 2);
    $prTemplatePath = $root.'/.github/pull_request_template.md';

    expect(is_file($prTemplatePath))->toBeTrue('.github/pull_request_template.md wajib ada.');

    $content = (string) file_get_contents($prTemplatePath);

    // Verify V1-V12
    for ($i = 1; $i <= 12; $i++) {
        expect($content)->toContain("**V{$i}**");
    }

    // Verify C1-C14
    for ($j = 1; $j <= 14; $j++) {
        expect($content)->toContain("**C{$j}**");
    }

    expect($content)->toContain('Checklist Pelaksana: Vertical Slice (V1–V12)')
        ->and($content)->toContain('Checklist Verifikator Independen: Verifikasi Silang (C1–C14)');
});

it('verifies docs/gates/README.md provides guidance on reading gate reports', function (): void {
    $root = dirname(__DIR__, 2);
    $readmePath = $root.'/docs/gates/README.md';

    expect(is_file($readmePath))->toBeTrue('docs/gates/README.md wajib ada.');

    $content = (string) file_get_contents($readmePath);

    expect($content)->toContain('php artisan gate:report --fase=N')
        ->and($content)->toContain('Struktur Laporan Quality Gate')
        ->and($content)->toContain('Verifikasi Silang (Wajib untuk Status ✅)');
});
