<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// 67.5: Kernel waktu (SimClockService / SimClockInterface) tidak boleh diakses langsung dari business controllers luar Core
arch('non-core controllers do not access SimClockService directly')
    ->expect([
        'Modules\AutoServe\Http\Controllers',
        'Modules\AutoDex\Http\Controllers',
        'Modules\Banking\Http\Controllers',
        'Modules\Crypto\Http\Controllers',
        'Modules\Mall\Http\Controllers',
        'Modules\Resto\Http\Controllers',
        'Modules\Logistics\Http\Controllers',
        'Modules\Store\Http\Controllers',
        'Modules\Telematics\Http\Controllers',
    ])
    ->not->toUse([
        'Modules\Core\Application\Services\SimClockService',
        'Modules\Core\Contracts\SimClockInterface',
    ]);

// 67.5: Modul non-core tidak boleh mengimpor langsung model SimTwinState atau SimEventSpine di controller
arch('business controllers do not directly bind to SimTwinState or SimEventSpine models')
    ->expect([
        'Modules\AutoServe\Http\Controllers',
        'Modules\AutoDex\Http\Controllers',
        'Modules\Banking\Http\Controllers',
        'Modules\Crypto\Http\Controllers',
        'Modules\Mall\Http\Controllers',
        'Modules\Resto\Http\Controllers',
        'Modules\Logistics\Http\Controllers',
        'Modules\Store\Http\Controllers',
    ])
    ->not->toUse([
        'Modules\Core\Domain\Models\SimTwinState',
        'Modules\Core\Domain\Models\SimEventSpine',
    ]);

// 67.5: Schema check - sim_twin_states tidak boleh memiliki kolom saldo / stok
test('sim_twin_states does not have balance or stock columns as source of truth', function () {
    $columns = Schema::getColumnListing('sim_twin_states');

    expect($columns)->not->toContain('balance')
        ->not->toContain('amount')
        ->not->toContain('stock')
        ->not->toContain('inventory_qty')
        ->not->toContain('current_balance');
});
