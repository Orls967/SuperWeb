<?php

declare(strict_types=1);

use Tests\TestCase;

/*
| Kontrak Arsitektur: MemoryBudgetContractTest (K-6, KONSEP §A14).
| Memastikan konfigurasi memory_limit di phpunit.xml tidak melebihi
| batas anggaran 512M, kecuali ada keputusan resmi di docs/DECISIONS.md.
*/

uses(TestCase::class);

function parseMemoryLimitInMb(string $val): int
{
    $val = trim($val);
    if (preg_match('/^(\d+)\s*(m|mb|g|gb)?$/i', $val, $m)) {
        $num = (int) $m[1];
        $unit = strtolower($m[2] ?? 'm');
        if (str_starts_with($unit, 'g')) {
            return $num * 1024;
        }

        return $num;
    }

    return (int) $val;
}

function validateMemoryBudget(string $phpunitXmlContent, string $decisionsContent): array
{
    $violations = [];

    if (! preg_match('/<ini\s+name=["\']memory_limit["\']\s+value=["\']([^"\']+)["\']/i', $phpunitXmlContent, $matches)) {
        $violations[] = "Konfigurasi ini 'memory_limit' tidak ditemukan di phpunit.xml.";

        return $violations;
    }

    $rawValue = trim($matches[1]);
    $limitMb = parseMemoryLimitInMb($rawValue);

    if ($limitMb > 512) {
        $hasApprovedDecision = (bool) preg_match('/DECISIONS\.md#([a-zA-Z0-9_\-]+memory[a-zA-Z0-9_\-]*)/i', $decisionsContent);
        if (! $hasApprovedDecision) {
            $violations[] = "memory_limit di phpunit.xml bernilai {$rawValue} ({$limitMb}M), melebihi batas anggaran maksimal 512M tanpa anchor DECISIONS.md yang disetujui pemilik (K-6).";
        }
    }

    return $violations;
}

it('passes sabotage test: accepts <= 512M and rejects > 512M without approved decision', function (): void {
    $decisionsEmpty = "# DECISIONS\n";
    $xml512 = '<phpunit><php><ini name="memory_limit" value="512M"/></php></phpunit>';
    $xml256 = '<phpunit><php><ini name="memory_limit" value="256M"/></php></phpunit>';
    $xml1024 = '<phpunit><php><ini name="memory_limit" value="1024M"/></php></phpunit>';

    expect(validateMemoryBudget($xml512, $decisionsEmpty))->toBe([]);
    expect(validateMemoryBudget($xml256, $decisionsEmpty))->toBe([]);

    $violations = validateMemoryBudget($xml1024, $decisionsEmpty);
    expect($violations)->not->toBeEmpty();
    expect($violations[0])->toContain('melebihi batas anggaran maksimal 512M');
});

it('enforces that phpunit.xml memory_limit does not exceed 512M budget', function (): void {
    $root = base_path();
    $phpunitXmlPath = $root.'/phpunit.xml';
    expect(is_file($phpunitXmlPath))->toBeTrue();

    $phpunitXml = (string) file_get_contents($phpunitXmlPath);
    $decisions = (string) file_get_contents($root.'/docs/DECISIONS.md');

    $violations = validateMemoryBudget($phpunitXml, $decisions);

    expect($violations)->toBe([], "Pelanggaran anggaran memori phpunit.xml ditemukan:\n".implode("\n", $violations));
});
