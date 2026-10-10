<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Banking\Domain\Models\LedgerAccount;

final class BankReconcileCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'bank:reconcile';
    }

    public function corrupt(): string
    {
        $account = LedgerAccount::first();

        if ($account === null) {
            $account = LedgerAccount::create([
                'code' => 'test:corrupt:IDR',
                'name' => 'Akun Rusak',
                'asset_code' => 'IDR',
                'kind' => 'asset',
                'cached_balance' => '1000',
            ]);
        } else {
            $account->forceFill([
                'cached_balance' => (string) ((int) $account->cached_balance + 999_999_999),
            ])->saveQuietly();
        }

        return 'Account Balance Discrepancy';
    }
}
