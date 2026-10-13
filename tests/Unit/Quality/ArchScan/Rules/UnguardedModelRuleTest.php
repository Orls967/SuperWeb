<?php

declare(strict_types=1);

use App\Quality\ArchScan\Rules\UnguardedModelRule;

it('reports models without mass-assignment protection', function (): void {
    $short = "<?php\nfinal class A extends Model { protected \$guarded = []; }";
    $long = "<?php\nfinal class B extends Model { protected \$guarded = array(); }";

    expect(ruleSignatures(new UnguardedModelRule, 'modules/Hotel/Domain/Models/A.php', $short))->toBe(['$guarded = []'])
        ->and(ruleSignatures(new UnguardedModelRule, 'modules/Hotel/Domain/Models/B.php', $long))->toBe(['$guarded = []']);
});

it('does not report guarded columns or fillable models', function (): void {
    $code = "<?php\nfinal class A extends Model { protected \$guarded = ['id']; protected \$fillable = []; }";

    expect(ruleSignatures(new UnguardedModelRule, 'modules/Hotel/Domain/Models/A.php', $code))->toBe([]);
});
