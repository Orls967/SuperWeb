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
        'app/Services/AuditLogService.php',
        'app/Actions/CreateUserAction.php',
        'app/Quality/Gate/JUnitParser.php', // non-Action/Service ignored
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
    ];

    $resolved = MutationTargetResolver::resolveClasses($files);

    expect($resolved)->toBe([
        'App\Actions\CreateUserAction',
        'App\Services\AuditLogService',
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
});
