<?php

declare(strict_types=1);

namespace Modules\Forest\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

final class ForestAccounts
{
    public const AR_TIMBER = 'ar:for:timber:IDR';

    public const REVENUE_TIMBER = 'for:revenue:IDR';

    public const FORESTRY_LEVY_PAYABLE = 'for:levy_payable:IDR';

    public static function definitions(): array
    {
        return [
            [
                'code' => self::AR_TIMBER,
                'name' => 'Piutang Usaha Penjualan Kayu',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::REVENUE_TIMBER,
                'name' => 'Pendapatan Penjualan Kayu Hutan',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::FORESTRY_LEVY_PAYABLE,
                'name' => 'Utang Pungutan Kehutanan PSDH DR',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
