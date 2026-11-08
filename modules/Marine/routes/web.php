<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Marine\UI\Http\Controllers\AquacultureBatchStockingController;
use Modules\Marine\UI\Http\Controllers\AquacultureExportController;
use Modules\Marine\UI\Http\Controllers\ExportShipmentDispatchController;
use Modules\Marine\UI\Http\Controllers\MarineController;

Route::middleware(['web', 'auth', 'role:admin,farmer,manager,officer'])->prefix('marine')->group(function () {
    Route::get('/', [MarineController::class, 'index'])->name('marine.index');
    Route::get('/ponds/{id}', [MarineController::class, 'showPond'])->name('marine.ponds.show');

    // Fase 171: Akuakultur Ekspor, Ketelusuran Seafood & Blue ESG
    Route::get('/aquaculture', [AquacultureExportController::class, 'index'])->name('marine.aquaculture.index');
    Route::get('/traceability', [AquacultureExportController::class, 'traceability'])->name('marine.traceability.index');
    Route::get('/escrow', [AquacultureExportController::class, 'escrow'])->name('marine.escrow.index');
    Route::get('/blue-carbon', [AquacultureExportController::class, 'blueCarbon'])->name('marine.blue_carbon.index');

    // Manajemen Batch & Telemetri
    Route::post('/aquaculture/batches', [AquacultureBatchStockingController::class, 'store'])->name('marine.aquaculture.batches.store');
    Route::post('/aquaculture/batches/{id}/sampling', [AquacultureBatchStockingController::class, 'recordSampling'])->name('marine.aquaculture.batches.sampling');
    Route::post('/aquaculture/batches/{id}/telemetry', [AquacultureBatchStockingController::class, 'recordTelemetry'])->name('marine.aquaculture.batches.telemetry');
    Route::get('/aquaculture/batches/{id}/fcr', [AquacultureBatchStockingController::class, 'evaluateFcr'])->name('marine.aquaculture.batches.fcr');

    // Dispatch & Logistik Ekspor
    Route::post('/shipments/{id}/health-certificate', [ExportShipmentDispatchController::class, 'issueHealthCertificate'])->name('marine.shipments.health_cert');
    Route::post('/shipments/{id}/coo', [ExportShipmentDispatchController::class, 'issueCertificateOfOrigin'])->name('marine.shipments.coo');
    Route::post('/shipments/{id}/cold-chain-telemetry', [ExportShipmentDispatchController::class, 'recordColdChainTelemetry'])->name('marine.shipments.telemetry');
    Route::post('/shipments/{id}/dispatch', [ExportShipmentDispatchController::class, 'dispatchShipment'])->name('marine.shipments.dispatch');
    Route::post('/disputes/arbitrate', [ExportShipmentDispatchController::class, 'arbitrateDispute'])->name('marine.disputes.arbitrate');

    // Fase 171: Akuakultur Ekspor, Ketelusuran Seafood & Blue ESG
    Route::get('/aquaculture', [AquacultureExportController::class, 'index'])->name('marine.aquaculture.index');
    Route::get('/traceability', [AquacultureExportController::class, 'traceability'])->name('marine.traceability.index');
    Route::get('/escrow', [AquacultureExportController::class, 'escrow'])->name('marine.escrow.index');
    Route::get('/blue-carbon', [AquacultureExportController::class, 'blueCarbon'])->name('marine.blue_carbon.index');

    // Manajemen Batch & Telemetri
    Route::post('/aquaculture/batches', [AquacultureBatchStockingController::class, 'store'])->name('marine.aquaculture.batches.store');
    Route::post('/aquaculture/batches/{id}/sampling', [AquacultureBatchStockingController::class, 'recordSampling'])->name('marine.aquaculture.batches.sampling');
    Route::post('/aquaculture/batches/{id}/telemetry', [AquacultureBatchStockingController::class, 'recordTelemetry'])->name('marine.aquaculture.batches.telemetry');
    Route::get('/aquaculture/batches/{id}/fcr', [AquacultureBatchStockingController::class, 'evaluateFcr'])->name('marine.aquaculture.batches.fcr');

    // Dispatch & Logistik Ekspor
    Route::post('/shipments/{id}/health-certificate', [ExportShipmentDispatchController::class, 'issueHealthCertificate'])->name('marine.shipments.health_cert');
    Route::post('/shipments/{id}/coo', [ExportShipmentDispatchController::class, 'issueCertificateOfOrigin'])->name('marine.shipments.coo');
    Route::post('/shipments/{id}/cold-chain-telemetry', [ExportShipmentDispatchController::class, 'recordColdChainTelemetry'])->name('marine.shipments.telemetry');
    Route::post('/shipments/{id}/dispatch', [ExportShipmentDispatchController::class, 'dispatchShipment'])->name('marine.shipments.dispatch');
    Route::post('/disputes/arbitrate', [ExportShipmentDispatchController::class, 'arbitrateDispute'])->name('marine.disputes.arbitrate');
});
