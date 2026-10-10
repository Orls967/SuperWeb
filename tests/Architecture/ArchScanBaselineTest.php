<?php

declare(strict_types=1);

use App\Console\Commands\ArchScanCommand;
use App\Quality\ArchScan\ArchScanner;
use App\Quality\Baseline\FingerprintBaseline;
use App\Quality\Docs\DecisionLog;

/*
| Gate for `arch:scan` (PROGRESS.md §P11, KONSEP.md §A14.1). Runs without booting
| Laravel so it stays fast; it reads config/modules.php directly.
*/

it('keeps module code within the arch:scan ratchet baseline', function (): void {
    $root = dirname(__DIR__, 2);
    $report = ArchScanner::scanProject($root, require $root.'/config/modules.php');

    $comparison = FingerprintBaseline::fromFile($root.'/'.ArchScanCommand::DEFAULT_BASELINE)->compare($report);

    expect($comparison->isClean())->toBeTrue(
        "arch:scan tidak cocok dengan baseline. Perbaiki pelanggaran baru, atau turunkan baseline bila pelanggaran hilang (php artisan arch:scan --update-baseline):\n".$comparison->describe()
    );
});

it('only accepts baseline additions that cite an existing decision', function (): void {
    $root = dirname(__DIR__, 2);
    $decisions = DecisionLog::fromFile($root.'/docs/DECISIONS.md');
    $baseline = FingerprintBaseline::fromFile($root.'/'.ArchScanCommand::DEFAULT_BASELINE);

    $unknownDecisions = array_values(array_unique(array_map(
        fn (array $addition): string => $addition['decision'],
        array_filter($baseline->approvedAdditions(), fn (array $addition): bool => ! $decisions->has($addition['decision'])),
    )));

    expect($unknownDecisions)->toBe([]);
});
