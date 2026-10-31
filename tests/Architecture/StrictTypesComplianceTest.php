<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Quality\ArchScan\Rules\MissingStrictTypesRule;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\SourceFinder;

it('enforces declare(strict_types=1) across all module PHP files excluding migrations and tests', function (): void {
    $root = dirname(__DIR__, 2);
    $config = require $root.'/config/modules.php';
    $registry = new ModuleRegistry($config);
    $rule = new MissingStrictTypesRule;

    $files = SourceFinder::phpFiles($root, ['modules'], ['#(?:^|/)modules/[^/]+/tests/#']);

    $violations = [];

    foreach ($files as $file) {
        $fileViolations = $rule->check($file, $registry);

        foreach ($fileViolations as $violation) {
            $violations[] = $violation->path.':'.$violation->line;
        }

        $file->releaseTokens();
    }

    expect($violations)->toBe([], 'Ditemukan file modul tanpa declare(strict_types=1): '.implode(', ', $violations));
});
