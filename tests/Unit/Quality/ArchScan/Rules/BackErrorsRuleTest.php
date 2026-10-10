<?php

declare(strict_types=1);

use App\Quality\ArchScan\Rules\BackErrorsRule;

it('reports back()->errors() on the error path', function (): void {
    $code = "<?php\nreturn back()->errors()->add('price', 'Harga tidak valid');";

    expect(ruleSignatures(new BackErrorsRule, 'modules/Pricing/Http/Controllers/PricingController.php', $code))->toBe(['back()->errors()']);
});

it('does not report withErrors', function (): void {
    $code = "<?php\nreturn back()->withErrors(['price' => 'Harga tidak valid'])->withInput();";

    expect(ruleSignatures(new BackErrorsRule, 'modules/Pricing/Http/Controllers/PricingController.php', $code))->toBe([]);
});
