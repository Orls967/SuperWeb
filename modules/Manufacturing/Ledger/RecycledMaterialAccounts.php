<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Akun aditif untuk kliring penerimaan material daur ulang (KONSEP §5F, D79-3).
 */
final class RecycledMaterialAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'inv:materials:IDR',
                'name' => 'Raw Materials Inventory',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'mfg:recycled_material_clearing:IDR',
                'name' => 'Recycled Material Receipt Clearing',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true, // Akun kliring penerimaan vs penagihan nota pemasok
            ],
        ];
    }
}
