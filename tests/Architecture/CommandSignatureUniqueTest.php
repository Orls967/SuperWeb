<?php

declare(strict_types=1);

use App\Quality\Commands\CommandNameCollector;
use App\Quality\Support\SourceFinder;

/*
| PROGRESS R0.6: every Artisan command name is declared exactly once. The container
| keeps only the last registration, so a duplicate silently disables a command.
*/

it('declares every artisan command name exactly once', function (): void {
    $root = dirname(__DIR__, 2);
    $files = SourceFinder::phpFiles($root, ['app', 'modules', 'routes'], ['#(?:^|/)modules/[^/]+/tests/#']);

    $commands = CommandNameCollector::collect($files);

    expect(array_column($commands, 'name'))->toContain('bank:reconcile', 'api:audit', 'arch:scan', 'inspire')
        ->and(CommandNameCollector::duplicates($commands))->toBe([]);
});
