<?php

use App\Quality\ArchScan\Rule;
use App\Quality\ArchScan\Violation;
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
