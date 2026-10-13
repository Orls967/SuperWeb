<?php

use App\Quality\ArchScan\Rule;
use App\Quality\ArchScan\ScanReport;
use App\Quality\ArchScan\Violation;
use App\Quality\Baseline\FingerprintBaseline;
use App\Quality\Baseline\SetBaseline;
use App\Quality\Docs\DecisionLog;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\SourceFile;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->in('Feature', '../modules/*/tests/Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Quality tooling (arch:scan, baselines): build an in-memory source file.
 */
function sourceFile(string $relativePath, string $code): SourceFile
{
    return new SourceFile($relativePath, $code);
}

/**
 * Small, explicit module registry for quality-rule tests (independent of config/modules.php).
 */
function qualityTestRegistry(): ModuleRegistry
{
    return new ModuleRegistry(
        tablePrefixes: ['hsp_' => 'Hospital', 'htl_' => 'Hotel', 'bank_' => 'Banking', 'intg_' => 'Integration', 'core_' => 'Core'],
        legacyTables: ['users' => 'App', 'bookings' => 'AutoServe'],
        kernelModules: ['Shared', 'Core'],
        sharedKernelNamespaces: ['Modules\Banking\Domain\Kernel'],
        crossModuleColumns: ['hsp_admissions.insurer_party_id' => 'Party dibaca lewat Contract'],
    );
}

/**
 * Run one quality rule over one in-memory file and return the violation signatures.
 *
 * @return list<string>
 */
function ruleSignatures(Rule $rule, string $relativePath, string $code): array
{
    return array_map(
        static fn (Violation $violation): string => $violation->signature,
        $rule->check(sourceFile($relativePath, $code), qualityTestRegistry()),
    );
}

/**
 * Ratchet gate for a set baseline (PROGRESS.md §P11). Fails on entries that are not in
 * the baseline and on baseline entries that no longer exist. With UPDATE_BASELINES=1
 * the file is rewritten instead: shrinking is always allowed, growing only with
 * BASELINE_DECISION="DECISIONS.md#…" pointing at an existing decision.
 *
 * @param  list<string>  $current
 */
function assertSetBaseline(string $baselinePath, array $current, string $about): void
{
    $root = dirname(__DIR__);
    $baseline = SetBaseline::fromFile($root.'/'.$baselinePath);

    if (getenv('UPDATE_BASELINES') === '1') {
        $baseline = $baseline->updatedFrom($current, baselineDecisionFromEnvironment($root), date('Y-m-d'));
        file_put_contents($root.'/'.$baselinePath, $baseline->toJson($about));
    }

    $difference = $baseline->compare($current);
    $decisions = DecisionLog::fromFile($root.'/docs/DECISIONS.md');
    $unknownDecisions = array_values(array_filter(
        array_column($baseline->approvedAdditions(), 'decision'),
        fn (string $decision): bool => ! $decisions->has($decision),
    ));

    expect($difference['new'])->toBe([], "Entri baru di luar baseline {$baselinePath}. Perbaiki, atau (dengan keputusan pemilik) UPDATE_BASELINES=1 BASELINE_DECISION=\"DECISIONS.md#…\".")
        ->and($difference['stale'])->toBe([], "Entri baseline {$baselinePath} sudah hilang. Turunkan baseline: UPDATE_BASELINES=1 vendor/bin/pest --filter=<test ini>.")
        ->and($unknownDecisions)->toBe([], "approved_additions di {$baselinePath} merujuk keputusan yang tidak ada di docs/DECISIONS.md.");
}

/**
 * Same ratchet gate for rule/path/signature baselines (arch:scan style scans).
 */
function assertFingerprintBaseline(string $baselinePath, ScanReport $report, string $about): void
{
    $root = dirname(__DIR__);
    $baseline = FingerprintBaseline::fromFile($root.'/'.$baselinePath);

    if (getenv('UPDATE_BASELINES') === '1') {
        $baseline = $baseline->updatedFrom($report, baselineDecisionFromEnvironment($root), date('Y-m-d'));
        file_put_contents($root.'/'.$baselinePath, $baseline->toJson($about));
    }

    $comparison = $baseline->compare($report);
    $decisions = DecisionLog::fromFile($root.'/docs/DECISIONS.md');
    $unknownDecisions = array_values(array_filter(
        array_column($baseline->approvedAdditions(), 'decision'),
        fn (string $decision): bool => ! $decisions->has($decision),
    ));

    expect($comparison->isClean())->toBeTrue("{$baselinePath} tidak cocok:\n".$comparison->describe())
        ->and($unknownDecisions)->toBe([], "approved_additions di {$baselinePath} merujuk keputusan yang tidak ada di docs/DECISIONS.md.");
}

function baselineDecisionFromEnvironment(string $root): ?string
{
    $decision = getenv('BASELINE_DECISION') ?: null;

    if ($decision !== null && ! DecisionLog::fromFile($root.'/docs/DECISIONS.md')->has($decision)) {
        throw new RuntimeException("BASELINE_DECISION {$decision} tidak ditemukan di docs/DECISIONS.md.");
    }

    return $decision;
}
