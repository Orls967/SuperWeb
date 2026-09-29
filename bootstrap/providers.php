<?php

use App\Providers\AppServiceProvider;
use Modules\AutoDex\AutoDexServiceProvider;
use Modules\AutoServe\AutoServeServiceProvider;
use Modules\Banking\BankingServiceProvider;
use Modules\Core\CoreServiceProvider;
use Modules\Inventory\InventoryServiceProvider;
use Modules\Payment\PaymentServiceProvider;
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
];
