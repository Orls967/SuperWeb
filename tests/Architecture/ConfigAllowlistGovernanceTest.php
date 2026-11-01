<?php

declare(strict_types=1);

use Tests\TestCase;

/*
| Kontrak Arsitektur: ConfigAllowlistGovernanceTest (K-2, KONSEP §A14.1).
| Memastikan konfigurasi allowlist di config/modules.php tidak bertambah
| melampaui baseline asli 37aebb76 kecuali tercatat di approved_additions
| dengan anchor DECISIONS.md yang sah.
*/

uses(TestCase::class);

function validateConfigAllowlists(array $config, array $baselineData, string $decisionsContent): array
{
    $violations = [];
    $baselines = $baselineData['baselines'] ?? [];
    $approvedAdditions = $baselineData['approved_additions'] ?? [];

    // 1. Validasi setiap approved_additions memiliki anchor sah di DECISIONS.md
    $approvedCountsByKey = [];
    foreach ($approvedAdditions as $addition) {
        $key = $addition['key'] ?? '';
        $decision = $addition['decision'] ?? '';

        if (! preg_match('/DECISIONS\.md#([a-zA-Z0-9_\-]+)/u', $decision, $dm)) {
            $violations[] = "approved_additions untuk '{$key}' tidak menyertakan anchor DECISIONS.md#...: '{$decision}'";

            continue;
        }

        $anchor = $dm[1];
        if (! str_contains($decisionsContent, $anchor)) {
            $violations[] = "approved_additions untuk '{$key}' merujuk anchor '{$anchor}' yang tidak ditemukan di docs/DECISIONS.md.";

            continue;
        }

        $count = 0;
        if (isset($addition['count'])) {
            $count = (int) $addition['count'];
        } elseif (isset($addition['entries']) && is_array($addition['entries'])) {
            $count = count($addition['entries']);
        } elseif (isset($addition['entry'])) {
            $count = 1;
        }

        $approvedCountsByKey[$key] = ($approvedCountsByKey[$key] ?? 0) + $count;
    }

    // 2. Periksa setiap kunci baseline terhadap config aktual
    foreach ($baselines as $key => $baseLimit) {
        $actualEntries = $config[$key] ?? [];
        $actualCount = is_array($actualEntries) ? count($actualEntries) : 0;
        $approvedCount = $approvedCountsByKey[$key] ?? 0;
        $allowedLimit = $baseLimit + $approvedCount;

        if ($actualCount > $allowedLimit) {
            $violations[] = "Kunci config/modules.php '{$key}' memiliki {$actualCount} entri, melebihi batas baseline {$baseLimit} + approved {$approvedCount} = {$allowedLimit} (selisih +".($actualCount - $allowedLimit).' pelanggaran tanpa anchor DECISIONS.md).';
        }
    }

    return $violations;
}

it('passes sabotage test: rejects unauthorized additions and accepts validly anchored additions', function (): void {
    $baselineData = [
        'baselines' => [
            'table_prefixes' => 58,
            'legacy_tables' => 20,
            'kernel_modules' => 1,
            'shared_kernel_namespaces' => 3,
            'cross_module_columns' => 0,
            'shared_tables' => 0,
        ],
        'approved_additions' => [],
    ];

    $decisions = "# DECISIONS\n\n## 2026-10-30-izin-resmi\n";

    // Clean config matching baseline
    $cleanConfig = [
        'table_prefixes' => array_fill(0, 58, 'pfx_'),
        'legacy_tables' => array_fill(0, 20, 'legacy_tbl'),
        'kernel_modules' => ['Core'],
        'shared_kernel_namespaces' => ['App\\A', 'App\\B', 'App\\C'],
        'cross_module_columns' => [],
        'shared_tables' => [],
    ];

    expect(validateConfigAllowlists($cleanConfig, $baselineData, $decisions))->toBe([]);

    // Sabotage 1: Add unauthorized cross_module_columns
    $sabotagedConfig = $cleanConfig;
    $sabotagedConfig['cross_module_columns'] = ['orders.user_id'];
    $violations1 = validateConfigAllowlists($sabotagedConfig, $baselineData, $decisions);
    expect($violations1)->not->toBeEmpty();
    expect($violations1[0])->toContain("Kunci config/modules.php 'cross_module_columns' memiliki 1 entri, melebihi batas");

    // Sabotage 2: Add approved addition with invalid / missing anchor in DECISIONS.md
    $invalidAnchorData = $baselineData;
    $invalidAnchorData['approved_additions'] = [
        [
            'key' => 'cross_module_columns',
            'decision' => 'DECISIONS.md#anchor-palsu-tidak-ada',
            'count' => 1,
        ],
    ];
    $violations2 = validateConfigAllowlists($sabotagedConfig, $invalidAnchorData, $decisions);
    expect($violations2)->not->toBeEmpty();
    expect($violations2[0])->toContain("merujuk anchor 'anchor-palsu-tidak-ada' yang tidak ditemukan di docs/DECISIONS.md");

    // Valid recovery: Approved addition with valid anchor
    $validAnchorData = $baselineData;
    $validAnchorData['approved_additions'] = [
        [
            'key' => 'cross_module_columns',
            'decision' => 'DECISIONS.md#2026-10-30-izin-resmi',
            'count' => 1,
        ],
    ];
    $violations3 = validateConfigAllowlists($sabotagedConfig, $validAnchorData, $decisions);
    expect($violations3)->toBeEmpty();
});

it('enforces that config/modules.php does not exceed baseline allowlists without approved additions', function (): void {
    $root = base_path();
    $config = require $root.'/config/modules.php';
    $baselinePath = $root.'/tests/Architecture/baselines/config-allowlists.json';
    $baselineData = json_decode((string) file_get_contents($baselinePath), true, 512, JSON_THROW_ON_ERROR);
    $decisionsContent = (string) file_get_contents($root.'/docs/DECISIONS.md');

    $violations = validateConfigAllowlists($config, $baselineData, $decisionsContent);

    expect($violations)->toBe([], "Ditemukan penambahan allowlist tanpa anchor DECISIONS.md di config/modules.php:\n".implode("\n", $violations));
});

it('validates syntax integrity of all cross_module_columns and audit documentation', function (): void {
    $root = base_path();
    $config = require $root.'/config/modules.php';
    $crossColumns = $config['cross_module_columns'] ?? [];

    expect($crossColumns)->toBeArray();
    expect(count($crossColumns))->toBeLessThanOrEqual(172);

    foreach ($crossColumns as $key => $description) {
        expect($key)->toMatch('/^[a-z0-9_]+\.[a-z0-9_]+$/', "Format cross_module_columns '{$key}' harus table.column");
        expect($description)->toBeString()->not->toBeEmpty();
    }

    $auditDocPath = $root.'/docs/audit/G5-A-allowlist-governance.md';
    expect(file_exists($auditDocPath))->toBeTrue('Dokumen audit tata kelola docs/audit/G5-A-allowlist-governance.md wajib ada');
});
