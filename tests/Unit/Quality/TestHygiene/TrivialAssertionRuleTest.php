<?php

declare(strict_types=1);

use App\Quality\TestHygiene\TrivialAssertionRule;

it('reports assertions that can never fail', function (): void {
    $code = <<<'PHP'
        <?php
        it('does nothing', function () { expect(true)->toBeTrue(); });
        final class XTest extends TestCase { public function test_x(): void { $this->assertTrue(true, 'selalu'); } }
        PHP;

    expect(ruleSignatures(new TrivialAssertionRule, 'tests/Feature/XTest.php', $code))->toBe(['expect(true)->toBeTrue()', 'assertTrue(true)']);
});

it('does not report assertions on real values or production code', function (): void {
    $test = "<?php\nexpect(\$order->paid)->toBeTrue();\n\$this->assertTrue(\$ledger->isBalanced());";
    $production = "<?php\nassertTrue(true);";

    expect(ruleSignatures(new TrivialAssertionRule, 'modules/Mall/tests/Feature/XTest.php', $test))->toBe([])
        ->and(ruleSignatures(new TrivialAssertionRule, 'modules/Mall/Application/X.php', $production))->toBe([]);
});
