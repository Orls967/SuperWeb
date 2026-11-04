<?php

use App\Providers\AppServiceProvider;
use Modules\Agency\AgencyServiceProvider;
use Modules\Agri\AgriServiceProvider;
use Modules\Asset\AssetServiceProvider;
use Modules\AutoDex\AutoDexServiceProvider;
use Modules\AutoServe\AutoServeServiceProvider;
use Modules\B2b\B2bServiceProvider;
use Modules\Banking\BankingServiceProvider;
use Modules\Circular\CircularServiceProvider;
use Modules\CloudKitchen\CloudKitchenServiceProvider;
use Modules\Contract\ContractServiceProvider;
use Modules\ControlTower\ControlTowerServiceProvider;
use Modules\Core\CoreServiceProvider;
use Modules\Crypto\CryptoServiceProvider;
use Modules\Distribution\DistributionServiceProvider;
use Modules\Edu\EduServiceProvider;
use Modules\Egy\EgyServiceProvider;
use Modules\EnterpriseFinance\EnterpriseFinanceServiceProvider;
use Modules\Epc\EpcServiceProvider;
use Modules\Esg\EsgServiceProvider;
use Modules\Fashion\FashionServiceProvider;
use Modules\Finance\FinanceServiceProvider;
use Modules\Food\FoodServiceProvider;
use Modules\Governance\GovernanceServiceProvider;
use Modules\Hcm\HcmServiceProvider;
use Modules\Hospital\HospitalServiceProvider;
use Modules\Hotel\HotelServiceProvider;
use Modules\Insurance\InsuranceServiceProvider;
use Modules\Integration\IntegrationServiceProvider;
use Modules\Intercompany\IntercompanyServiceProvider;
use Modules\International\InternationalServiceProvider;
use Modules\Inventory\InventoryServiceProvider;
use Modules\Logistics\LogisticsServiceProvider;
use Modules\Mall\MallServiceProvider;
use Modules\Manufacturing\ManufacturingServiceProvider;
use Modules\Marine\MarineServiceProvider;
use Modules\Med\MedServiceProvider;
use Modules\Mining\MiningServiceProvider;
use Modules\Mobility\Ev\EvServiceProvider;
use Modules\Mobility\Fleet\FleetServiceProvider;
use Modules\Mobility\Telematics\TelematicsServiceProvider;
use Modules\Partner\PartnerServiceProvider;
use Modules\Party\PartyServiceProvider;
use Modules\Payment\PaymentServiceProvider;
use Modules\Plm\PlmServiceProvider;
use Modules\Port\PortServiceProvider;
use Modules\Pricing\PricingServiceProvider;
use Modules\Procurement\ProcurementServiceProvider;
use Modules\Proptech\ProptechServiceProvider;
use Modules\Resto\RestoServiceProvider;
use Modules\Ret\RetServiceProvider;
use Modules\Rwa\RwaServiceProvider;
use Modules\Shared\SharedServiceProvider;
use Modules\Store\StoreServiceProvider;
use Modules\Supplier\SupplierServiceProvider;
use Modules\Syariah\SyariahServiceProvider;
use Modules\Tlx\TlxServiceProvider;
use Modules\Trade\TradeServiceProvider;
use Modules\TradeFinance\TradeFinanceServiceProvider;
use Modules\Treasury\TreasuryServiceProvider;
use Modules\Vending\VendingServiceProvider;
use Modules\Venue\VenueServiceProvider;
use Modules\Wealth\WealthServiceProvider;
use Modules\Wms\WmsServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    CoreServiceProvider::class,
    AutoServeServiceProvider::class,
    AutoDexServiceProvider::class,
    BankingServiceProvider::class,
    CircularServiceProvider::class,
    PaymentServiceProvider::class,
    PortServiceProvider::class,
    InventoryServiceProvider::class,
    StoreServiceProvider::class,
    CryptoServiceProvider::class,
    FinanceServiceProvider::class,
    FoodServiceProvider::class,
    FashionServiceProvider::class,
    RestoServiceProvider::class,
    MallServiceProvider::class,
    LogisticsServiceProvider::class,
    PartyServiceProvider::class,
    AssetServiceProvider::class,
    ContractServiceProvider::class,
    SupplierServiceProvider::class,
    ProcurementServiceProvider::class,
    ManufacturingServiceProvider::class,
    AgencyServiceProvider::class,
    PartnerServiceProvider::class,
    DistributionServiceProvider::class,
    PricingServiceProvider::class,
    WmsServiceProvider::class,
    TreasuryServiceProvider::class,
    TradeServiceProvider::class,
    TradeFinanceServiceProvider::class,
    InternationalServiceProvider::class,
    IntercompanyServiceProvider::class,
    ControlTowerServiceProvider::class,
    EnterpriseFinanceServiceProvider::class,
    IntegrationServiceProvider::class,
    HcmServiceProvider::class,
    PlmServiceProvider::class,
    EsgServiceProvider::class,
    B2bServiceProvider::class,
    AgriServiceProvider::class,
    EpcServiceProvider::class,
    GovernanceServiceProvider::class,
    TelematicsServiceProvider::class,
    EvServiceProvider::class,
    FleetServiceProvider::class,
    RwaServiceProvider::class,
    InsuranceServiceProvider::class,
    WealthServiceProvider::class,
    CloudKitchenServiceProvider::class,
    VendingServiceProvider::class,
    ProptechServiceProvider::class,
    HospitalServiceProvider::class,
    VenueServiceProvider::class,
    HotelServiceProvider::class,
    MiningServiceProvider::class,
    EgyServiceProvider::class,
    TlxServiceProvider::class,
    MedServiceProvider::class,
    EduServiceProvider::class,
    RetServiceProvider::class,
    SyariahServiceProvider::class,
    MarineServiceProvider::class,
];
