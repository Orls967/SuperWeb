<?php

declare(strict_types=1);

use App\Quality\Docs\DecisionLog;

it('builds GitHub-style anchors from headings', function (string $heading, string $anchor): void {
    expect(DecisionLog::slug($heading))->toBe($anchor);
})->with([
    ['2026-10-10: PHP 8.4 & composer.lock di-commit (Fase R0.1)', '2026-10-10-php-84--composerlock-di-commit-fase-r01'],
    ['2026-09-29: Pest vs PHPUnit', '2026-09-29-pest-vs-phpunit'],
    ['Gelombang 2 (Fase 104–149) — 17 Lini', 'gelombang-2-fase-104149--17-lini'],
]);

it('resolves references to existing decisions only', function (): void {
    $log = new DecisionLog("# Decisions Log\n\n## 2026-10-10: Baseline awal\n- x\n\n```\n## Bukan heading\n```\n## 2026-10-10: Baseline awal\n");

    expect($log->has('DECISIONS.md#2026-10-10-baseline-awal'))->toBeTrue()
        ->and($log->has('docs/DECISIONS.md#2026-10-10-baseline-awal-1'))->toBeTrue()
        ->and($log->has('#bukan-heading'))->toBeFalse()
        ->and($log->has('DECISIONS.md#keputusan-karangan'))->toBeFalse()
        ->and($log->has('DECISIONS.md#'))->toBeFalse();
});
