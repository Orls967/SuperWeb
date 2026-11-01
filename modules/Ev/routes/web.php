<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Ev\Domain\Models\EvSession;
use Modules\Ev\Http\Controllers\EvController;
use Modules\Ev\Http\Controllers\EvSmartGridController;

Route::middleware(['web', 'auth', 'role:admin,ev_admin,station_operator,driver'])->prefix('ev')->group(function () {
    Route::get('/', [EvController::class, 'index'])->name('ev.index');
    Route::post('/chargers/{charger}/reserve', [EvController::class, 'reserve'])->name('ev.chargers.reserve');
    Route::post('/chargers/{charger}/check-in', [EvController::class, 'checkIn'])->name('ev.chargers.checkin');

    Route::middleware(['role:admin,ev_admin,station_operator'])->group(function () {
        Route::post('/sessions/{session}/complete', [EvController::class, 'complete'])->name('ev.sessions.complete');
        Route::post('/stations/{station}/load-balance', [EvController::class, 'loadBalance'])->name('ev.stations.loadbalance');
    });

    // Smart Grid & Battery Passport Deepening Routes
    Route::prefix('smart-grid')->name('ev.smart-grid.')->group(function () {
        Route::get('/ocpp-sessions', [EvSmartGridController::class, 'ocppSessions'])->name('ocpp-sessions');
        Route::get('/tou-tariffs', [EvSmartGridController::class, 'touTariffs'])->name('tou-tariffs');
        Route::get('/v2g-feedin', [EvSmartGridController::class, 'v2gFeedIn'])->name('v2g-feedin');
        Route::get('/battery-passport', [EvSmartGridController::class, 'batteryPassport'])->name('battery-passport');
        Route::post('/start-ocpp', [EvSmartGridController::class, 'startOcpp'])->name('start-ocpp');
        Route::post('/settle-tou', [EvSmartGridController::class, 'settleTou'])->name('settle-tou');
        Route::post('/discharge-v2g', [EvSmartGridController::class, 'dischargeV2g'])->name('discharge-v2g');
    });
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/api/oto/sessions/{session}', function (Request $request, EvSession $session) {
        $user = $request->user();
        if ($session->user_id !== $user->id && ! $user->hasAnyRbacRole(['ev_operator', 'admin', 'fleet_manager'])) {
            abort(403, 'Akses ditolak: Anda bukan pemilik sesi pengisian ini.');
        }

        return response()->json(['session' => $session]);
    });

    Route::get('/oto/charging', function (Request $request) {
        $sessions = EvSession::where('user_id', $request->user()->id)->get();

        return view('ev::charging', compact('sessions'));
    });
});
