<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    Modules\Shared\SharedServiceProvider::class,
    Modules\Core\CoreServiceProvider::class,
];
