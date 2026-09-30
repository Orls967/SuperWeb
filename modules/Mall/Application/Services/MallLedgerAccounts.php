<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Services;

use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerAccount;

/**
 * Pendaftaran akun sistem ledger milik modul Mall.
 *
 * Konvensi tanda mengikuti modul lain: akun pendapatan bertambah positif,
 * sedangkan akun kas/kliring sumber dana dicatat negatif (lihat docs/ARCHITECTURE.md).
 */
class MallLedgerAccounts
{
    public const PARKING_REVENUE = 'revenue:mall:parking:IDR';

    public const PARKING_CASH = 'cash:mall:parking:IDR';

    /**
     * @var array<string, array{name: string, kind: AccountKind, allow_negative: bool}>
     */
    private const DEFINITIONS = [
        self::PARKING_REVENUE => [
            'name' => 'Pendapatan Parkir Duta Mall',
            'kind' => AccountKind::REVENUE,
            'allow_negative' => false,
        ],
        self::PARKING_CASH => [
            'name' => 'Kas Fisik Gate Parkir Duta Mall',
            'kind' => AccountKind::CASH,
            'allow_negative' => true,
        ],
        'revenue:mall:membership:IDR' => [
            'name' => 'Pendapatan Keanggotaan Parkir Bulanan',
            'kind' => AccountKind::REVENUE,
            'allow_negative' => false,
        ],
    ];

    public function ensure(string $code): LedgerAccount
    {
        $definition = self::DEFINITIONS[$code] ?? [
            'name' => 'Akun Operasional Mall',
            'kind' => AccountKind::REVENUE,
            'allow_negative' => false,
        ];

        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'name' => $definition['name'],
                'asset_code' => 'IDR',
                'kind' => $definition['kind'],
                'allow_negative' => $definition['allow_negative'],
            ]
        );
    }

    /**
     * Daftarkan semua akun sistem parkir sekaligus (dipakai seeder).
     *
     * @return array<string, LedgerAccount>
     */
    public function ensureAll(): array
    {
        $accounts = [];

        foreach (array_keys(self::DEFINITIONS) as $code) {
            $accounts[$code] = $this->ensure($code);
        }

        return $accounts;
    }
}
