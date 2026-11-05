<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Insurance\Http\Controllers\AgriInsuranceController;
use Modules\Insurance\Http\Controllers\BrokerMarketplaceController;
use Modules\Insurance\Http\Controllers\CapitalController;
use Modules\Insurance\Http\Controllers\CatBondController;
use Modules\Insurance\Http\Controllers\CatController;
use Modules\Insurance\Http\Controllers\CustomerHubController;
use Modules\Insurance\Http\Controllers\EmbeddedInsuranceController;
use Modules\Insurance\Http\Controllers\FullInsuranceController;
use Modules\Insurance\Http\Controllers\HealthInsuranceController;
use Modules\Insurance\Http\Controllers\InsuranceClaimsWorkflowController;
use Modules\Insurance\Http\Controllers\InsuranceCommandCenterController;
use Modules\Insurance\Http\Controllers\InsuranceController;
use Modules\Insurance\Http\Controllers\InsurancePoliciesController;
use Modules\Insurance\Http\Controllers\InvestorCatBondController;
use Modules\Insurance\Http\Controllers\LifeInsuranceController;
use Modules\Insurance\Http\Controllers\MicroInsuranceController;
use Modules\Insurance\Http\Controllers\ReinsuranceController;
use Modules\Insurance\Http\Controllers\SiuInvestigationController;
use Modules\Insurance\Http\Controllers\TakafulController;
use Modules\Insurance\Http\Controllers\UnitLinkController;
use Modules\Insurance\Http\Controllers\WellnessController;

Route::middleware(['web', 'auth'])->prefix('insurance')->group(function () {
    Route::middleware('role:admin,insurance_admin,underwriter,customer')->group(function () {
        Route::get('/', [InsuranceController::class, 'index'])->name('insurance.index');
        Route::get('/policies', [InsuranceClaimsWorkflowController::class, 'policies'])->name('insurance.policies');
        Route::post('/policies/issue', [InsurancePoliciesController::class, 'issue'])->name('insurance.policies.issue');
        Route::post('/products/{product}/issue', [InsuranceController::class, 'issue'])->name('insurance.products.issue');

        // Insurance Underwriting & Claims Deepening Routes
        Route::prefix('workflow')->name('insurance.workflow.')->group(function () {
            Route::get('/policies', [InsuranceClaimsWorkflowController::class, 'policies'])->name('policies');
            Route::get('/claims', [InsuranceClaimsWorkflowController::class, 'claims'])->name('claims');
            Route::get('/fraud-evaluations', [InsuranceClaimsWorkflowController::class, 'fraudEvaluations'])->name('fraud-evaluations');
            Route::get('/parametric-triggers', [InsuranceClaimsWorkflowController::class, 'parametricTriggers'])->name('parametric-triggers');
            Route::post('/issue-policy', [InsuranceClaimsWorkflowController::class, 'issuePolicy'])->name('issue-policy');
            Route::post('/trigger-parametric', [InsuranceClaimsWorkflowController::class, 'triggerParametric'])->name('trigger-parametric');
            Route::post('/evaluate-fraud', [InsuranceClaimsWorkflowController::class, 'evaluateFraud'])->name('evaluate-fraud');
            Route::post('/settle-claim', [InsuranceClaimsWorkflowController::class, 'settleClaim'])->name('settle-claim');
        });
    });

    // Lini 18 Asuransi & Reasuransi Penuh (Fase 156)
    Route::prefix('full')->name('insurance.full.')->group(function () {
        Route::middleware('role:admin,superadmin,underwriter,actuary,claims_adjuster,reinsurance_mgr')->group(function () {
            Route::get('/products', [FullInsuranceController::class, 'products'])->name('products.index');
            Route::get('/actuarial', [FullInsuranceController::class, 'actuarial'])->name('actuarial.index');
        });

        Route::middleware('role:admin,superadmin,underwriter,actuary,claims_adjuster,reinsurance_mgr,customer')->group(function () {
            Route::get('/policies', [FullInsuranceController::class, 'policies'])->name('policies.index');
            Route::get('/policies/{id}', [FullInsuranceController::class, 'policyDetail'])->name('policies.show');
            Route::get('/claims', [FullInsuranceController::class, 'claims'])->name('claims.index');
            Route::get('/claims/{id}', [FullInsuranceController::class, 'claimDetail'])->name('claims.show');
        });
    });

    // Reinsurance Management (Fase 157)
    Route::prefix('reinsurance')->name('insurance.reinsurance.')->middleware('role:admin,superadmin,reinsurance_mgr')->group(function () {
        Route::get('/treaties', [ReinsuranceController::class, 'treaties'])->name('treaties.index');
        Route::get('/treaties/{id}', [ReinsuranceController::class, 'treatyDetail'])->name('treaties.show');
        Route::post('/treaties', [ReinsuranceController::class, 'storeTreaty'])->name('treaties.store');
        Route::post('/treaties/{id}/transition', [ReinsuranceController::class, 'transitionTreaty'])->name('treaties.transition');

        Route::get('/bordereaux', [ReinsuranceController::class, 'bordereaux'])->name('bordereaux.index');
        Route::get('/bordereaux/{id}', [ReinsuranceController::class, 'bordereauDetail'])->name('bordereaux.show');
        Route::post('/bordereaux/generate', [ReinsuranceController::class, 'generateBordereau'])->name('bordereaux.generate');
        Route::post('/bordereaux/{id}/send', [ReinsuranceController::class, 'sendBordereau'])->name('bordereaux.send');
        Route::get('/bordereaux/{id}/export-csv', [ReinsuranceController::class, 'exportBordereauCsv'])->name('bordereaux.export-csv');

        Route::get('/account-current', [ReinsuranceController::class, 'accountCurrents'])->name('account-current.index');
        Route::get('/account-current/{id}', [ReinsuranceController::class, 'accountCurrentDetail'])->name('account-current.show');
        Route::post('/account-current/generate', [ReinsuranceController::class, 'generateAccountCurrent'])->name('account-current.generate');
        Route::post('/account-current/{id}/settle', [ReinsuranceController::class, 'settleAccountCurrent'])->name('account-current.settle');
    });

    // Capital & Solvency (Fase 157)
    Route::prefix('capital')->name('insurance.capital.')->middleware('role:admin,superadmin,actuary,cfo_view')->group(function () {
        Route::get('/', [CapitalController::class, 'index'])->name('index');
        Route::post('/snapshot', [CapitalController::class, 'createSnapshot'])->name('snapshot');
        Route::post('/simulate', [CapitalController::class, 'simulate'])->name('simulate');
    });

    // Catastrophe Modelling (Fase 157)
    Route::prefix('cat')->name('insurance.cat.')->middleware('role:admin,superadmin,actuary')->group(function () {
        Route::get('/', [CatController::class, 'index'])->name('index');
        Route::post('/run', [CatController::class, 'run'])->name('run');
        Route::get('/{id}', [CatController::class, 'show'])->name('show');
    });

    // Cat Bonds (Fase 157)
    Route::prefix('cat-bonds')->name('insurance.cat-bonds.')->middleware('role:admin,superadmin,reinsurance_mgr')->group(function () {
        Route::get('/', [CatBondController::class, 'index'])->name('index');
        Route::get('/{id}', [CatBondController::class, 'show'])->name('show');
        Route::post('/', [CatBondController::class, 'store'])->name('store');
        Route::post('/{id}/coupon', [CatBondController::class, 'distributeCoupon'])->name('coupon');
        Route::post('/{id}/trigger', [CatBondController::class, 'trigger'])->name('trigger');
    });

    // Embedded Insurance (Fase 158)
    Route::prefix('embedded')->name('insurance.embedded.')->middleware('role:admin,superadmin,underwriter,insurance_admin,broker')->group(function () {
        Route::get('/matrix', [EmbeddedInsuranceController::class, 'matrix'])->name('matrix');
        Route::get('/policies', [EmbeddedInsuranceController::class, 'policies'])->name('policies');
        Route::post('/issue', [EmbeddedInsuranceController::class, 'issue'])->name('issue');
    });

    // Broker Marketplace (Fase 158)
    Route::prefix('brokers')->name('insurance.brokers.')->middleware('role:admin,superadmin,insurance_admin,broker')->group(function () {
        Route::get('/', [BrokerMarketplaceController::class, 'index'])->name('index');
        Route::post('/', [BrokerMarketplaceController::class, 'register'])->name('register');
        Route::get('/commissions', [BrokerMarketplaceController::class, 'commissions'])->name('commissions');
        Route::post('/commissions/{id}/settle', [BrokerMarketplaceController::class, 'settle'])->name('settle');
    });

    // Customer Hub (Fase 158)
    Route::prefix('hub')->name('insurance.hub.')->middleware('role:admin,superadmin,customer,underwriter,broker')->group(function () {
        Route::get('/', [CustomerHubController::class, 'dashboard'])->name('dashboard');
    });

    // SIU Investigation (Fase 158)
    Route::prefix('siu')->name('insurance.siu.')->middleware('role:admin,superadmin,claims_adjuster,underwriter')->group(function () {
        Route::get('/', [SiuInvestigationController::class, 'index'])->name('index');
        Route::get('/{id}', [SiuInvestigationController::class, 'show'])->name('show');
        Route::post('/{id}/decide', [SiuInvestigationController::class, 'decide'])->name('decide');
    });

    // Life Insurance & Saving Plans (Fase 159)
    Route::prefix('life')->name('insurance.life.')->group(function () {
        Route::middleware('role:admin,superadmin,underwriter,insurance_admin')->group(function () {
            Route::get('/', [LifeInsuranceController::class, 'index'])->name('index');
            Route::get('/actuarial', [LifeInsuranceController::class, 'actuarial'])->name('actuarial');
            Route::get('/{id}', [LifeInsuranceController::class, 'show'])->name('show');
            Route::post('/', [LifeInsuranceController::class, 'store'])->name('store');
            Route::post('/{id}/underwrite', [LifeInsuranceController::class, 'underwrite'])->name('underwrite');
        });
        Route::middleware('role:admin,superadmin,claims_adjuster,underwriter')->group(function () {
            Route::post('/claim', [LifeInsuranceController::class, 'claim'])->name('claim');
        });
        Route::middleware('role:admin,superadmin,underwriter,customer')->group(function () {
            Route::post('/{id}/surrender', [LifeInsuranceController::class, 'surrender'])->name('surrender');
        });
    });

    // Health Insurance & Cashless Admission (Fase 159)
    Route::prefix('health')->name('insurance.health.')->group(function () {
        Route::middleware('role:admin,superadmin,underwriter,insurance_admin')->group(function () {
            Route::get('/', [HealthInsuranceController::class, 'index'])->name('index');
            Route::get('/analytics', [HealthInsuranceController::class, 'analytics'])->name('analytics');
        });
        Route::middleware('role:admin,superadmin,claims_adjuster,underwriter')->group(function () {
            Route::get('/guarantee-letters', [HealthInsuranceController::class, 'guaranteeLetters'])->name('gl.index');
            Route::post('/guarantee-letters', [HealthInsuranceController::class, 'requestGl'])->name('gl.request');
            Route::get('/claims', [HealthInsuranceController::class, 'claims'])->name('claims.index');
            Route::post('/claims/adjudicate', [HealthInsuranceController::class, 'adjudicate'])->name('claims.adjudicate');
            Route::post('/claims/{id}/appeal', [HealthInsuranceController::class, 'appeal'])->name('claims.appeal');
        });
    });

    // Wellness Rewards (Fase 159)
    Route::prefix('wellness')->name('insurance.wellness.')->middleware('role:admin,superadmin,customer,underwriter')->group(function () {
        Route::get('/', [WellnessController::class, 'dashboard'])->name('dashboard');
        Route::post('/record', [WellnessController::class, 'record'])->name('record');
    });

    // Unit Link Portfolio (Fase 159)
    Route::prefix('unit-link')->name('insurance.unitlink.')->group(function () {
        Route::middleware('role:admin,superadmin,underwriter,investor')->group(function () {
            Route::get('/', [UnitLinkController::class, 'portfolio'])->name('portfolio');
            Route::get('/analytics', [UnitLinkController::class, 'analytics'])->name('analytics');
        });
        Route::middleware('role:admin,superadmin,underwriter,actuary')->group(function () {
            Route::post('/valuate', [UnitLinkController::class, 'valuate'])->name('valuate');
        });
        Route::middleware('role:admin,superadmin,underwriter,customer')->group(function () {
            Route::post('/switch', [UnitLinkController::class, 'switch'])->name('switch');
        });
    });

    // Takaful Window (Fase 160)
    Route::prefix('takaful')->name('insurance.takaful.')->group(function () {
        Route::middleware('role:admin,superadmin,underwriter,insurance_admin')->group(function () {
            Route::get('/', [TakafulController::class, 'index'])->name('index');
            Route::get('/actuarial-board', [TakafulController::class, 'actuarialBoard'])->name('actuarial-board');
            Route::get('/surplus', [TakafulController::class, 'surplus'])->name('surplus');
            Route::post('/', [TakafulController::class, 'store'])->name('store');
            Route::post('/distribute-surplus', [TakafulController::class, 'distribute'])->name('distribute');
        });
    });

    // Agri Insurance (Fase 160)
    Route::prefix('agri')->name('insurance.agri.')->group(function () {
        Route::middleware('role:admin,superadmin,underwriter,insurance_admin')->group(function () {
            Route::get('/', [AgriInsuranceController::class, 'index'])->name('index');
            Route::get('/weather-stations', [AgriInsuranceController::class, 'weatherStations'])->name('weather-stations');
            Route::get('/claims', [AgriInsuranceController::class, 'claims'])->name('claims');
            Route::get('/{id}', [AgriInsuranceController::class, 'show'])->name('show');
            Route::post('/', [AgriInsuranceController::class, 'store'])->name('store');
        });
        Route::middleware('role:admin,superadmin,claims_adjuster,underwriter')->group(function () {
            Route::post('/settle', [AgriInsuranceController::class, 'settle'])->name('settle');
        });
    });

    // Micro Insurance (Fase 160)
    Route::prefix('micro')->name('insurance.micro.')->middleware('role:admin,superadmin,customer,underwriter')->group(function () {
        Route::get('/', [MicroInsuranceController::class, 'index'])->name('index');
        Route::get('/analytics', [MicroInsuranceController::class, 'analytics'])->name('analytics');
        Route::post('/', [MicroInsuranceController::class, 'store'])->name('store');
    });

    // Insurance Command Center & Regulatory (Fase 160)
    Route::prefix('command')->name('insurance.command.')->middleware('role:admin,superadmin,underwriter,insurance_admin')->group(function () {
        Route::get('/', [InsuranceCommandCenterController::class, 'dashboard'])->name('dashboard');
        Route::get('/aml', [InsuranceCommandCenterController::class, 'aml'])->name('aml');
        Route::get('/calendar', [InsuranceCommandCenterController::class, 'complianceCalendar'])->name('calendar');
        Route::get('/stress-test', [InsuranceCommandCenterController::class, 'stressTest'])->name('stress-test');
        Route::post('/snapshot', [InsuranceCommandCenterController::class, 'snapshot'])->name('snapshot');
        Route::get('/regulatory', [InsuranceCommandCenterController::class, 'regulatory'])->name('regulatory');
        Route::post('/grievance', [InsuranceCommandCenterController::class, 'grievance'])->name('grievance');
        Route::post('/grievance/resolve', [InsuranceCommandCenterController::class, 'resolveGrievance'])->name('resolve-grievance');
    });
});

// Investor Portal (Fase 157)
Route::middleware(['web', 'auth'])->prefix('investor/cat-bonds')->name('investor.cat-bonds.')->middleware('role:admin,superadmin,investor,reinsurance_mgr')->group(function () {
    Route::get('/', [InvestorCatBondController::class, 'index'])->name('index');
    Route::get('/{id}', [InvestorCatBondController::class, 'show'])->name('show');
});
