<?php

declare(strict_types=1);

use App\Quality\Freeze\IntegrationFreeze;

it('tracks files outside adapter directories and tables without the intg_ prefix', function (): void {
    $files = [
        'modules/Integration/Application/Services/EsgCarbonService.php',
        'modules/Integration/Adapters/BankStatementAdapter.php',
        'modules/Integration/Http/Controllers/Api/OrderController.php',
        'modules/Integration/Http/Controllers/IntegrationController.php',
        'modules/Integration/tests/Feature/IntegrationTest.php',
    ];

    $entries = IntegrationFreeze::entriesFrom($files, ['esg_carbon_credits', 'intg_webhook_subscriptions']);

    expect($entries)->toBe([
        'file:modules/Integration/Application/Services/EsgCarbonService.php',
        'file:modules/Integration/Http/Controllers/IntegrationController.php',
        'table:esg_carbon_credits',
    ]);
});
