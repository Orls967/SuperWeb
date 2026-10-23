<?php

declare(strict_types=1);

namespace Tests\Feature\Quality;

use App\Quality\Audit\Fixtures\BankReconcileCorruptionFixture;
use App\Quality\Audit\Fixtures\ContractsVerifyChainCorruptionFixture;
use App\Quality\Audit\Fixtures\MallAuditBillingCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Services\SystemHealthService;

/*
| PROGRESS R5.3: Honest super:health-check verification.
| Verifies that super:health-check inspects 12 real two-source pillars, returns
| exit code 0 on clean state, and returns exit code 1 reporting the specific
| failed pillar when data is corrupted.
*/

uses(RefreshDatabase::class);

it('confirms super:health-check passes on clean state and verifies all 12 pillars', function (): void {
    $this->artisan('super:health-check')
        ->assertSuccessful()
        ->expectsOutputToContain('12 pilar')
        ->expectsOutputToContain('SELURUH SUB-SISTEM BERFUNGSI SEMPURNA');

    $health = app(SystemHealthService::class)->check();

    expect($health['status'])->toBe('HEALTHY')
        ->and($health['checks'])->toHaveCount(12);

    foreach ($health['checks'] as $key => $check) {
        expect($check['ok'])->toBeTrue("Pilar {$key} seharusnya HEALTHY")
            ->and($check['status'])->toBe('HEALTHY');
    }
});

it('fails honestly when the ledger pillar is corrupted', function (): void {
    $fixture = new BankReconcileCorruptionFixture;
    $keyword = $fixture->corrupt();

    $this->artisan('super:health-check')
        ->assertFailed()
        ->expectsOutputToContain('DITEMUKAN GANGGUAN PADA SATU ATAU LEBIH SUB-SISTEM KRITIS');

    $health = app(SystemHealthService::class)->check();

    expect($health['status'])->toBe('UNHEALTHY')
        ->and($health['checks']['ledger']['ok'])->toBeFalse()
        ->and($health['checks']['ledger']['status'])->toBe('UNHEALTHY');
});

it('fails honestly when the mall billing pillar is corrupted', function (): void {
    $fixture = new MallAuditBillingCorruptionFixture;
    $fixture->corrupt();

    $this->artisan('super:health-check')
        ->assertFailed()
        ->expectsOutputToContain('DITEMUKAN GANGGUAN PADA SATU ATAU LEBIH SUB-SISTEM KRITIS');

    $health = app(SystemHealthService::class)->check();

    expect($health['status'])->toBe('UNHEALTHY')
        ->and($health['checks']['mall_billing']['ok'])->toBeFalse()
        ->and($health['checks']['mall_billing']['status'])->toBe('UNHEALTHY');
});

it('fails honestly when the contracts chain pillar is corrupted', function (): void {
    $fixture = new ContractsVerifyChainCorruptionFixture;
    $fixture->corrupt();

    $this->artisan('super:health-check')
        ->assertFailed()
        ->expectsOutputToContain('DITEMUKAN GANGGUAN PADA SATU ATAU LEBIH SUB-SISTEM KRITIS');

    $health = app(SystemHealthService::class)->check();

    expect($health['status'])->toBe('UNHEALTHY')
        ->and($health['checks']['contracts']['ok'])->toBeFalse()
        ->and($health['checks']['contracts']['status'])->toBe('UNHEALTHY');
});
