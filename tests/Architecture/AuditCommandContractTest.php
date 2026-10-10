<?php

declare(strict_types=1);

use App\Quality\Audit\AuditCommandRegistry;
use App\Quality\Audit\Fixtures\ApiAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\BankReconcileCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| PROGRESS R0.10: AuditCommandContractTest (KONSEP.md §A14.4).
| Every audit, reconcile, and verification command must have a corruption fixture
| that proves the audit fails on tampered data. Missing fixtures are tracked in
| a ratchet baseline (targeted to reach 0 in R5.2).
*/

uses(TestCase::class, RefreshDatabase::class);

it('confirms bank:reconcile passes on clean state and fails when data is corrupted', function (): void {
    $this->artisan('bank:reconcile')->assertSuccessful();

    $fixture = new BankReconcileCorruptionFixture;
    $keyword = $fixture->corrupt();

    $this->artisan('bank:reconcile')
        ->assertFailed()
        ->expectsOutputToContain($keyword);
});

it('confirms api:audit passes on clean state and fails when signature is corrupted', function (): void {
    $this->artisan('api:audit')->assertSuccessful();

    $fixture = new ApiAuditCorruptionFixture;
    $keyword = $fixture->corrupt();

    $this->artisan('api:audit')
        ->assertFailed()
        ->expectsOutputToContain($keyword);
});

it('keeps audit commands without corruption fixtures within the ratchet baseline', function (): void {
    $missing = AuditCommandRegistry::commandsWithoutFixtures();

    assertSetBaseline(
        'tests/Architecture/baselines/audit-contract.json',
        $missing,
        'Daftar command audit/reconcile/verify tanpa fixture korupsi (PROGRESS R0.10, KONSEP §A14.4). Hanya boleh berkurang (R5.2).'
    );
});
