<?php

declare(strict_types=1);

use App\Quality\Ledger\LedgerNormalBalanceScanner;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| PROGRESS R0.10: LedgerNormalBalanceTest (KONSEP.md §A14.5).
| Every ledger account balance must match its normal side (credit >= 0, debit <= 0).
| Legacy accounts with inverted balances are tracked in a ratchet baseline
| (targeted to reach 0 in R1.2).
*/

uses(TestCase::class, RefreshDatabase::class);

it('keeps accounts with inverted normal balances within the ratchet baseline', function (): void {
    $this->seed(DatabaseSeeder::class);

    $violations = LedgerNormalBalanceScanner::violations();

    assertSetBaseline(
        'tests/Architecture/baselines/ledger-normal-balance.json',
        $violations,
        'Akun ledger dengan saldo berlawanan sisi normal pada database seed (PROGRESS R0.10, KONSEP §A14.5). Hanya boleh menyusut (R1.2).'
    );
});
