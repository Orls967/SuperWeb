<?php

use App\Providers\AppServiceProvider;
use Modules\Agency\AgencyServiceProvider;
use Modules\Asset\AssetServiceProvider;
use Modules\AutoDex\AutoDexServiceProvider;
use Modules\AutoServe\AutoServeServiceProvider;
use Modules\Banking\BankingServiceProvider;
use Modules\Contract\ContractServiceProvider;
use Modules\Core\CoreServiceProvider;
use Modules\Crypto\CryptoServiceProvider;
use Modules\Distribution\DistributionServiceProvider;
use Modules\Finance\FinanceServiceProvider;
use Modules\Inventory\InventoryServiceProvider;
use Modules\Logistics\LogisticsServiceProvider;
use Modules\Mall\MallServiceProvider;
use Modules\Manufacturing\ManufacturingServiceProvider;
use Modules\Partner\PartnerServiceProvider;
use Modules\Party\PartyServiceProvider;
use Modules\Payment\PaymentServiceProvider;
use Modules\Pricing\PricingServiceProvider;
use Modules\Procurement\ProcurementServiceProvider;
use Modules\Resto\RestoServiceProvider;
use Modules\Shared\SharedServiceProvider;
use Modules\Store\StoreServiceProvider;
use Modules\Supplier\SupplierServiceProvider;
use Modules\Trade\TradeServiceProvider;
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
    \Modules\TradeFinance\TradeFinanceServiceProvider::class,
    \Modules\International\InternationalServiceProvider::class,
    \Modules\Intercompany\IntercompanyServiceProvider::class,
    \Modules\ControlTower\ControlTowerServiceProvider::class,
    \Modules\EnterpriseFinance\EnterpriseFinanceServiceProvider::class,
    \Modules\Integration\IntegrationServiceProvider::class,
    \Modules\Hcm\HcmServiceProvider::class,
    \Modules\Plm\PlmServiceProvider::class,
    \Modules\Esg\EsgServiceProvider::class,
    \Modules\B2b\B2bServiceProvider::class,
    \Modules\Agri\AgriServiceProvider::class,
    \Modules\Epc\EpcServiceProvider::class,
];





