<?php

declare(strict_types=1);

use App\Quality\TestHygiene\ModuleWithoutHttpTestRule;
use App\Quality\TestHygiene\TestHygieneScan;

it('reports route files of modules whose tests never send a request', function (): void {
    $routes = "<?php\nRoute::middleware(['web', 'auth'])->get('/esg', fn () => null);";
    $rule = new ModuleWithoutHttpTestRule(['Mall']);

    expect(ruleSignatures($rule, 'modules/Esg/routes/web.php', $routes))->toBe(['modul Esg tanpa test HTTP'])
        ->and(ruleSignatures($rule, 'modules/Mall/routes/web.php', $routes))->toBe([])
        ->and(ruleSignatures($rule, 'modules/Esg/routes/console.php', "<?php\n// tidak ada rute"))->toBe([]);
});

it('credits tests in tests/Feature/{Module}/ and tests/Feature/{Module}…Test.php to that module', function (): void {
    $files = [
        sourceFile('tests/Feature/Procurement/ProcurementTest.php', "<?php\n\$this->actingAs(\$u)->get(route('procurement.dashboard'));"),
        sourceFile('tests/Feature/AutoDexCharacterizationTest.php', "<?php\n\$this->get('/dex/cars');"),
        sourceFile('modules/Mall/tests/Feature/BillingTest.php', "<?php\n\$this->post('/mall/billing/generate');"),
        sourceFile('modules/Esg/tests/Feature/EsgTest.php', "<?php\n\$service->recordEmission(\$data);"),
        sourceFile('tests/Feature/SecurityTest.php', "<?php\n\$this->get('/login');"),
    ];

    expect(TestHygieneScan::modulesWithHttpTests($files, ['Procurement', 'Mall', 'Esg', 'AutoDex', 'Auto']))->toBe(['AutoDex', 'Mall', 'Procurement']);
});
