<?php

use App\Providers\AppServiceProvider;
use Modules\AutoDex\AutoDexServiceProvider;
use Modules\AutoServe\AutoServeServiceProvider;
use Modules\Banking\BankingServiceProvider;
use Modules\Core\CoreServiceProvider;
use Modules\Shared\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    CoreServiceProvider::class,
    AutoServeServiceProvider::class,
    AutoDexServiceProvider::class,
    BankingServiceProvider::class,
];
