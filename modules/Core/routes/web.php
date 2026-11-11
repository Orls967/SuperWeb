<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\ActivityFeedController;
use Modules\Core\Http\Controllers\Admin\Spine\SpineConsumerController;
use Modules\Core\Http\Controllers\Admin\Spine\SpineDashboardController;
use Modules\Core\Http\Controllers\Admin\Spine\SpineDlqController;
use Modules\Core\Http\Controllers\Admin\Spine\SpineGapController;
use Modules\Core\Http\Controllers\Admin\Spine\SpineSchemaController;
use Modules\Core\Http\Controllers\Ai\AiAnomalyController;
use Modules\Core\Http\Controllers\Ai\AiQuarantineController;
use Modules\Core\Http\Controllers\Ai\AiStockRecommendationController;
use Modules\Core\Http\Controllers\AiGovernanceController;
use Modules\Core\Http\Controllers\ApiAdminDashboardController;
use Modules\Core\Http\Controllers\ApiV2BulkController;
use Modules\Core\Http\Controllers\AuditLogController;
use Modules\Core\Http\Controllers\Boundaries\ModuleMapController;
use Modules\Core\Http\Controllers\Boundaries\ModuleMapExportController;
use Modules\Core\Http\Controllers\Boundaries\ViolationController;
use Modules\Core\Http\Controllers\Boundaries\WaiverController;
use Modules\Core\Http\Controllers\DesignSystem\DesignSystemCatalogController;
use Modules\Core\Http\Controllers\DesignSystem\DesignSystemReleaseController;
use Modules\Core\Http\Controllers\DesignSystem\ThemePreferenceController;
use Modules\Core\Http\Controllers\DesignSystem\UxResearchDashboardController;
use Modules\Core\Http\Controllers\GlobalSearchApiController;
use Modules\Core\Http\Controllers\HealthCheckController;
use Modules\Core\Http\Controllers\MobileGovernanceController;
use Modules\Core\Http\Controllers\NotificationController;
use Modules\Core\Http\Controllers\PassportController;
use Modules\Core\Http\Controllers\RbacController;
use Modules\Core\Http\Controllers\ResilienceGovernanceController;
use Modules\Core\Http\Controllers\SearchAnalyticsController;
use Modules\Core\Http\Controllers\SimulationDiffController;
use Modules\Core\Http\Controllers\SimulationExportController;
use Modules\Core\Http\Controllers\SimulationGovernanceController;
use Modules\Core\Http\Controllers\SimulationRunController;
use Modules\Core\Http\Controllers\SimulationTwinController;
use Modules\Core\Http\Controllers\TaskInboxController;

Route::middleware(['web'])->group(function () {
    Route::get('/passport/{uuid}', [PassportController::class, 'show'])->name('passport.show');
});

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');
    Route::post('/notifications/mark-all-read-json', [NotificationController::class, 'markAllReadJson'])->name('notifications.markAllReadJson');

    // Activity Feed
    Route::get('/activity', [ActivityFeedController::class, 'index'])->name('activity.index');

    // AI & Predictive Governance (Fase 64, D64-1..D64-4)
    Route::prefix('ai')->group(function () {
        Route::middleware('role:admin,risk_officer')->group(function () {
            Route::get('/anomalies', [AiAnomalyController::class, 'index'])->name('core.ai.anomalies.index');
            Route::post('/anomalies', [AiAnomalyController::class, 'screen'])->name('core.ai.anomalies.screen');
        });

        Route::middleware('role:admin,risk_officer,risk_manager')->group(function () {
            Route::get('/quarantine', [AiQuarantineController::class, 'index'])->name('core.ai.quarantine.index');
        });
        Route::middleware('role:admin,risk_manager')->group(function () {
            Route::post('/quarantine/{quarantine}/release', [AiQuarantineController::class, 'release'])->name('core.ai.quarantine.release');
        });

        Route::middleware('role:admin,planner')->group(function () {
            Route::get('/stock-recommendations', [AiStockRecommendationController::class, 'index'])->name('core.ai.stock-recommendations.index');
            Route::post('/stock-recommendations', [AiStockRecommendationController::class, 'store'])->name('core.ai.stock-recommendations.store');
        });
        Route::middleware('role:admin,planner_manager')->group(function () {
            Route::post('/stock-recommendations/{recommendation}/approve', [AiStockRecommendationController::class, 'approve'])->name('core.ai.stock-recommendations.approve');
        });
    });

    // Admin System Health & Observability
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/health', [HealthCheckController::class, 'index'])->name('admin.health.index');
        Route::post('/health/run', [HealthCheckController::class, 'run'])->name('admin.health.run');

        // RBAC Management
        Route::get('/rbac', [RbacController::class, 'index'])->name('admin.rbac.index');
        Route::get('/rbac/role/{role}', [RbacController::class, 'showRole'])->name('admin.rbac.role');
        Route::post('/rbac/role/{role}/toggle-permission', [RbacController::class, 'togglePermission'])->name('admin.rbac.toggle-permission');
        Route::post('/rbac/assign', [RbacController::class, 'assignRole'])->name('admin.rbac.assign');
        Route::post('/rbac/revoke', [RbacController::class, 'revokeRole'])->name('admin.rbac.revoke');
        Route::get('/rbac/user/{user}', [RbacController::class, 'showUser'])->name('admin.rbac.user');

        // Audit Trail Generik
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
        Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('admin.audit-logs.show');

        // AI Governance & Predictive Analytics (Fase 64)
        Route::get('/ai-governance', [AiGovernanceController::class, 'index'])->name('admin.ai.index');
        Route::post('/ai-governance/calculate-pricing', [AiGovernanceController::class, 'calculatePricing'])->name('admin.ai.calculate-pricing');
        Route::post('/ai-governance/screen-anomaly', [AiGovernanceController::class, 'screenAnomaly'])->name('admin.ai.screen-anomaly');
        Route::post('/ai-governance/generate-stock-recommendation', [AiGovernanceController::class, 'generateStockRecommendation'])->name('admin.ai.generate-stock-recommendation');
        Route::post('/ai-governance/quarantines/{quarantine}/release', [AiGovernanceController::class, 'releaseQuarantine'])->name('admin.ai.release-quarantine');

        // Enterprise Mobile Suite & Offline-First (Fase 65)
        Route::get('/mobile-governance', [MobileGovernanceController::class, 'index'])->name('admin.mobile.index');
        Route::post('/mobile-governance/sync-batch', [MobileGovernanceController::class, 'syncBatch'])->name('admin.mobile.sync-batch');
        Route::post('/mobile-governance/submit-epod', [MobileGovernanceController::class, 'submitEpod'])->name('admin.mobile.submit-epod');
        Route::post('/mobile-governance/record-action', [MobileGovernanceController::class, 'recordAction'])->name('admin.mobile.record-action');
        Route::post('/mobile-governance/devices/{device}/revoke', [MobileGovernanceController::class, 'revokeDevice'])->name('admin.mobile.revoke-device');

        // Multi-Region Resilience, DR & Data Sovereignty (Fase 66)
        Route::get('/resilience-governance', [ResilienceGovernanceController::class, 'index'])->name('admin.resilience.index');
        Route::post('/resilience-governance/execute-drill', [ResilienceGovernanceController::class, 'executeDrill'])->name('admin.resilience.execute-drill');
        Route::post('/resilience-governance/rotate-kms', [ResilienceGovernanceController::class, 'rotateKms'])->name('admin.resilience.rotate-kms');
        Route::post('/resilience-governance/check-residency', [ResilienceGovernanceController::class, 'checkResidency'])->name('admin.resilience.check-residency');

        // Simulation Kernel, Event Spine & Digital Twin Bus (Fase 67)
        Route::get('/simulation-governance', [SimulationGovernanceController::class, 'index'])->name('admin.simulation.index');
        Route::post('/simulation-governance/advance', [SimulationGovernanceController::class, 'advance'])->name('admin.simulation.advance');
        Route::post('/simulation-governance/publish-spine', [SimulationGovernanceController::class, 'publishSpine'])->name('admin.simulation.publish-spine');
        Route::post('/simulation-governance/record-twin', [SimulationGovernanceController::class, 'recordTwin'])->name('admin.simulation.record-twin');
        Route::post('/simulation-governance/scale-provision', [SimulationGovernanceController::class, 'scaleProvision'])->name('admin.simulation.scale-provision');

        // Design System & UX Governance (Fase 240)
        Route::prefix('design-system')->group(function () {
            Route::get('/releases', [DesignSystemReleaseController::class, 'index'])->name('admin.design-system.releases.index');
            Route::get('/releases/create', [DesignSystemReleaseController::class, 'create'])->name('admin.design-system.releases.create');
            Route::post('/releases', [DesignSystemReleaseController::class, 'store'])->name('admin.design-system.releases.store');
            Route::post('/releases/{name}/transition', [DesignSystemReleaseController::class, 'transition'])->name('admin.design-system.releases.transition');
            Route::get('/research', [UxResearchDashboardController::class, 'index'])->name('admin.design-system.research.index');
            Route::get('/research/export-csv', [UxResearchDashboardController::class, 'exportCsv'])->name('admin.design-system.research.export-csv');
        });
    });

    // Design System Management untuk Role design_system_owner & admin
    Route::middleware('role:design_system_owner,admin')->prefix('design-system')->group(function () {
        Route::get('/releases', [DesignSystemReleaseController::class, 'index'])->name('design-system.releases.index');
        Route::get('/releases/create', [DesignSystemReleaseController::class, 'create'])->name('design-system.releases.create');
        Route::post('/releases', [DesignSystemReleaseController::class, 'store'])->name('design-system.releases.store');
        Route::post('/releases/{name}/transition', [DesignSystemReleaseController::class, 'transition'])->name('design-system.releases.transition');
        Route::get('/research', [UxResearchDashboardController::class, 'index'])->name('design-system.research.index');
        Route::get('/research/export-csv', [UxResearchDashboardController::class, 'exportCsv'])->name('design-system.research.export-csv');
    });

    // Design System Catalog & Theme Preference (Dapat diakses admin, owner, dan operator)
    Route::middleware('role:admin,design_system_owner,operator')->group(function () {
        Route::get('/design-system', [DesignSystemCatalogController::class, 'index'])->name('design-system.index');
        Route::get('/design-system/{name}', [DesignSystemCatalogController::class, 'show'])->name('design-system.show');
        Route::post('/user/theme-preference', [ThemePreferenceController::class, 'update'])->name('user.theme-preference.update');
    });

    // Kotak Masuk "Tugas Saya" (§19.1) — Fase 194
    Route::middleware('role:admin,design_system_owner,manager,operator')->prefix('inbox')->group(function () {
        Route::get('/', [TaskInboxController::class, 'index'])->name('core.task-inbox.index');
        Route::get('/analytics', [TaskInboxController::class, 'analytics'])->name('core.task-inbox.analytics');
        Route::post('/batch', [TaskInboxController::class, 'batch'])->name('core.task-inbox.batch');
        Route::get('/{id}', [TaskInboxController::class, 'show'])->name('core.task-inbox.show');
        Route::post('/{id}/action', [TaskInboxController::class, 'action'])->name('core.task-inbox.action');
    });

    // Analitik Pencarian & Reindexing — Fase 194 (Admin Only)
    Route::middleware('role:admin')->prefix('admin/search')->group(function () {
        Route::get('/analytics', [SearchAnalyticsController::class, 'analytics'])->name('core.search.analytics');
        Route::get('/diagnostics', [SearchAnalyticsController::class, 'diagnostics'])->name('core.search.diagnostics');
        Route::post('/reindex', [SearchAnalyticsController::class, 'triggerReindex'])->name('core.search.reindex');
    });

    // API Pencarian Global BM25 — Fase 194
    Route::middleware('role:admin,design_system_owner,manager,operator')->get('/api/global-search-v2', GlobalSearchApiController::class)->name('core.api.global-search');

    // Universal Event Spine & DLQ Console — Fase R8
    Route::middleware('role:spine_operator,admin,superadmin')->prefix('admin/spine')->group(function () {
        Route::get('/', SpineDashboardController::class)->name('admin.spine.index');

        // DLQ
        Route::get('/dlq', [SpineDlqController::class, 'index'])->name('admin.spine.dlq.index');
        Route::get('/dlq/export', [SpineDlqController::class, 'exportCsv'])->name('admin.spine.dlq.export');
        Route::post('/dlq/replay', [SpineDlqController::class, 'replay'])->name('admin.spine.dlq.replay');
        Route::post('/dlq/replay-batch', [SpineDlqController::class, 'replayBatch'])->name('admin.spine.dlq.replay-batch');
        Route::post('/dlq/discard', [SpineDlqController::class, 'discard'])->name('admin.spine.dlq.discard');
        Route::post('/dlq/discard-batch', [SpineDlqController::class, 'discardBatch'])->name('admin.spine.dlq.discard-batch');

        // Consumers & Lag
        Route::get('/consumers', [SpineConsumerController::class, 'index'])->name('admin.spine.consumers.index');
        Route::post('/consumers/replay', [SpineConsumerController::class, 'replayRange'])->name('admin.spine.consumers.replay');
        Route::get('/consumers/export', [SpineConsumerController::class, 'exportCsv'])->name('admin.spine.consumers.export');

        // Gaps
        Route::get('/gaps', [SpineGapController::class, 'index'])->name('admin.spine.gaps.index');
        Route::post('/gaps/scan', [SpineGapController::class, 'scan'])->name('admin.spine.gaps.scan');
        Route::post('/gaps/resolve', [SpineGapController::class, 'resolve'])->name('admin.spine.gaps.resolve');
        Route::get('/gaps/export', [SpineGapController::class, 'exportCsv'])->name('admin.spine.gaps.export');
    });

    // Schemas Console (Spine Operator + Auditor) — Fase R8
    Route::middleware('role:spine_operator,auditor,admin,superadmin')->prefix('admin/spine/schemas')->group(function () {
        Route::get('/', [SpineSchemaController::class, 'index'])->name('admin.spine.schemas.index');
        Route::get('/export', [SpineSchemaController::class, 'exportCsv'])->name('admin.spine.schemas.export');
    });

    // Simulation Operator Console & Digital Twin Bus — Fase 67 & R8
    Route::middleware('role:sim_operator,admin,superadmin')->prefix('admin/simulation')->group(function () {
        Route::get('/', [SimulationRunController::class, 'index'])->name('admin.simulation.runs.index');
        Route::post('/', [SimulationRunController::class, 'store'])->name('admin.simulation.runs.store');
        Route::get('/diff', [SimulationDiffController::class, 'index'])->name('admin.simulation.diff');
        Route::get('/twins', [SimulationTwinController::class, 'index'])->name('admin.simulation.twins.index');
        Route::get('/twins/{type}/{id}', [SimulationTwinController::class, 'show'])->name('admin.simulation.twins.show');
        Route::get('/export/{type}', [SimulationExportController::class, 'export'])->name('admin.simulation.export');
        Route::get('/{run}', [SimulationRunController::class, 'show'])->name('admin.simulation.runs.show');
        Route::post('/{run}/pause', [SimulationRunController::class, 'pause'])->name('admin.simulation.runs.pause');
        Route::post('/{run}/resume', [SimulationRunController::class, 'resume'])->name('admin.simulation.runs.resume');
    });

    // Modular Boundaries & Architecture Governance — Fase R4
    Route::middleware('role:admin,superadmin,platform_architect')->prefix('admin/boundaries')->group(function () {
        Route::get('/map', [ModuleMapController::class, 'index'])->name('admin.boundaries.map');
        Route::get('/violations', [ViolationController::class, 'index'])->name('admin.boundaries.violations');
        Route::get('/violations/{id}', [ViolationController::class, 'show'])->name('admin.boundaries.violations.show');

        // Exports
        Route::get('/export/violations', [ModuleMapExportController::class, 'exportViolations'])->name('admin.boundaries.export.violations');
        Route::get('/export/waivers', [ModuleMapExportController::class, 'exportWaivers'])->name('admin.boundaries.export.waivers');
        Route::get('/export/coverage', [ModuleMapExportController::class, 'exportCoverage'])->name('admin.boundaries.export.coverage');
        Route::get('/export/table-ownership', [ModuleMapExportController::class, 'exportTableOwnership'])->name('admin.boundaries.export.table-ownership');
    });

    Route::middleware('role:admin,superadmin,platform_architect,auditor')->prefix('admin/boundaries')->group(function () {
        Route::get('/waivers', [WaiverController::class, 'index'])->name('admin.boundaries.waivers');
        Route::post('/waivers/{id}/extend', [WaiverController::class, 'extend'])->name('admin.boundaries.waivers.extend');
    });

    // Canonical Architecture Routes per W8-P4 Specification (T-10 / R4)
    Route::middleware('role:admin,superadmin,platform_architect')->prefix('admin/architecture')->group(function () {
        Route::get('/modules', [ModuleMapController::class, 'index'])->name('admin.architecture.modules');
        Route::get('/violations', [ViolationController::class, 'index'])->name('admin.architecture.violations');
        Route::get('/violations/{id}', [ViolationController::class, 'show'])->name('admin.architecture.violations.show');
    });

    Route::middleware('role:admin,superadmin,platform_architect,auditor')->prefix('admin/architecture')->group(function () {
        Route::get('/waivers', [WaiverController::class, 'index'])->name('admin.architecture.waivers');
        Route::post('/waivers/{id}/extend', [WaiverController::class, 'extend'])->name('admin.architecture.waivers.extend');
    });

    // API v2 & Multi-Tenancy Management (Fase 55)
    Route::middleware('role:admin,api_admin,superadmin')->group(function () {
        Route::get('/admin/api/clients', [ApiAdminDashboardController::class, 'clients'])->name('admin.api.clients');
        Route::get('/admin/api/edi', [ApiAdminDashboardController::class, 'edi'])->name('admin.api.edi');
        Route::get('/admin/api/imports', [ApiAdminDashboardController::class, 'imports'])->name('admin.api.imports');
        Route::get('/admin/api/webhooks', [ApiAdminDashboardController::class, 'webhooks'])->name('admin.api.webhooks');
        Route::get('/admin/api/exports/download', [ApiV2BulkController::class, 'downloadExport'])->name('admin.api.exports.download');
    });

    Route::middleware('role:admin,api_admin,auditor,superadmin')->group(function () {
        Route::get('/admin/api/usage', [ApiAdminDashboardController::class, 'usage'])->name('admin.api.usage');
    });

    Route::middleware('role:admin,api_admin,auditor,platform_architect,superadmin')->group(function () {
        Route::get('/admin/api/retention', [ApiAdminDashboardController::class, 'retention'])->name('admin.api.retention');
    });

    Route::middleware('role:admin,api_admin,platform_architect,superadmin')->group(function () {
        Route::get('/admin/tenancy', [ApiAdminDashboardController::class, 'tenancy'])->name('admin.tenancy.index');
    });

    Route::middleware('role:admin,api_admin,platform_architect,security_officer,superadmin')->group(function () {
        Route::get('/admin/tenancy/coverage', [ApiAdminDashboardController::class, 'tenancy'])->name('admin.tenancy.coverage');
    });

    Route::middleware('role:admin,api_admin,developer,superadmin')->group(function () {
        Route::get('/developer', [ApiAdminDashboardController::class, 'developer'])->name('developer.index');
        Route::get('/admin/developer', [ApiAdminDashboardController::class, 'developer'])->name('admin.developer.index');
    });
});
