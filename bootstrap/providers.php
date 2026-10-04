<?php

use App\Providers\AppServiceProvider;
use Modules\Asset\AssetServiceProvider;
use Modules\AutoDex\AutoDexServiceProvider;
use Modules\AutoServe\AutoServeServiceProvider;
use Modules\Banking\BankingServiceProvider;
use Modules\Contract\ContractServiceProvider;
use Modules\Core\CoreServiceProvider;
use Modules\Crypto\CryptoServiceProvider;
use Modules\Finance\FinanceServiceProvider;
use Modules\Inventory\InventoryServiceProvider;
use Modules\Logistics\LogisticsServiceProvider;
use Modules\Mall\MallServiceProvider;
use Modules\Party\PartyServiceProvider;
use Modules\Payment\PaymentServiceProvider;
use Modules\Resto\RestoServiceProvider;
use Modules\Shared\SharedServiceProvider;
use Modules\Store\StoreServiceProvider;

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
];
