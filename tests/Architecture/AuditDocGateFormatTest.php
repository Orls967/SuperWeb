<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Tests\TestCase;

/*
| PROGRESS R5.4: AuditDocGateFormatTest.
| Memverifikasi format gate di docs/AUDIT.md:
| 1. Entri gate historis diberi catatan "tidak representatif (hanya menguji sub-suite modul sebelum pagar kualitas penuh Fase R0)".
| 2. Entri gate pasca-R0 hanya menautkan laporan gate resmi di docs/gates/fase-r*.md.
| 3. Seluruh file laporan gate yang ditautkan benar-benar eksis secara fisik.
*/

uses(TestCase::class);

it('verifies that docs/AUDIT.md exists and contains the unrepresentative note for pre-R0 entries', function (): void {
    $auditDocPath = base_path('docs/AUDIT.md');
    expect(file_exists($auditDocPath))->toBeTrue();

    $content = (string) file_get_contents($auditDocPath);

    expect($content)->toContain('tidak representatif (hanya menguji sub-suite modul sebelum pagar kualitas penuh Fase R0)');
});

it('verifies that all official gate reports referenced in docs/AUDIT.md physically exist', function (): void {
    $auditDocPath = base_path('docs/AUDIT.md');
    $content = (string) file_get_contents($auditDocPath);

    preg_match_all('/(?:docs\/gates\/|\[docs\/gates\/)(fase-r[a-z0-9_-]+\.md)/i', $content, $matches);

    $linkedFiles = array_unique($matches[1] ?? []);
    expect($linkedFiles)->not->toBeEmpty();

    foreach ($linkedFiles as $file) {
        $fullPath = base_path('docs/gates/'.$file);
        expect(file_exists($fullPath))->toBeTrue("File gate {$file} yang ditautkan di AUDIT.md harus ada secara fisik");
    }
});

it('ensures all completed R phase gates are referenced in docs/AUDIT.md', function (): void {
    $auditDocPath = base_path('docs/AUDIT.md');
    $content = (string) file_get_contents($auditDocPath);

    $requiredGates = [
        'docs/gates/fase-r0.md',
        'docs/gates/fase-r1.md',
        'docs/gates/fase-r2.md',
        'docs/gates/fase-r3.md',
    ];

    foreach ($requiredGates as $gate) {
        expect($content)->toContain($gate);
    }
});
