<?php

declare(strict_types=1);

use App\Quality\ArchScan\Rules\TestNowOutsideTestsRule;

it('reports moving the global test clock from production code', function (): void {
    $code = <<<'PHP'
        <?php
        Carbon::setTestNow($virtualNow);
        \Illuminate\Support\Facades\Date::setTestNow(null);
        CarbonImmutable::setTestNowAndTimezone($virtualNow);
        PHP;

    $signatures = ruleSignatures(new TestNowOutsideTestsRule, 'modules/Core/Application/Services/SimClockService.php', $code);

    expect($signatures)->toBe(['Carbon::setTestNow', 'Date::setTestNow', 'CarbonImmutable::setTestNowAndTimezone']);
});

it('does not report test code or other clock calls', function (): void {
    $inTest = "<?php\nCarbon::setTestNow('2026-01-01');";
    $production = "<?php\n\$now = Carbon::now(); \$clock->setTestNow();";

    expect(ruleSignatures(new TestNowOutsideTestsRule, 'modules/Core/tests/Feature/SimClockTest.php', $inTest))->toBe([])
        ->and(ruleSignatures(new TestNowOutsideTestsRule, 'modules/Core/Application/Services/X.php', $production))->toBe([]);
});
