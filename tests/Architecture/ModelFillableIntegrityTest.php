<?php

declare(strict_types=1);

use App\Quality\ArchScan\Rules\UnguardedModelRule;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\SourceFinder;
use Tests\TestCase;

/*
| PROGRESS R4.6 (K-25): Model fillable integrity and mass assignment protection.
| Enforces that no Eloquent model in modules/ uses $guarded = [] (A10 = 0),
| and ensures baseline arch-scan.json has ratcheted A10 down to 0.
*/

uses(TestCase::class);

it('enforces that no module model uses unguarded array', function (): void {
    $root = dirname(__DIR__, 2);
    $rule = new UnguardedModelRule;
    $registry = ModuleRegistry::fromConfig(config('modules'));

    $files = SourceFinder::phpFiles($root, ['modules'], [
        '#/database/migrations/#',
        '#/tests/#',
    ]);

    $violations = [];
    foreach ($files as $file) {
        $found = $rule->check($file, $registry);
        if ($found !== []) {
            $violations = array_merge($violations, $found);
        }
    }

    expect($violations)->toBeEmpty('Tidak boleh ada model dengan $guarded = [] di modules/ (K-25, A10 = 0).');
});

it('verifies arch-scan baseline locks A10 at zero', function (): void {
    $root = dirname(__DIR__, 2);
    $baselinePath = $root.'/tests/Architecture/baselines/arch-scan.json';

    $data = json_decode((string) file_get_contents($baselinePath), true, 512, JSON_THROW_ON_ERROR);
    $a10Total = $data['rules']['A10']['total'] ?? null;

    expect($a10Total)->toBe(0, 'Baseline A10 di arch-scan.json wajib 0 (R4.6).');
});
