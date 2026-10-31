<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Agri\Ledger\AgriAccounts;
use Modules\B2b\Ledger\B2bAccounts;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\CloudKitchen\Ledger\CloudKitchenAccounts;
use Modules\Core\Ledger\CoreAccounts;
use Modules\Edu\Ledger\EduAccounts;
use Modules\Egy\Ledger\EgyAccounts;
use Modules\EnterpriseFinance\Ledger\EnterpriseFinanceAccounts;
use Modules\Epc\Ledger\EpcAccounts;
use Modules\Esg\Ledger\EsgAccounts;
use Modules\Ev\Ledger\EvAccounts;
use Modules\Fleet\Ledger\FleetAccounts;
use Modules\Hcm\Ledger\HcmAccounts;
use Modules\Hospital\Ledger\HospitalAccounts;
use Modules\Hotel\Ledger\HotelAccounts;
use Modules\Insurance\Ledger\InsuranceAccounts;
use Modules\Logistics\Ledger\LogisticsAccounts;
use Modules\Mall\Ledger\MallAccounts;
use Modules\Manufacturing\Ledger\ManufacturingAccounts;
use Modules\Med\Ledger\MedAccounts;
use Modules\Mining\Ledger\MiningAccounts;
use Modules\Proptech\Ledger\ProptechAccounts;
use Modules\Ret\Ledger\RetAccounts;
use Modules\Rwa\Ledger\RwaAccounts;
use Modules\Tlx\Ledger\TlxAccounts;
use Modules\Trade\Ledger\TradeAccounts;
use Modules\TradeFinance\Ledger\TradeFinanceAccounts;
use Modules\Treasury\Ledger\TreasuryAccounts;
use Modules\Vending\Ledger\VendingAccounts;
use Modules\Venue\Ledger\VenueAccounts;
use Modules\Wealth\Ledger\WealthAccounts;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul-modul ekosistem.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
class LedgerAccountsSeeder extends Seeder
{
    /**
     * @var list<class-string>
     */
    public const ACCOUNT_REGISTRIES = [
        AgriAccounts::class,
        B2bAccounts::class,
        CloudKitchenAccounts::class,
        CoreAccounts::class,
        EduAccounts::class,
        EgyAccounts::class,
        EnterpriseFinanceAccounts::class,
        EpcAccounts::class,
        EsgAccounts::class,
        EvAccounts::class,
        FleetAccounts::class,
        HcmAccounts::class,
        HospitalAccounts::class,
        HotelAccounts::class,
        InsuranceAccounts::class,
        LogisticsAccounts::class,
        MallAccounts::class,
        ManufacturingAccounts::class,
        MedAccounts::class,
        MiningAccounts::class,
        ProptechAccounts::class,
        RetAccounts::class,
        RwaAccounts::class,
        TlxAccounts::class,
        TradeAccounts::class,
        TradeFinanceAccounts::class,
        TreasuryAccounts::class,
        VendingAccounts::class,
        VenueAccounts::class,
        WealthAccounts::class,
    ];

    public function run(): void
    {
        foreach (self::ACCOUNT_REGISTRIES as $registry) {
            /** @var list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}> $definitions */
            $definitions = $registry::definitions();

            foreach ($definitions as $def) {
                LedgerAccount::firstOrCreate(
                    ['code' => $def['code']],
                    [
                        'uuid' => (string) Str::uuid(),
                        'name' => $def['name'],
                        'asset_code' => $def['asset_code'] ?? 'IDR',
                        'kind' => $def['kind']->value ?? (string) $def['kind'],
                        'allow_negative' => $def['allow_negative'] ?? true,
                        'cached_balance' => '0',
                        'is_frozen' => false,
                    ]
                );
            }
        }

        LedgerAccount::firstOrCreate(
            ['code' => 'hsp:hospital_package_revenue:IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Hospital Package Revenue',
                'asset_code' => 'IDR',
                'kind' => AccountKind::REVENUE->value,
                'allow_negative' => false,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
