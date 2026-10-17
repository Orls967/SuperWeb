<?php

declare(strict_types=1);

namespace Modules\Banking\Application\Registry;

use BackedEnum;
use Modules\Agri\Ledger\AgriTransactionTypes;
use Modules\B2b\Ledger\B2bTransactionTypes;
use Modules\Banking\Contracts\TransactionTypeRegistry as RegistryInterface;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\CloudKitchen\Ledger\CloudKitchenTransactionTypes;
use Modules\Core\Ledger\CoreTransactionTypes;
use Modules\Edu\Ledger\EduTransactionTypes;
use Modules\Egy\Ledger\EgyTransactionTypes;
use Modules\EnterpriseFinance\Ledger\EnterpriseFinanceTransactionTypes;
use Modules\Epc\Ledger\EpcTransactionTypes;
use Modules\Esg\Ledger\EsgTransactionTypes;
use Modules\Fleet\Ledger\FleetTransactionTypes;
use Modules\Hcm\Ledger\HcmTransactionTypes;
use Modules\Hospital\Ledger\HospitalTransactionTypes;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;
use Modules\Logistics\Ledger\LogisticsTransactionTypes;
use Modules\Manufacturing\Ledger\ManufacturingTransactionTypes;
use Modules\Med\Ledger\MedTransactionTypes;
use Modules\Mining\Ledger\MiningTransactionTypes;
use Modules\Proptech\Ledger\ProptechTransactionTypes;
use Modules\Ret\Ledger\RetTransactionTypes;
use Modules\Rwa\Ledger\RwaTransactionTypes;
use Modules\Tlx\Ledger\TlxTransactionTypes;
use Modules\Trade\Ledger\TradeTransactionTypes;
use Modules\TradeFinance\Ledger\TradeFinanceTransactionTypes;
use Modules\Treasury\Ledger\TreasuryTransactionTypes;
use Modules\Vending\Ledger\VendingTransactionTypes;
use Modules\Venue\Ledger\VenueTransactionTypes;
use Modules\Wealth\Ledger\WealthTransactionTypes;

final class TransactionTypeRegistry
{
    /** @var list<class-string<RegistryInterface>> */
    private static array $registries = [
        TransactionType::class,
        AgriTransactionTypes::class,
        B2bTransactionTypes::class,
        CloudKitchenTransactionTypes::class,
        CoreTransactionTypes::class,
        EduTransactionTypes::class,
        EgyTransactionTypes::class,
        EnterpriseFinanceTransactionTypes::class,
        EpcTransactionTypes::class,
        EsgTransactionTypes::class,
        FleetTransactionTypes::class,
        HcmTransactionTypes::class,
        HospitalTransactionTypes::class,
        HotelTransactionTypes::class,
        InsuranceTransactionTypes::class,
        LogisticsTransactionTypes::class,
        ManufacturingTransactionTypes::class,
        MedTransactionTypes::class,
        MiningTransactionTypes::class,
        ProptechTransactionTypes::class,
        RetTransactionTypes::class,
        RwaTransactionTypes::class,
        TlxTransactionTypes::class,
        TradeTransactionTypes::class,
        TradeFinanceTransactionTypes::class,
        TreasuryTransactionTypes::class,
        VendingTransactionTypes::class,
        VenueTransactionTypes::class,
        WealthTransactionTypes::class,
    ];

    /** @var list<string>|null */
    private static ?array $cachedAll = null;

    /**
     * @param  class-string<RegistryInterface>  $enumClass
     */
    public static function register(string $enumClass): void
    {
        if (! in_array($enumClass, self::$registries, true)) {
            self::$registries[] = $enumClass;
            self::$cachedAll = null;
        }
    }

    /**
     * @return list<class-string<RegistryInterface>>
     */
    public static function registries(): array
    {
        return self::$registries;
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        if (self::$cachedAll !== null) {
            return self::$cachedAll;
        }

        $all = [];
        foreach (self::$registries as $enumClass) {
            if (enum_exists($enumClass)) {
                foreach ($enumClass::cases() as $case) {
                    if ($case instanceof BackedEnum) {
                        $all[] = (string) $case->value;
                    }
                }
            }
        }

        return self::$cachedAll = array_values(array_unique($all));
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    public static function reset(): void
    {
        self::$cachedAll = null;
    }
}
