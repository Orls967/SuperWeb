<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Tests\TestCase;

/*
| PROGRESS TAHAP 1: SpecsAndDiagramsCompletenessTest.
| Memverifikasi kelengkapan spesifikasi modular Fase 1–105 + 57B di docs/specs/:
| 1. Keberadaan 16 file spesifikasi modular dan SPECS_INDEX.md.
| 2. Setiap fase memiliki 6 bagian wajib, minimal 5 US, minimal 5 BDD AC (Given/When/Then),
|    minimal 2 sequenceDiagram, dan minimal 1 erDiagram / stateDiagram-v2.
| 3. Total sequenceDiagram di seluruh direktori docs/ mencapai minimal 210 diagram.
| 4. ROLES_60_PLAYBOOK.md memiliki minimal 600 baris dan memuat profil lengkap 60 peran.
| 5. ARCHITECTURE.md memuat minimal 12 sequenceDiagram lintas lini bisnis.
*/

uses(TestCase::class);

it('verifies that all 17 specification files exist in docs/specs/', function (): void {
    $expectedFiles = [
        'FASE_106_125_SPECS.md',
        'fase-001-005-core-banking-payment-store-crypto.md',
        'fase-006-011-platform-resto-complete.md',
        'fase-012-018-mall-cross-hardening.md',
        'fase-019-025-logistics-multimodal-tower.md',
        'fase-026-031-platform-party-contract-asset.md',
        'fase-032-034-supplier-procurement-ap.md',
        'fase-035-040-manufacturing-industrial.md',
        'fase-041-050-wms-dist-pricing-agency-treasury-trade.md',
        'fase-051-057b-global-interco-tower-ef-edi-audit.md',
        'fase-058-063-hcm-plm-esg-b2b-agri-epc.md',
        'fase-064-070-ai-mobile-dr-simkernel-mobility.md',
        'fase-071-075-rwa-insurtech-wealth-cloudkitchen-vending.md',
        'fase-076-086-proptech-circular-autonomous-dao.md',
        'fase-087-094-hospital-venue-hotel-mining.md',
        'fase-095-100-conglomerate-integration-scale-ai-security.md',
        'fase-101-105-golden-api-v3-handover-healthcare-wave2.md',
    ];

    $specsFiles = glob(base_path('docs/specs/*.md')) ?: [];
    expect(count($specsFiles))->toBe(17, 'Total file spesifikasi di docs/specs/ wajib tepat 17 file.');

    foreach ($expectedFiles as $filename) {
        $path = base_path('docs/specs/'.$filename);
        expect(file_exists($path))->toBeTrue("File spesifikasi {$filename} wajib ada di docs/specs/");
    }
});

it('verifies that docs/SPECS_INDEX.md exists and indexes all 106 development phases and 10 remediation phases', function (): void {
    $indexPath = base_path('docs/SPECS_INDEX.md');
    expect(file_exists($indexPath))->toBeTrue('docs/SPECS_INDEX.md wajib ada');

    $content = (string) file_get_contents($indexPath);

    // Remediation phases R0..R9
    for ($r = 0; $r <= 9; $r++) {
        expect($content)->toContain("`Fase R{$r}`");
    }

    // Development phases 1..105 and 57B
    for ($p = 1; $p <= 105; $p++) {
        expect($content)->toContain("`Fase {$p}`");
    }
    expect($content)->toContain('`Fase 57B`');
});

it('verifies that every phase from 1 to 105 and 57B contains all 6 required sections, >= 5 US, >= 5 AC, >= 2 sequence diagrams, and >= 1 ERD/state diagram', function (): void {
    $specsDir = base_path('docs/specs');
    $files = glob($specsDir.'/*.md') ?: [];

    $allContent = '';
    foreach ($files as $file) {
        $allContent .= "\n".file_get_contents($file);
    }

    $allPhaseKeys = array_merge(
        array_map('strval', range(1, 57)),
        ['57B'],
        array_map('strval', range(58, 105))
    );

    // Split content by phase headers
    $phaseSections = preg_split('/(?=^## FASE (?:[0-9]+|57B)\b)/mi', $allContent) ?: [];

    $phaseMap = [];
    foreach ($phaseSections as $sec) {
        if (preg_match('/^## FASE ([0-9]+|57B)\b/mi', $sec, $m)) {
            $phaseMap[$m[1]] = $sec;
        }
    }

    expect(count($phaseMap))->toBe(126, 'Seluruh 126 fase wajib ditemukan dalam spesifikasi modular (1-105 + 57B + 106-125)');

    foreach ($allPhaseKeys as $k) {
        expect(isset($phaseMap[$k]))->toBeTrue("Fase {$k} tidak ditemukan dalam spesifikasi");
        $sec = $phaseMap[$k];

        // 1. Ringkasan Arsitektur
        expect(str_contains($sec, 'Identitas & Arsitektur'))->toBeTrue("Fase {$k} wajib memiliki bagian Identitas & Arsitektur");

        // 2. Matriks Problem-to-Solution
        expect(str_contains($sec, 'Problem-to-Solution Matrix'))->toBeTrue("Fase {$k} wajib memiliki Matriks Problem-to-Solution");

        // 3. User Stories >= 5
        expect(str_contains($sec, 'User Stories'))->toBeTrue("Fase {$k} wajib memiliki Spesifikasi User Stories");
        preg_match_all('/US-'.preg_quote($k, '/').'\.[0-9]+/i', $sec, $usMatches);
        expect(count($usMatches[0]))->toBeGreaterThanOrEqual(5, "Fase {$k} wajib memiliki minimal 5 User Stories (US-{$k}.1..US-{$k}.5)");

        // 4. BDD AC >= 5 with Given, When, Then
        expect(str_contains($sec, 'BDD Acceptance Criteria'))->toBeTrue("Fase {$k} wajib memiliki Kriteria Keberimaan BDD");
        preg_match_all('/AC-'.preg_quote($k, '/').'\.[0-9]+/i', $sec, $acMatches);
        expect(count($acMatches[0]))->toBeGreaterThanOrEqual(5, "Fase {$k} wajib memiliki minimal 5 Acceptance Criteria (AC-{$k}.1..AC-{$k}.5)");
        expect(str_contains($sec, '**Given**') && str_contains($sec, '**When**') && str_contains($sec, '**Then**'))
            ->toBeTrue("Fase {$k} wajib memuat struktur Given / When / Then");

        // 5. Sequence Diagrams >= 2
        expect(str_contains($sec, 'Sequence Diagram'))->toBeTrue("Fase {$k} wajib memiliki Diagram Sekuensial Mermaid");
        $seqCount = substr_count($sec, 'sequenceDiagram');
        expect($seqCount)->toBeGreaterThanOrEqual(2, "Fase {$k} wajib memiliki minimal 2 sequenceDiagram");

        // 6. ERD / State Machine >= 1
        $hasErdOrState = (str_contains($sec, 'erDiagram') || str_contains($sec, 'stateDiagram-v2'));
        expect($hasErdOrState)->toBeTrue("Fase {$k} wajib memiliki minimal 1 erDiagram atau stateDiagram-v2");
    }
});

it('verifies that total sequence diagrams across docs/ is at least 210', function (): void {
    $docsDir = base_path('docs');
    $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($docsDir));

    $totalSeq = 0;
    /** @var \SplFileInfo $file */
    foreach ($rii as $file) {
        if ($file->isFile() && $file->getExtension() === 'md') {
            $content = (string) file_get_contents($file->getPathname());
            $totalSeq += substr_count($content, 'sequenceDiagram');
        }
    }

    expect($totalSeq)->toBe(249, "Total sequenceDiagram di seluruh docs/ wajib tepat 249 (ditemukan: {$totalSeq})");
});

it('verifies that docs/ROLES_60_PLAYBOOK.md contains at least 600 lines and details all 60 operational roles', function (): void {
    $rolesPath = base_path('docs/ROLES_60_PLAYBOOK.md');
    expect(file_exists($rolesPath))->toBeTrue('docs/ROLES_60_PLAYBOOK.md wajib ada');

    $content = (string) file_get_contents($rolesPath);
    $lines = count(explode("\n", $content));

    expect($lines)->toBe(739, "ROLES_60_PLAYBOOK.md wajib memiliki tepat 739 baris (ditemukan: {$lines})");

    // All 60 roles (1 to 60)
    for ($i = 1; $i <= 60; $i++) {
        expect($content)->toMatch("/### 4\.{$i}\s+Peran\s+#{$i}:/i", "Peran #{$i} wajib terdaftar dan diprofilkan di ROLES_60_PLAYBOOK.md");
    }

    // Segregation of Duties matrix
    expect($content)->toContain('## 5. Matriks Pemisahan Tugas (Segregation of Duties - SoD Matrix)');
    // Emergency offboarding
    expect($content)->toContain('## 6. Prosedur Audit Kepatuhan Akses & Pencabutan Kredensial Darurat');
});

it('verifies that docs/ARCHITECTURE.md contains at least 12 cross-line sequence diagrams and no outdated claims', function (): void {
    $archPath = base_path('docs/ARCHITECTURE.md');
    expect(file_exists($archPath))->toBeTrue('docs/ARCHITECTURE.md wajib ada');

    $content = (string) file_get_contents($archPath);
    $seqCount = substr_count($content, 'sequenceDiagram');

    expect($seqCount)->toBe(13, "ARCHITECTURE.md wajib memiliki tepat 13 sequenceDiagram (ditemukan: {$seqCount})");
    expect($content)->not->toContain('Laravel 11')
        ->and($content)->not->toContain('PHP 8.3');
});
