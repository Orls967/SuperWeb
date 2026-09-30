<?php

declare(strict_types=1);

arch('controllers do not use DB facade directly')
    ->expect([
        'Modules\AutoServe\Http\Controllers',
        'Modules\AutoDex\Http\Controllers',
        'Modules\Banking\Http\Controllers',
        'Modules\Payment\Http\Controllers',
        'Modules\Store\Http\Controllers',
        'Modules\Crypto\Http\Controllers',
        'Modules\Resto\Http\Controllers',
        'Modules\Mall\Http\Controllers',
    ])
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('domain does not depend on Http')
    ->expect([
        'Modules\AutoServe\Domain',
        'Modules\AutoDex\Domain',
        'Modules\Banking\Domain',
        'Modules\Core\Domain',
        'Modules\Payment\Domain',
        'Modules\Shared\Domain',
        'Modules\Store\Domain',
        'Modules\Inventory\Domain',
        'Modules\Crypto\Domain',
        'Modules\Resto\Domain',
        'Modules\Mall\Domain',
    ])
    ->not->toUse([
        'Illuminate\Http',
        'Illuminate\Routing',
    ]);

arch('AutoServe does not import AutoDex domain')
    ->expect('Modules\AutoServe')
    ->not->toUse('Modules\AutoDex\Domain');

arch('AutoDex does not import AutoServe domain')
    ->expect('Modules\AutoDex')
    ->not->toUse('Modules\AutoServe\Domain');
