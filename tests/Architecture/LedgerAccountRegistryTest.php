<?php

declare(strict_types=1);

use App\Quality\Ledger\LedgerAccountScanner;
use App\Quality\Support\SourceFinder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Tests\TestCase;

/*
| PROGRESS R0.10: LedgerAccountRegistryTest (KONSEP.md §A14.5).
| Every ledger account referenced in production code must be registered and seeded
| in the chart of accounts. Unseeded legacy accounts are tracked in a ratchet baseline
| (targeted to reach 0 in R1.3).
*/

uses(TestCase::class, RefreshDatabase::class);

it('keeps unseeded ledger accounts within the ratchet baseline', function (): void {
    $root = dirname(__DIR__, 2);
    $files = SourceFinder::phpFiles($root, ['modules', 'app'], ['#(?:^|/)modules/[^/]+/tests/#', '#(?:^|/)tests/#']);

    $referencedCodes = LedgerAccountScanner::referencedAccounts($files);

    $this->seed(DatabaseSeeder::class);
    $seededCodes = LedgerAccount::pluck('code')->all();

    $unseeded = LedgerAccountScanner::unseededAccounts($referencedCodes, $seededCodes);

    assertSetBaseline(
        'tests/Architecture/baselines/ledger-accounts.json',
        $unseeded,
        'Akun ledger yang dipakai di kode produksi tetapi belum terdefinisi & terseed di database seeder (PROGRESS R0.10, KONSEP §A14.5). Hanya boleh menyusut (R1.3).'
    );
});
