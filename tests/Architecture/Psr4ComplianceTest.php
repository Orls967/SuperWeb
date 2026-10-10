<?php

declare(strict_types=1);

use App\Quality\Autoload\Psr4ComplianceChecker;
use App\Quality\Support\SourceFinder;

/*
| PROGRESS R0.3.a (K-28): every production class lives at the path its namespace
| demands, compared case-sensitively, so it also loads on Linux servers and CI.
| Test files are excluded: Pest loads them by path, not through the autoloader.
*/

it('keeps production classes at their case-sensitive PSR-4 path', function (): void {
    $root = dirname(__DIR__, 2);
    $composer = json_decode((string) file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $psr4 = $composer['autoload']['psr-4'];
    $files = SourceFinder::phpFiles($root, array_values($psr4), ['#(?:^|/)modules/[^/]+/tests/#']);

    $mismatches = (new Psr4ComplianceChecker($psr4))->mismatches($files);

    expect(count($files))->toBeGreaterThan(1000)
        ->and($mismatches)->toBe([]);
});
