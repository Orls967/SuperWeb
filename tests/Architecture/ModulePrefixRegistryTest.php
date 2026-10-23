<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Quality\ArchScan\Rules\ForeignTablePrefixRule;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\SourceFile;

it('ensures table prefixes are well-formed and unique', function (): void {
    $root = dirname(__DIR__, 2);
    $config = require $root.'/config/modules.php';
    $prefixes = $config['table_prefixes'] ?? [];

    expect($prefixes)->not->toBeEmpty();

    $seen = [];
    foreach ($prefixes as $prefix => $owner) {
        expect($prefix)->toMatch('/^[a-z0-9]+_$/', "Format prefiks '{$prefix}' tidak valid (harus lowercase alphanumeric dengan akhiran underscore).");
        expect(isset($seen[$prefix]))->toBeFalse("Prefiks '{$prefix}' terduplikasi di config/modules.php.");
        $seen[$prefix] = true;
        expect($owner)->toBeString()->not->toBeEmpty();
    }
});

it('resolves specific disambiguated prefixes correctly', function (): void {
    $root = dirname(__DIR__, 2);
    $config = require $root.'/config/modules.php';
    $registry = ModuleRegistry::fromConfig($config);

    // Disambiguations explicitly mandated by R4.2:
    // prc_ (Procurement) vs pric_ (Pricing)
    expect($registry->ownerOfTable('prc_purchase_orders'))->toBe('Procurement');
    expect($registry->ownerOfTable('pric_rate_cards'))->toBe('Pricing');

    // ven_ (Venue) vs vnd_ (Vending)
    expect($registry->ownerOfTable('ven_events'))->toBe('Venue');
    expect($registry->ownerOfTable('vnd_machines'))->toBe('Vending');

    // resto_ (Resto) vs ckt_ (CloudKitchen)
    expect($registry->ownerOfTable('resto_menus'))->toBe('Resto');
    expect($registry->ownerOfTable('ckt_kitchen_stations'))->toBe('CloudKitchen');

    // oto_ (Mobility)
    expect($registry->ownerOfTable('oto_trips'))->toBe('Mobility');

    // Core tables
    expect($registry->ownerOfTable('sim_runs'))->toBe('Core');
    expect($registry->ownerOfTable('platform_configs'))->toBe('Core');
    expect($registry->ownerOfTable('role_assignments'))->toBe('Core');
    expect($registry->ownerOfTable('user_preferences'))->toBe('Core');
});

it('flags foreign table access via ForeignTablePrefixRule', function (): void {
    $root = dirname(__DIR__, 2);
    $config = require $root.'/config/modules.php';
    $registry = ModuleRegistry::fromConfig($config);
    $rule = new ForeignTablePrefixRule;

    $code = <<<'PHP'
    <?php
    declare(strict_types=1);
    namespace Modules\AutoServe\Application\Services;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    class IllegalAccessService {
        public function run(): void {
            DB::table('bank_accounts')->get();
            Schema::create('resto_tables', function ($table) {});
        }
    }
    PHP;

    $file = new SourceFile('modules/AutoServe/Application/Services/IllegalAccessService.php', $code);
    $violations = $rule->check($file, $registry);

    expect($violations)->toHaveCount(2);
    expect($violations[0]->ruleId)->toBe('A2');
    expect($violations[0]->signature)->toBe("DB::table('bank_accounts')");
    expect($violations[1]->ruleId)->toBe('A2');
    expect($violations[1]->signature)->toBe("Schema::create('resto_tables')");
});
