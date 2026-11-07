<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Mining\Http\Controllers\MiningContractorPortalController;
use Modules\Mining\Http\Controllers\MiningController;
use Modules\Mining\Http\Controllers\MiningDispatchController;
use Modules\Mining\Http\Controllers\MiningEquipmentController;
use Modules\Mining\Http\Controllers\MiningExtractionDispatchController;
use Modules\Mining\Http\Controllers\MiningFuelController;
use Modules\Mining\Http\Controllers\MiningGradeControlController;
use Modules\Mining\Http\Controllers\MiningHseController;
use Modules\Mining\Http\Controllers\MiningOfftakeController;
use Modules\Mining\Http\Controllers\MiningOfftakerPortalController;
use Modules\Mining\Http\Controllers\MiningPlanController;
use Modules\Mining\Http\Controllers\MiningReclamationController;
use Modules\Mining\Http\Controllers\MiningReportController;
use Modules\Mining\Http\Controllers\MiningRoyaltyController;
use Modules\Mining\Http\Controllers\MiningRoyaltyReclamationSafetyController;
use Modules\Mining\Http\Controllers\MiningSmelterAndTradingController;
use Modules\Mining\Http\Controllers\MiningUndergroundAndMarineController;
use Modules\Mining\Http\Controllers\MiningWeighbridgeSmelterController;

Route::middleware(['web', 'auth'])->prefix('mining')->group(function () {
    // Weighbridge operator route (mobile 375px)
    Route::middleware(['role:weighbridge_operator,admin,mine_manager'])
        ->get('/weighbridge', function () {
            return view('mining::weighbridge');
        })->name('mining.weighbridge');

    // Metallurgist & Grade Control route (M5)
    Route::middleware(['role:metallurgist,admin,mine_manager'])->group(function () {
        Route::get('/grade', [MiningGradeControlController::class, 'index'])->name('mining.grade');
        Route::post('/grade/blend', [MiningGradeControlController::class, 'blend'])->name('mining.grade.blend');
        Route::post('/grade/depletion', [MiningGradeControlController::class, 'trackDepletion'])->name('mining.grade.depletion');
    });

    // Royalty Officer route (M4)
    Route::middleware(['role:royalty_officer,admin,mine_manager'])->group(function () {
        Route::get('/royalty', [MiningRoyaltyController::class, 'index'])->name('mining.royalty');
        Route::post('/royalty/adjust-price', [MiningRoyaltyController::class, 'adjustPrice'])->name('mining.royalty.adjust-price');
    });

    // HSE officer route
    Route::middleware(['role:hse_officer,admin,mine_manager'])->group(function () {
        Route::get('/hse', [MiningHseController::class, 'index'])->name('mining.hse');
        Route::post('/hse/permit', [MiningHseController::class, 'issuePermit'])->name('mining.hse.permit');
        Route::post('/hse/scan-expiry', [MiningHseController::class, 'scanExpiry'])->name('mining.hse.scan-expiry');
        Route::get('/hse/audit-permits', [MiningHseController::class, 'auditPermits'])->name('mining.hse.audit-permits');
        Route::post('/hse/blasting-eval', [MiningHseController::class, 'evaluateBlasting'])->name('mining.hse.blasting-eval');
    });

    // Reclamation route (mine_manager)
    Route::middleware(['role:mine_manager,admin'])->group(function () {
        Route::get('/reclamation', [MiningReclamationController::class, 'index'])->name('mining.reclamation');
        Route::post('/reclamation/schedule', [MiningReclamationController::class, 'schedule'])->name('mining.reclamation.schedule');
        Route::post('/reclamation/unwind', [MiningReclamationController::class, 'unwind'])->name('mining.reclamation.unwind');
    });

    // Offtake Manager route
    Route::middleware(['role:offtake_manager,admin,mine_manager'])->group(function () {
        Route::get('/offtake', [MiningOfftakeController::class, 'index'])->name('mining.offtake');
        Route::post('/offtake/nominate', [MiningOfftakeController::class, 'nominate'])->name('mining.offtake.nominate');
        Route::post('/offtake/settle-provisional', [MiningOfftakeController::class, 'settleProvisional'])->name('mining.offtake.settle-provisional');
        Route::post('/offtake/settle-final', [MiningOfftakeController::class, 'settleFinal'])->name('mining.offtake.settle-final');
    });

    // Mine planning routes (mine_planner, admin)
    Route::middleware(['role:mine_planner,admin'])->group(function () {
        Route::get('/plans', [MiningPlanController::class, 'index'])->name('mining.plans');
        Route::post('/plans', [MiningPlanController::class, 'store'])->name('mining.plans.store');
        Route::post('/plans/{plan}/replan', [MiningPlanController::class, 'replan'])->name('mining.plans.replan');
    });

    // Mine dispatcher routes (dispatcher_mine, admin, ...)
    Route::middleware(['role:dispatcher_mine,admin,mining_admin,site_manager,mine_manager'])->group(function () {
        Route::get('/dispatch', [MiningDispatchController::class, 'index'])->name('mining.dispatch.ui');
        Route::post('/dispatch/assign', [MiningDispatchController::class, 'assign'])->name('mining.dispatch.assign');
        Route::post('/dispatch/override', [MiningDispatchController::class, 'override'])->name('mining.dispatch.override');
    });

    // Equipment fleet management (mine_manager, admin)
    Route::middleware(['role:mine_manager,admin'])->group(function () {
        Route::get('/equipment', [MiningEquipmentController::class, 'index'])->name('mining.equipment');
    });

    // Fuel management & anomaly cases (finance_officer, hse_officer, admin)
    Route::middleware(['role:finance_officer,hse_officer,admin'])->group(function () {
        Route::get('/fuel', [MiningFuelController::class, 'index'])->name('mining.fuel');
        Route::post('/fuel/{case}/resolve', [MiningFuelController::class, 'resolve'])->name('mining.fuel.resolve');
        Route::post('/fuel/dispense', [MiningFuelController::class, 'dispense'])->name('mining.fuel.dispense');
    });

    // Reports export CSV
    Route::middleware(['role:mine_manager,finance_officer,admin'])->group(function () {
        Route::get('/reports/export', [MiningReportController::class, 'export'])->name('mining.reports.export');
    });

    // Mine manager & admin hub
    Route::middleware(['role:mine_manager,admin,mining_admin,site_manager'])
        ->get('/admin', [MiningController::class, 'index'])->name('mining.admin');

    // Finance officer royalties route (legacy alias)
    Route::middleware(['role:finance_officer,admin,mining_admin,site_manager'])
        ->get('/royalties', [MiningRoyaltyReclamationSafetyController::class, 'pnbpRoyalty'])->name('mining.royalties');

    // General Mining hub & legacy routes
    Route::middleware(['role:admin,mining_admin,site_manager'])->group(function () {
        Route::get('/', [MiningController::class, 'index'])->name('mining.index');
        Route::post('/pits/{pit}/dispatch', [MiningController::class, 'dispatch'])->name('mining.dispatch');

        Route::prefix('operations')->name('mining.operations.')->group(function () {
            Route::get('/block-planning', [MiningExtractionDispatchController::class, 'blockPlanning'])->name('block-planning');
            Route::post('/block-planning/plan', [MiningExtractionDispatchController::class, 'planBlock'])->name('block-planning.plan');

            Route::get('/haul-dispatch', [MiningExtractionDispatchController::class, 'haulDispatch'])->name('haul-dispatch');
            Route::post('/haul-dispatch/truck', [MiningExtractionDispatchController::class, 'dispatchTruck'])->name('haul-dispatch.truck');

            Route::get('/weighbridge', [MiningWeighbridgeSmelterController::class, 'weighbridgeTickets'])->name('weighbridge');
            Route::post('/weighbridge/ticket', [MiningWeighbridgeSmelterController::class, 'recordTicket'])->name('weighbridge.ticket');

            Route::get('/grade-reconciliation', [MiningWeighbridgeSmelterController::class, 'oreGradeReconciliation'])->name('grade-reconciliation');
            Route::post('/grade-reconciliation/reconcile', [MiningWeighbridgeSmelterController::class, 'reconcileAssay'])->name('grade-reconciliation.reconcile');

            Route::get('/smelter', [MiningWeighbridgeSmelterController::class, 'smelterRuns'])->name('smelter');
            Route::post('/smelter/run', [MiningWeighbridgeSmelterController::class, 'executeSmelter'])->name('smelter.run');

            Route::get('/pnbp-royalty', [MiningRoyaltyReclamationSafetyController::class, 'pnbpRoyalty'])->name('pnbp-royalty');
            Route::post('/pnbp-royalty/calculate', [MiningRoyaltyReclamationSafetyController::class, 'calcRoyalty'])->name('pnbp-royalty.calculate');

            Route::get('/reclamation', [MiningRoyaltyReclamationSafetyController::class, 'reclamationBonds'])->name('reclamation');
            Route::post('/reclamation/bond', [MiningRoyaltyReclamationSafetyController::class, 'postBond'])->name('reclamation.bond');

            Route::get('/blast-safety', [MiningRoyaltyReclamationSafetyController::class, 'blastSafety'])->name('blast-safety');
            Route::post('/blast-safety/lock', [MiningRoyaltyReclamationSafetyController::class, 'lockBlast'])->name('blast-safety.lock');
        });
    });

    // Fase 119: Underground, Blast, Geotech, Barge (mine_manager, site_manager, admin)
    Route::middleware(['role:mine_manager,site_manager,admin,dispatcher_mine,hse_officer'])->prefix('fase119')->name('mining.fase119.')->group(function () {
        Route::get('/underground', [MiningUndergroundAndMarineController::class, 'index'])->name('underground');
        Route::post('/stopes', [MiningUndergroundAndMarineController::class, 'createStope'])->name('stopes.store');
        Route::post('/ventilation/simulate', [MiningUndergroundAndMarineController::class, 'simulateVentilation'])->name('ventilation.simulate');

        Route::get('/blast', [MiningUndergroundAndMarineController::class, 'blastView'])->name('blast');
        Route::post('/blast/runs', [MiningUndergroundAndMarineController::class, 'createBlastRun'])->name('blast.runs.store');
        Route::post('/blast/verify-clearance', [MiningUndergroundAndMarineController::class, 'verifyClearance'])->name('blast.verify');
        Route::post('/blast/trigger-siren', [MiningUndergroundAndMarineController::class, 'triggerSiren'])->name('blast.siren');
        Route::post('/blast/detonate', [MiningUndergroundAndMarineController::class, 'detonate'])->name('blast.detonate');
        Route::post('/blast/fragmentation', [MiningUndergroundAndMarineController::class, 'evaluateFragmentation'])->name('blast.fragmentation');

        Route::post('/blast/radius', [MiningUndergroundAndMarineController::class, 'calculateBlastRadius'])->name('blast.radius');
        Route::post('/blast/drill-correction', [MiningUndergroundAndMarineController::class, 'recommendDrillCorrection'])->name('blast.drill-correction');

        Route::get('/geotech', [MiningUndergroundAndMarineController::class, 'geotechView'])->name('geotech');
        Route::post('/geotech/telemetry', [MiningUndergroundAndMarineController::class, 'ingestSlopeTelemetry'])->name('geotech.telemetry');
        Route::post('/geotech/evacuate', [MiningUndergroundAndMarineController::class, 'triggerEvacuation'])->name('geotech.evacuate');
        Route::post('/geotech/investigate', [MiningUndergroundAndMarineController::class, 'investigateGeotech'])->name('geotech.investigate');
        Route::post('/geotech/bishop-fos', [MiningUndergroundAndMarineController::class, 'evaluateBishopFos'])->name('geotech.bishop-fos');
        Route::post('/geotech/remediate', [MiningUndergroundAndMarineController::class, 'remediateSlope'])->name('geotech.remediate');
        Route::post('/geotech/resolve', [MiningUndergroundAndMarineController::class, 'resolveIncident'])->name('geotech.resolve');

        Route::post('/underground/gas-inspection', [MiningUndergroundAndMarineController::class, 'inspectGasLevel'])->name('underground.gas');
        Route::post('/underground/fan-optimize', [MiningUndergroundAndMarineController::class, 'optimizeFanPower'])->name('underground.fan-optimize');

        Route::post('/drone/tin-mesh-volume', [MiningUndergroundAndMarineController::class, 'calculateTinMeshVolume'])->name('drone.tin-volume');
        Route::post('/drone/reconcile-weighbridge', [MiningUndergroundAndMarineController::class, 'reconcileDroneWithWeighbridge'])->name('drone.reconcile');

        Route::get('/barge', [MiningUndergroundAndMarineController::class, 'bargeView'])->name('barge');
        Route::post('/barge/draft', [MiningUndergroundAndMarineController::class, 'recordDraft'])->name('barge.draft');
        Route::post('/barge/reconcile', [MiningUndergroundAndMarineController::class, 'reconcileBarge'])->name('barge.reconcile');
        Route::post('/barge/chain-of-custody', [MiningUndergroundAndMarineController::class, 'auditChainOfCustody'])->name('barge.chain');
        Route::post('/barge/settle-freight', [MiningUndergroundAndMarineController::class, 'settleFreight'])->name('barge.freight');

        Route::get('/export-csv', [MiningUndergroundAndMarineController::class, 'exportCsv'])->name('export-csv');
    });

    // Fase 121: Minerals Processing, Smelter & Metals Trading Desk
    Route::middleware(['role:admin,dispatcher_mine,hse_officer,mine_manager,site_manager'])->prefix('fase121')->name('mining.fase121.')->group(function () {
        Route::get('/smelter', [MiningSmelterAndTradingController::class, 'smelterView'])->name('smelter');
        Route::post('/smelter/runs', [MiningSmelterAndTradingController::class, 'createFurnaceRun'])->name('smelter.runs.store');
        Route::post('/smelter/balance', [MiningSmelterAndTradingController::class, 'executeEnergyBalance'])->name('smelter.balance');
        Route::post('/smelter/tapping', [MiningSmelterAndTradingController::class, 'recordTapping'])->name('smelter.tapping');
        Route::post('/smelter/assay', [MiningSmelterAndTradingController::class, 'conductQualityAssay'])->name('smelter.assay');
        Route::post('/smelter/emissions', [MiningSmelterAndTradingController::class, 'evaluateEmissionIntensity'])->name('smelter.emissions');
        Route::post('/smelter/carbon-tax', [MiningSmelterAndTradingController::class, 'calculateCarbonTax'])->name('smelter.carbon-tax');
        Route::post('/smelter/slag-basicity', [MiningSmelterAndTradingController::class, 'calculateSlagBasicity'])->name('smelter.slag-basicity');
        Route::post('/smelter/reconcile', [MiningSmelterAndTradingController::class, 'reconcileMassBalance'])->name('smelter.reconcile');
        Route::post('/smelter/audit-chain', [MiningSmelterAndTradingController::class, 'auditChainOfCustody'])->name('smelter.audit-chain');
        Route::post('/smelter/abort', [MiningSmelterAndTradingController::class, 'abortFurnaceRun'])->name('smelter.abort');
        Route::post('/smelter/hilirisasi-pipeline', [MiningSmelterAndTradingController::class, 'runHilirisasiPipeline'])->name('smelter.hilirisasi-pipeline');

        Route::get('/trading', [MiningSmelterAndTradingController::class, 'tradingView'])->name('trading');
        Route::post('/trading/contracts', [MiningSmelterAndTradingController::class, 'createTradingContract'])->name('trading.contracts.store');
        Route::post('/trading/lock-price', [MiningSmelterAndTradingController::class, 'lockContractPrice'])->name('trading.lock-price');
        Route::post('/trading/mtm', [MiningSmelterAndTradingController::class, 'executeMtmValuation'])->name('trading.mtm');
        Route::post('/trading/margin-call', [MiningSmelterAndTradingController::class, 'triggerMarginCall'])->name('trading.margin-call');
        Route::post('/trading/settle', [MiningSmelterAndTradingController::class, 'settleContract'])->name('trading.settle');
        Route::post('/trading/lme-contango', [MiningSmelterAndTradingController::class, 'evaluateLmeContango'])->name('trading.lme-contango');

        Route::get('/warehouse-receipt', [MiningSmelterAndTradingController::class, 'warehouseReceiptView'])->name('warehouse-receipt');
        Route::post('/warehouse-receipt/issue', [MiningSmelterAndTradingController::class, 'issueWarehouseReceipt'])->name('warehouse-receipt.issue');
        Route::post('/warehouse-receipt/register-bappebti', [MiningSmelterAndTradingController::class, 'registerBappebti'])->name('warehouse-receipt.register-bappebti');
        Route::post('/warehouse-receipt/pledge-scf', [MiningSmelterAndTradingController::class, 'pledgeBankScf'])->name('warehouse-receipt.pledge-scf');
        Route::post('/warehouse-receipt/release', [MiningSmelterAndTradingController::class, 'releaseWarehouseReceipt'])->name('warehouse-receipt.release');
        Route::post('/warehouse-receipt/validate-integrity', [MiningSmelterAndTradingController::class, 'validateReceiptIntegrity'])->name('warehouse-receipt.validate-integrity');

        Route::get('/byproduct', [MiningSmelterAndTradingController::class, 'byproductView'])->name('byproduct');
        Route::post('/byproduct/dispatch', [MiningSmelterAndTradingController::class, 'dispatchByproductSlag'])->name('byproduct.dispatch');
        Route::post('/byproduct/recycle', [MiningSmelterAndTradingController::class, 'recycleByproductSlag'])->name('byproduct.recycle');

        Route::post('/trading/plan-layered-hedge', [MiningSmelterAndTradingController::class, 'planLayeredHedge'])->name('trading.plan-layered-hedge');
        Route::post('/smelter/allocate-joint-cost', [MiningSmelterAndTradingController::class, 'allocateJointCost'])->name('smelter.allocate-joint-cost');
        Route::post('/smelter/settle-final-assay', [MiningSmelterAndTradingController::class, 'settleFinalAssay'])->name('smelter.settle-final-assay');
        Route::post('/warehouse-receipt/scan-expiry', [MiningSmelterAndTradingController::class, 'scanReceiptExpiry'])->name('warehouse-receipt.scan-expiry');
        Route::get('/audit-metals-desk', [MiningSmelterAndTradingController::class, 'auditMetalsDesk'])->name('audit-metals-desk');

        Route::get('/export-csv', [MiningSmelterAndTradingController::class, 'exportReportCsv'])->name('export-csv');
    });
});

// Offtaker portal (isolated customer access)
Route::middleware(['web', 'auth', 'role:offtaker,admin'])->prefix('offtaker/mining')->group(function () {
    Route::get('/', [MiningOfftakerPortalController::class, 'index'])->name('mining.offtaker.portal');
    Route::post('/nominate', [MiningOfftakerPortalController::class, 'nominate'])->name('mining.offtaker.nominate');
});

// Contractor portal (isolated partner access)
Route::middleware(['web', 'auth', 'role:contractor,admin'])->prefix('contractor/mining')->group(function () {
    Route::get('/', [MiningContractorPortalController::class, 'index'])->name('mining.contractor.portal');
    Route::post('/dispute', [MiningContractorPortalController::class, 'dispute'])->name('mining.contractor.dispute');
});
