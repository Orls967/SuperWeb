<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Telematics\Application\Actions\ClearVehicleGroundingAction;
use Modules\Telematics\Http\Controllers\TelematicsController;
use Modules\Telematics\Http\Controllers\TelematicsFleetOperationsController;

Route::middleware(['web', 'auth', 'role:admin,telematics_admin,fleet_manager'])->prefix('telematics')->group(function () {
    Route::get('/', [TelematicsController::class, 'index'])->name('telematics.index');
    Route::post('/devices/{device}/ingest', [TelematicsController::class, 'ingest'])->name('telematics.devices.ingest');

    // Operations & Diagnostic Deepening Routes
    Route::prefix('operations')->name('telematics.operations.')->group(function () {
        Route::get('/baselines', [TelematicsFleetOperationsController::class, 'baselines'])->name('baselines');
        Route::get('/dtc-alerts', [TelematicsFleetOperationsController::class, 'dtcAlerts'])->name('dtc-alerts');
        Route::get('/driver-scores', [TelematicsFleetOperationsController::class, 'driverScores'])->name('driver-scores');
        Route::get('/emergency-dispatches', [TelematicsFleetOperationsController::class, 'emergencyDispatches'])->name('emergency-dispatches');
        Route::post('/evaluate-dtc', [TelematicsFleetOperationsController::class, 'evaluateDtc'])->name('evaluate-dtc');
        Route::post('/dispatch-tow', [TelematicsFleetOperationsController::class, 'dispatchTow'])->name('dispatch-tow');
    });
});

Route::middleware(['web', 'auth', 'role:admin,mechanic'])->post('/api/oto/telematics/clear-grounding', function (Request $request, ClearVehicleGroundingAction $action) {
    $user = $request->user();
    if (! $user->hasAnyRbacRole(['mechanic', 'admin'])) {
        abort(403, 'Hanya mekanik berwenang yang dapat menghapus status grounding');
    }

    $vehicleId = (int) $request->input('vehicle_id');
    $reason = (string) $request->input('reason_notes', '');
    $action->execute($vehicleId, $user->id, $reason);

    return response()->json(['status' => 'GROUNDING_CLEARED']);
});
