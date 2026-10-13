<?php

declare(strict_types=1);

use App\Quality\TestHygiene\LedgerAccountInTestRule;

it('reports ledger accounts created by tests', function (): void {
    $code = <<<'PHP'
        <?php
        LedgerAccount::firstOrCreate(['code' => 'tlx:clearing:IDR'], ['kind' => 'asset']);
        \Modules\Banking\Domain\Models\LedgerAccount::create(['code' => 'x']);
        DB::table('bank_ledger_accounts')->insert(['code' => 'y']);
        PHP;

    expect(ruleSignatures(new LedgerAccountInTestRule, 'modules/Tlx/tests/Feature/IspTest.php', $code))->toBe([
        'LedgerAccount::firstOrCreate()',
        'LedgerAccount::create()',
        "DB::table('bank_ledger_accounts')->insert()",
    ]);
});

it('does not report reading accounts or seeding the production chart of accounts', function (): void {
    $code = <<<'PHP'
        <?php
        $this->seed(LedgerAccountsSeeder::class);
        $account = LedgerAccount::where('code', 'x')->firstOrFail();
        DB::table('bank_ledger_accounts')->count();
        PHP;

    expect(ruleSignatures(new LedgerAccountInTestRule, 'tests/Feature/LedgerTest.php', $code))->toBe([]);
});
