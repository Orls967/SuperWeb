<?php

declare(strict_types=1);

use App\Quality\Ledger\LedgerNormalBalanceScanner;
use Modules\Banking\Domain\Models\LedgerAccount;

it('flags accounts whose cached balance is on the wrong normal side', function (): void {
    $accounts = [
        new LedgerAccount(['code' => 'rev:food:IDR', 'kind' => 'revenue', 'cached_balance' => '1000']), // credit normal, positive -> ok
        new LedgerAccount(['code' => 'rev:bad:IDR', 'kind' => 'revenue', 'cached_balance' => '-500']), // credit normal, negative -> violation!
        new LedgerAccount(['code' => 'ast:cash:IDR', 'kind' => 'asset', 'cached_balance' => '-2000']), // debit normal, negative -> ok
        new LedgerAccount(['code' => 'ast:bad:IDR', 'kind' => 'asset', 'cached_balance' => '500']), // debit normal, positive -> violation!
        new LedgerAccount(['code' => 'clr:ext:IDR', 'kind' => 'clearing', 'cached_balance' => '500']), // clearing -> free, ok
        new LedgerAccount(['code' => 'clr:ext2:IDR', 'kind' => 'clearing', 'cached_balance' => '-500']), // clearing -> free, ok
    ];

    $violations = LedgerNormalBalanceScanner::violations($accounts);

    expect($violations)->toBe([
        'account:ast:bad:IDR:debit_normal_has_credit_balance',
        'account:rev:bad:IDR:credit_normal_has_debit_balance',
    ]);
});
