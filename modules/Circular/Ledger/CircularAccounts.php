<?php

declare(strict_types=1);

namespace Modules\Circular\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan akun standar untuk modul Circular Marketplace (KONSEP Lini 24, D24-4).
 */
final class CircularAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'cir:fee_revenue:IDR',
                'name' => 'Circular Marketplace Fee Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'escrow:payment:IDR',
                'name' => 'Circular Marketplace Escrow Clearing',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}

