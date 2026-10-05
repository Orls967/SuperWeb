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
        'Modules\Core\Http\Controllers',
        'Modules\Finance\Http\Controllers',
        'Modules\Logistics\Http\Controllers',
        'Modules\Contract\Http\Controllers',
        'Modules\Party\Http\Controllers',
        'Modules\Agency\Http\Controllers',
        'Modules\Partner\Http\Controllers',
        'Modules\Treasury\Http\Controllers',
        'Modules\Trade\Http\Controllers',
        'Modules\TradeFinance\Http\Controllers',
        'Modules\International\Http\Controllers',
        'Modules\Intercompany\Http\Controllers',
        'Modules\ControlTower\Http\Controllers',
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
        'Modules\Logistics\Domain',
        'Modules\Agency\Domain',
        'Modules\Partner\Domain',
        'Modules\Treasury\Domain',
        'Modules\Trade\Domain',
        'Modules\TradeFinance\Domain',
        'Modules\International\Domain',
        'Modules\Intercompany\Domain',
        'Modules\ControlTower\Domain',
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

arch('external modules do not use Banking VerifyPinAction directly')
    ->expect([
        'Modules\AutoServe',
        'Modules\Crypto',
        'Modules\Finance',
        'Modules\Mall',
        'Modules\Resto',
        'Modules\Store',
        'Modules\Logistics',
    ])
    ->not->toUse('Modules\Banking\Application\Actions\VerifyPinAction');

arch('Resto Domain does not import Mall')
    ->expect('Modules\Resto\Domain')
    ->not->toUse('Modules\Mall');

arch('Mall Domain does not import Resto')
    ->expect('Modules\Mall\Domain')
    ->not->toUse('Modules\Resto');

arch('Logistics Domain does not import other business domains')
    ->expect('Modules\Logistics\Domain')
    ->not->toUse([
        'Modules\Resto\Domain',
        'Modules\Mall\Domain',
        'Modules\AutoServe\Domain',
        'Modules\Store\Domain',
    ]);

arch('other business domains do not import Logistics Domain')
    ->expect([
        'Modules\AutoServe',
        'Modules\Store',
        'Modules\Resto',
        'Modules\Mall',
    ])
    ->not->toUse('Modules\Logistics\Domain');

arch('Party Domain does not import business domain models')
    ->expect('Modules\Party\Domain')
    ->not->toUse([
        'Modules\Logistics\Domain',
        'Modules\Mall\Domain',
        'Modules\Resto\Domain',
        'Modules\Store\Domain',
        'Modules\AutoServe\Domain',
        'Modules\Crypto\Domain',
        'Modules\Banking\Domain',
    ]);

arch('Contract Domain does not import other business domain models')
    ->expect('Modules\Contract\Domain')
    ->not->toUse([
        'Modules\Logistics\Domain',
        'Modules\Mall\Domain',
        'Modules\Resto\Domain',
        'Modules\Store\Domain',
        'Modules\AutoServe\Domain',
        'Modules\Crypto\Domain',
        'Modules\Banking\Domain',
    ]);

arch('Contract does not import Logistics or Mall Domain directly')
    ->expect('Modules\Contract')
    ->not->toUse([
        'Modules\Logistics\Domain',
        'Modules\Mall\Domain',
        'Modules\Resto\Domain',
    ]);
