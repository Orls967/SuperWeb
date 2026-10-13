<?php

declare(strict_types=1);

use App\Quality\TestHygiene\SkipWithoutBlockerRule;

it('reports skipped, incomplete and todo tests without a blocker reference', function (): void {
    $code = <<<'PHP'
        <?php
        it('a', function () {})->skip();
        it('b', function () {})->skip('belum sempat');
        it('c', function () {})->todo();
        final class XTest extends TestCase { public function test_x(): void { $this->markTestSkipped('nanti'); $this->markTestIncomplete(); } }
        PHP;

    expect(ruleSignatures(new SkipWithoutBlockerRule, 'tests/Feature/XTest.php', $code))
        ->toBe(['skip()', 'skip()', 'todo()', 'markTestSkipped()', 'markTestIncomplete()']);
});

it('accepts skips that cite BLOCKERS and ignores collection offsets', function (): void {
    $code = <<<'PHP'
        <?php
        it('a', function () {})->skip('Menunggu sandbox bank, lihat docs/BLOCKERS.md#B-3');
        $this->markTestSkipped("BLOCKERS B-4: driver MySQL belum ada");
        $rest = $items->skip(2)->values();
        $page = collect($rows)->skip($offset);
        PHP;

    expect(ruleSignatures(new SkipWithoutBlockerRule, 'tests/Feature/XTest.php', $code))->toBe([]);
});
