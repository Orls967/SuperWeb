<?php

declare(strict_types=1);

use App\Quality\Progress\ProgressIntegrityScanner;
use Tests\TestCase;

/*
| PROGRESS R0.4: ProgressIntegrityTest (KONSEP §A14.2, PROGRESS.md §P2).
| Parses docs/PROGRESS.md and validates proof blocks, git commits, touched files,
| route authorization, gate reports, verified phase status, and item text ratchet.
*/

uses(TestCase::class);

it('enforces that docs/PROGRESS.md item texts have not been modified without downgrade marker', function (): void {
    $root = dirname(__DIR__, 2);
    $progressPath = $root.'/docs/PROGRESS.md';
    $baselinePath = $root.'/tests/Architecture/baselines/progress-item-texts.json';

    $content = (string) file_get_contents($progressPath);
    $baseData = json_decode((string) file_get_contents($baselinePath), true, 512, JSON_THROW_ON_ERROR);
    $baselineTexts = $baseData['items'] ?? [];

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($content);

    $violations = [];
    foreach ($phases as $phase) {
        foreach ($phase->items as $item) {
            $key = $item->uniqueKey !== '' ? $item->uniqueKey : "{$item->phaseId}:{$item->id}";
            if (isset($baselineTexts[$key])) {
                $baseText = $baselineTexts[$key];
                if ($item->text !== $baseText) {
                    $hasDowngradeMarker = str_contains($item->text, '⬇️ diturunkan') || str_contains($item->text, '⬇️');
                    if (! $hasDowngradeMarker) {
                        $violations[] = "Teks item {$item->id} ({$key}) diubah tanpa penanda '⬇️ diturunkan' (X18 / P10).";
                    }
                }
            }
        }
    }

    expect($violations)->toBe([], 'Teks item di docs/PROGRESS.md diubah tanpa penanda ⬇️ diturunkan.');
});

it('enforces proof block integrity and phase verification in docs/PROGRESS.md', function (): void {
    $root = dirname(__DIR__, 2);
    $progressPath = $root.'/docs/PROGRESS.md';
    $baselinePath = $root.'/tests/Architecture/baselines/progress-item-texts.json';

    $content = (string) file_get_contents($progressPath);
    $baseData = json_decode((string) file_get_contents($baselinePath), true, 512, JSON_THROW_ON_ERROR);
    $baselineTexts = $baseData['items'] ?? [];

    $scanner = new ProgressIntegrityScanner;
    $phases = $scanner->parse($content);
    $violations = $scanner->validate($phases, repoRoot: $root, textBaseline: $baselineTexts);

    expect($violations)->toBe([], 'Pelanggaran integritas docs/PROGRESS.md ditemukan (P2, KONSEP §A14.2).');
});
