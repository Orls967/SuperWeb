<?php

declare(strict_types=1);

$moduleDirs = glob(dirname(__DIR__, 2).'/modules/*') ?: [];
$discoveredControllers = [];
$discoveredDomains = [];

foreach ($moduleDirs as $d) {
    if (! is_dir($d)) {
        continue;
    }
    $mod = basename($d);
    if (is_dir($d.'/Http/Controllers')) {
        $discoveredControllers[] = "Modules\\{$mod}\\Http\\Controllers";
    }
    if (is_dir($d.'/Domain')) {
        $discoveredDomains[] = "Modules\\{$mod}\\Domain";
    }
}

sort($discoveredControllers);
sort($discoveredDomains);

arch('controllers do not use DB facade directly')
    ->expect($discoveredControllers)
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('domain does not depend on Http')
    ->expect($discoveredDomains)
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

arch('controllers do not access SimClockInterface or models directly')
    ->expect([
        'Modules\AutoServe\Http\Controllers',
        'Modules\Banking\Http\Controllers',
        'Modules\Store\Http\Controllers',
    ])
    ->not->toUse([
        'Modules\Core\Contracts\SimClockInterface',
        'Modules\Core\Domain\Models\SimRun',
    ]);

arch('DigitalTwin state does not directly touch LedgerAccount or balance')
    ->expect('Modules\Core\Application\Services\DigitalTwinService')
    ->not->toUse([
        'Modules\Banking\Domain\Models\LedgerAccount',
        'Modules\Banking\Domain\Models\LedgerEntry',
    ]);
