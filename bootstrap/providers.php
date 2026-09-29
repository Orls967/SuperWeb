<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    Modules\Shared\SharedServiceProvider::class,
    Modules\Core\CoreServiceProvider::class,
    Modules\AutoServe\AutoServeServiceProvider::class,
    Modules\AutoDex\AutoDexServiceProvider::class,
];
