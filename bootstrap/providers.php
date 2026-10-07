<?php

use App\Providers\AppServiceProvider;
use Modules\Agency\AgencyServiceProvider;
use Modules\Agri\AgriServiceProvider;
use Modules\Asset\AssetServiceProvider;
use Modules\AutoDex\AutoDexServiceProvider;
use Modules\AutoServe\AutoServeServiceProvider;
use Modules\B2b\B2bServiceProvider;
use Modules\Banking\BankingServiceProvider;
use Modules\Contract\ContractServiceProvider;
use Modules\ControlTower\ControlTowerServiceProvider;
use Modules\Core\CoreServiceProvider;
use Modules\Crypto\CryptoServiceProvider;
use Modules\Distribution\DistributionServiceProvider;
use Modules\EnterpriseFinance\EnterpriseFinanceServiceProvider;
use Modules\Epc\EpcServiceProvider;
use Modules\Esg\EsgServiceProvider;
use Modules\Finance\FinanceServiceProvider;
use Modules\Hcm\HcmServiceProvider;
use Modules\Integration\IntegrationServiceProvider;
use Modules\Intercompany\IntercompanyServiceProvider;
use Modules\International\InternationalServiceProvider;
use Modules\Inventory\InventoryServiceProvider;
use Modules\Logistics\LogisticsServiceProvider;
use Modules\Mall\MallServiceProvider;
use Modules\Manufacturing\ManufacturingServiceProvider;
use Modules\Partner\PartnerServiceProvider;
use Modules\Party\PartyServiceProvider;
use Modules\Payment\PaymentServiceProvider;
use Modules\Plm\PlmServiceProvider;
use Modules\Pricing\PricingServiceProvider;
use Modules\Procurement\ProcurementServiceProvider;
use Modules\Resto\RestoServiceProvider;
use Modules\Shared\SharedServiceProvider;
use Modules\Store\StoreServiceProvider;
use Modules\Supplier\SupplierServiceProvider;
use Modules\Telematics\TelematicsServiceProvider;
use Modules\Trade\TradeServiceProvider;
use Modules\TradeFinance\TradeFinanceServiceProvider;
use Modules\Treasury\TreasuryServiceProvider;
use Modules\Wms\WmsServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    CoreServiceProvider::class,
    AutoServeServiceProvider::class,
    AutoDexServiceProvider::class,
    BankingServiceProvider::class,
    PaymentServiceProvider::class,
    InventoryServiceProvider::class,
    StoreServiceProvider::class,
    CryptoServiceProvider::class,
    FinanceServiceProvider::class,
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
    TelematicsServiceProvider::class,
];
