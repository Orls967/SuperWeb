<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Quality\ArchScan\Rules\KernelDependsOnBusinessModuleRule;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\SourceFinder;

it('enforces that kernel modules (Shared, Core) have zero dependencies on business modules (A9 = 0)', function (): void {
    $root = dirname(__DIR__, 2);
    $config = require $root.'/config/modules.php';
    $registry = ModuleRegistry::fromConfig($config);
    $rule = new KernelDependsOnBusinessModuleRule;

    $files = SourceFinder::phpFiles(
        $root,
        ['modules/Core', 'modules/Shared'],
        ['#(?:^|/)modules/[^/]+/tests/#']
    );

    $violations = [];

    foreach ($files as $file) {
        $fileViolations = $rule->check($file, $registry);

        foreach ($fileViolations as $violation) {
            $violations[] = $violation->path.':'.$violation->line.' -> '.$violation->signature;
        }
    }

    expect($violations)->toBe([], "Ditemukan dependensi terbalik kernel ke modul bisnis (A9):\n".implode("\n", $violations));
});
