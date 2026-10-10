<?php

declare(strict_types=1);

use App\Quality\ArchScan\ScanReport;
use App\Quality\ArchScan\Violation;
use App\Quality\Baseline\BaselineIncreaseRefused;
use App\Quality\Baseline\FingerprintBaseline;

/**
 * @param  list<array{string, int, string}>  $violations  [path, line, signature]
 */
function a13Report(array $violations): ScanReport
{
    return new ScanReport(
        ['A13' => 'back()->errors()'],
        ['A13' => array_map(fn (array $v): Violation => new Violation('A13', $v[0], $v[1], $v[2], 'msg'), $violations)],
    );
}

it('is clean when the scan matches the baseline even if lines moved', function (): void {
    $baseline = FingerprintBaseline::fromReport(a13Report([['modules/A/X.php', 10, 'back()->errors()']]));

    $comparison = $baseline->compare(a13Report([['modules/A/X.php', 42, 'back()->errors()']]));

    expect($comparison->isClean())->toBeTrue();
});

it('fails a fix in one file combined with a new violation in another', function (): void {
    $baseline = FingerprintBaseline::fromReport(a13Report([['modules/A/X.php', 10, 'back()->errors()']]));

    $comparison = $baseline->compare(a13Report([['modules/B/Y.php', 7, 'back()->errors()']]));

    expect($comparison->newCount('A13'))->toBe(1)
        ->and($comparison->staleCount('A13'))->toBe(1)
        ->and($comparison->new['A13'][0])->toBe(['path' => 'modules/B/Y.php', 'signature' => 'back()->errors()', 'delta' => 1, 'lines' => [7]]);
});

it('counts repeated violations in the same file', function (): void {
    $baseline = FingerprintBaseline::fromReport(a13Report([['modules/A/X.php', 10, 'back()->errors()']]));

    $comparison = $baseline->compare(a13Report([
        ['modules/A/X.php', 10, 'back()->errors()'],
        ['modules/A/X.php', 20, 'back()->errors()'],
    ]));

    expect($comparison->newCount('A13'))->toBe(1);
});

it('marks fixed violations as stale so the baseline has to be lowered', function (): void {
    $baseline = FingerprintBaseline::fromReport(a13Report([['modules/A/X.php', 10, 'back()->errors()']]));

    $comparison = $baseline->compare(a13Report([]));

    expect($comparison->hasNew())->toBeFalse()
        ->and($comparison->staleCount('A13'))->toBe(1)
        ->and($comparison->isClean())->toBeFalse();
});

it('refuses to add violations to the baseline without a decision reference', function (): void {
    $baseline = FingerprintBaseline::fromReport(a13Report([]));

    $baseline->updatedFrom(a13Report([['modules/B/Y.php', 7, 'back()->errors()']]), null, '2026-10-10');
})->throws(BaselineIncreaseRefused::class);

it('records the decision reference for every approved addition and lowers fixed entries', function (): void {
    $baseline = FingerprintBaseline::fromReport(a13Report([['modules/A/X.php', 10, 'back()->errors()']]));

    $updated = $baseline->updatedFrom(a13Report([['modules/B/Y.php', 7, 'back()->errors()']]), 'DECISIONS.md#2026-10-10-x', '2026-10-10');
    $json = json_decode($updated->toJson('about'), true);

    expect($json['rules']['A13'])->toBe(['total' => 1, 'entries' => ['modules/B/Y.php' => ['back()->errors()' => 1]]])
        ->and($json['approved_additions'])->toBe([[
            'date' => '2026-10-10',
            'decision' => 'DECISIONS.md#2026-10-10-x',
            'rule' => 'A13',
            'path' => 'modules/B/Y.php',
            'signature' => 'back()->errors()',
            'added' => 1,
        ]]);
});

it('records a single summary entry when the first baseline is created', function (): void {
    $empty = new FingerprintBaseline([]);

    $initial = $empty->updatedFrom(a13Report([
        ['modules/A/X.php', 10, 'back()->errors()'],
        ['modules/B/Y.php', 7, 'back()->errors()'],
    ]), 'DECISIONS.md#baseline-awal', '2026-10-10');

    expect(json_decode($initial->toJson('about'), true)['approved_additions'])->toBe([[
        'date' => '2026-10-10',
        'decision' => 'DECISIONS.md#baseline-awal',
        'rule' => '*',
        'path' => '*',
        'signature' => 'baseline awal',
        'added' => 2,
    ]]);
});

it('round-trips through the JSON file format', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'baseline');
    $original = FingerprintBaseline::fromReport(a13Report([['modules/A/X.php', 10, 'back()->errors()']]));
    file_put_contents($path, $original->toJson('about'));

    $loaded = FingerprintBaseline::fromFile($path);

    expect($loaded->total('A13'))->toBe(1)
        ->and($loaded->compare(a13Report([['modules/A/X.php', 3, 'back()->errors()']]))->isClean())->toBeTrue();

    unlink($path);
});
