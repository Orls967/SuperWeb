<?php

declare(strict_types=1);

namespace Modules\Marine\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

final class MarineAccounts
{
    public const ADVANCE_RECEIVABLE = 'mar:farmer_advance_receivable:IDR';

    public const INPUT_LOAN_RECEIVABLE = 'mar:farmer_input_loan_receivable:IDR';

    public const FEED_ISSUED_CLEARING = 'mar:feed_issued_clearing:IDR';

    public const HARVEST_INVENTORY = 'mar:harvest_inventory:IDR';

    public const HARVEST_PAYABLE = 'ap:mar:farmer_payable:IDR';

    public const SETTLEMENT_PAYOUT_CLEARING = 'mar:payout_clearing:IDR';

    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => self::ADVANCE_RECEIVABLE,
                'name' => 'Marine Farmer Advance Input Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::INPUT_LOAN_RECEIVABLE,
                'name' => 'Marine Farmer Feed Input Loan Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::FEED_ISSUED_CLEARING,
                'name' => 'Marine Feed Issued Clearing',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::HARVEST_INVENTORY,
                'name' => 'Marine Harvest Seafood Inventory',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::HARVEST_PAYABLE,
                'name' => 'Marine Farmer Harvest Net Payout Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::SETTLEMENT_PAYOUT_CLEARING,
                'name' => 'Marine Farmer Settlement Payout Clearing Account',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
