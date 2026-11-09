<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Platform\Http\Controllers\CorrelationTraceController;
use Modules\Platform\Http\Controllers\DevPortalDashboardController;
use Modules\Platform\Http\Controllers\PhaseMetricsController;
use Modules\Platform\Http\Controllers\QualityMetricsApiController;
use Modules\Platform\Http\Controllers\SandboxController;
use Modules\Platform\Http\Controllers\SandboxLifecycleApiController;
use Modules\Platform\Http\Controllers\SloMonitoringApiController;
use Modules\Platform\Http\Controllers\SloTrackerController;

Route::prefix('platform')->middleware(['web', 'auth'])->name('platform.')->group(function (): void {
    Route::get('/dev', [DevPortalDashboardController::class, 'index'])->name('dev.dashboard');

    Route::prefix('dev')->name('dev.')->group(function (): void {
        Route::get('/sandboxes', [SandboxController::class, 'index'])->name('sandboxes.index');
        Route::get('/sandboxes/{sandbox}', [SandboxController::class, 'show'])->name('sandboxes.show');

        Route::get('/slos', [SloTrackerController::class, 'index'])->name('slos.index');
        Route::get('/metrics', [PhaseMetricsController::class, 'index'])->name('metrics.index');
        Route::get('/trace/{correlationId}', [CorrelationTraceController::class, 'show'])->name('trace.show');

        // Tooling & Telemetry APIs
        Route::post('/api/sandboxes', [SandboxLifecycleApiController::class, 'store'])->name('api.sandboxes.store');
        Route::post('/api/sandboxes/{sandbox}/provision', [SandboxLifecycleApiController::class, 'provision'])->name('api.sandboxes.provision');
        Route::delete('/api/sandboxes/{sandbox}', [SandboxLifecycleApiController::class, 'destroy'])->name('api.sandboxes.destroy');

        Route::get('/api/slos', [SloMonitoringApiController::class, 'index'])->name('api.slos.index');
        Route::get('/api/metrics/prometheus', [SloMonitoringApiController::class, 'metrics'])->name('api.metrics.prometheus');
        Route::get('/api/metrics/phase/{phaseId}', [QualityMetricsApiController::class, 'show'])->name('api.metrics.phase');
    });
});
