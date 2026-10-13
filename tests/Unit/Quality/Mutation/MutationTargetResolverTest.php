<?php

declare(strict_types=1);

use App\Quality\Mutation\MutationTargetResolver;

/*
| PROGRESS R0.12: MutationTargetResolverTest (K-MUT, KONSEP §A14.8).
| Memverifikasi logika resolusi kelas Action/Service dari daftar file git diff.
*/

it('resolves Action and Service classes from changed files and ignores unrelated files', function (): void {
    $files = [
        'modules/Hospital/Application/Actions/AdmitPatientAction.php',
        'modules/Hospital/Application/Actions/AdmitPatientAction.php', // duplicate to test array_unique
        'modules/Billing/Application/Services/InvoiceGenerationService.php',
        'app/Quality/Gate/JUnitParser.php',
        'modules/Core/Actions/AuditLogAction.php', // non-Application ignored
        'modules/Core/Services/HealthService.php', // non-Application ignored
        'modules/Hospital/tests/Feature/AdmitPatientTest.php', // test ignored
        'tests/Architecture/ArchTest.php', // test ignored
        'database/migrations/2026_10_10_000000_create_test_table.php', // migration ignored
        'database/seeders/DatabaseSeeder.php', // seeder ignored
        'config/app.php', // config ignored
        'config/modules.php', // config ignored
        'resources/views/welcome.blade.php', // view ignored
        'docs/PROGRESS.md', // markdown ignored
        'modules/Billing/Domain/Models/Invoice.php', // model not action/service
        'app/Http/Controllers/SomeController.php', // non-Quality app class ignored
        'app/Quality/Gate/ProcessRunnerInterface.php', // interface ignored
    ];

    $resolved = MutationTargetResolver::resolveClasses($files);

    expect($resolved)->toBe([
        'App\Quality\Gate\JUnitParser',
        'Modules\Billing\Application\Services\InvoiceGenerationService',
        'Modules\Hospital\Application\Actions\AdmitPatientAction',
    ]);
});

it('returns empty array when no Action or Service classes are changed', function (): void {
    $files = [
        'docs/PROGRESS.md',
        'README.md',
        'composer.json',
        'tests/TestCase.php',
        'tests/Feature/ExampleTest.php',
        'database/seeders/DatabaseSeeder.php',
        'config/database.php',
        'modules/Inventory/Domain/Models/Item.php',
        'modules/Inventory/Domain/Repositories/ItemRepository.php',
    ];

    $resolved = MutationTargetResolver::resolveClasses($files);

    expect($resolved)->toBe([]);
});

it('resolves associated test paths for classes by convention', function (): void {
    $root = dirname(__DIR__, 4);
    $appQualityTest = MutationTargetResolver::resolveTestPathForClass('App\\Quality\\Progress\\ProgressIntegrityScanner', $root);
    expect($appQualityTest)->not->toBeNull()
        ->and($appQualityTest)->toContain('ProgressIntegrityScannerTest.php');

    $gateRunnerTest = MutationTargetResolver::resolveTestPathForClass('App\\Quality\\Gate\\GateRunner', $root);
    expect($gateRunnerTest)->not->toBeNull()
        ->and($gateRunnerTest)->toContain('GateRunnerTest.php');

    // Subdirectory fallback
    $subDirTest = MutationTargetResolver::resolveTestPathForClass('App\\Quality\\Progress\\NonExistentClass', $root);
    expect($subDirTest)->toBe('tests/Unit/Quality/Progress');

    // Null for unrecognized class
    expect(MutationTargetResolver::resolveTestPathForClass('App\\Models\\User', $root))->toBeNull();
    expect(MutationTargetResolver::resolveTestPathForClass('Modules\\', $root))->toBeNull();
});

it('resolves module test paths using virtual directory structure', function (): void {
    $tempDir = sys_get_temp_dir().'/mut_resolver_'.uniqid();
    mkdir($tempDir.'/modules/Billing/tests/Feature', 0777, true);
    mkdir($tempDir.'/modules/Billing/tests/Unit', 0777, true);
    touch($tempDir.'/modules/Billing/tests/Feature/InvoiceActionTest.php');
    touch($tempDir.'/modules/Billing/tests/Unit/ReceiptServiceTest.php');

    // 1. Matches Feature
    $feature = MutationTargetResolver::resolveTestPathForClass('Modules\\Billing\\Application\\Actions\\InvoiceAction', $tempDir);
    expect($feature)->toBe('modules/Billing/tests/Feature/InvoiceActionTest.php');

    // 2. Matches Unit
    $unit = MutationTargetResolver::resolveTestPathForClass('Modules\\Billing\\Application\\Services\\ReceiptService', $tempDir);
    expect($unit)->toBe('modules/Billing/tests/Unit/ReceiptServiceTest.php');

    // 3. Fallback to module test dir
    $fallback = MutationTargetResolver::resolveTestPathForClass('Modules\\Billing\\Application\\Services\\UnknownService', $tempDir);
    expect($fallback)->toBe('modules/Billing/tests');

    // 4. Module directory doesn't exist
    $noModule = MutationTargetResolver::resolveTestPathForClass('Modules\\NonExistent\\Application\\Actions\\SomeAction', $tempDir);
    expect($noModule)->toBeNull();

    // Cleanup
    unlink($tempDir.'/modules/Billing/tests/Feature/InvoiceActionTest.php');
    unlink($tempDir.'/modules/Billing/tests/Unit/ReceiptServiceTest.php');
    rmdir($tempDir.'/modules/Billing/tests/Feature');
    rmdir($tempDir.'/modules/Billing/tests/Unit');
    rmdir($tempDir.'/modules/Billing/tests');
    rmdir($tempDir.'/modules/Billing');
    rmdir($tempDir.'/modules');
    rmdir($tempDir);
});
