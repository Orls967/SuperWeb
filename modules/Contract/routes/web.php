<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Contract\Http\Controllers\ClauseTemplateController;
use Modules\Contract\Http\Controllers\ContractController;
use Modules\Contract\Http\Controllers\ContractFinanceController;
use Modules\Contract\Http\Controllers\ContractTemplateController;

Route::middleware(['web', 'auth', 'verified', 'role:admin,contract_manager,legal'])
    ->prefix('contracts')
    ->name('contract.')
    ->group(function () {

        // ── Clause Library ─────────────────────────────────────────────
        Route::prefix('clauses')->name('clauses.')->group(function () {
            Route::get('/', [ClauseTemplateController::class, 'index'])->name('index');
            Route::get('/create', [ClauseTemplateController::class, 'create'])->name('create');
            Route::post('/', [ClauseTemplateController::class, 'store'])->name('store');
            Route::get('/{clause}/edit', [ClauseTemplateController::class, 'edit'])->name('edit');
            Route::put('/{clause}', [ClauseTemplateController::class, 'update'])->name('update');
        });

        // ── Contract Templates ─────────────────────────────────────────
        Route::prefix('templates')->name('templates.')->group(function () {
            Route::get('/', [ContractTemplateController::class, 'index'])->name('index');
            Route::get('/create', [ContractTemplateController::class, 'create'])->name('create');
            Route::post('/', [ContractTemplateController::class, 'store'])->name('store');
        });

        // ── Contracts CRUD ─────────────────────────────────────────────
        Route::get('/', [ContractController::class, 'index'])->name('index');
        Route::get('/create', [ContractController::class, 'create'])->name('create');
        Route::post('/', [ContractController::class, 'store'])->name('store');

        // ── Contract Detail & Actions ──────────────────────────────────
        Route::get('/{contract}', [ContractController::class, 'show'])->name('show');
        Route::get('/{contract}/edit', [ContractController::class, 'edit'])->name('edit');
        Route::put('/{contract}', [ContractController::class, 'update'])->name('update');

        // State machine transitions
        Route::post('/{contract}/transition', [ContractController::class, 'transition'])->name('transition');

        // Approval flow
        Route::post('/{contract}/submit-approval', [ContractController::class, 'submitApproval'])->name('submit-approval');

        // E-Signature (simulated)
        Route::post('/{contract}/sign/{party}', [ContractController::class, 'signParty'])->name('sign-party');

        // Versioning
        Route::get('/{contract}/versions', [ContractController::class, 'versions'])->name('versions');
        Route::get('/{contract}/versions/diff', [ContractController::class, 'versionDiff'])->name('version-diff');
        Route::post('/{contract}/versions', [ContractController::class, 'appendVersion'])->name('append-version');

        // Milestones
        Route::post('/{contract}/milestones', [ContractController::class, 'storeMilestone'])->name('milestone.store');
        Route::post('/{contract}/milestones/{milestone}/complete', [ContractController::class, 'completeMilestone'])->name('milestone.complete');

        // Parties
        Route::post('/{contract}/parties', [ContractController::class, 'addParty'])->name('party.add');
        Route::delete('/{contract}/parties/{party}', [ContractController::class, 'removeParty'])->name('party.remove');

        // Attachments (Core DocumentStore, 28.6)
        Route::post('/{contract}/attachments', [ContractController::class, 'storeAttachment'])->name('attachment.store');
        Route::delete('/{contract}/attachments/{attachment}', [ContractController::class, 'destroyAttachment'])->name('attachment.destroy');

        // Dashboard kewajiban jatuh tempo (28.7)
        Route::get('/obligations/due', [ContractController::class, 'obligations'])->name('obligations');

        // Laporan paparan & audit subledger (29.8)
        Route::get('/reports', [ContractFinanceController::class, 'reports'])->name('reports');

        // Fase 29 — finansial per kontrak
        Route::post('/{contract}/schedule/build', [ContractFinanceController::class, 'buildSchedule'])->name('schedule.build');
        Route::post('/{contract}/schedule/{schedule}/pay', [ContractFinanceController::class, 'paySchedule'])->name('schedule.pay');
        Route::post('/{contract}/advance/pay', [ContractFinanceController::class, 'payAdvance'])->name('advance.pay');
        Route::post('/{contract}/schedule/{schedule}/penalty', [ContractFinanceController::class, 'showPenalty'])->name('schedule.penalty.show');
        Route::post('/{contract}/schedule/{schedule}/penalty/pay', [ContractFinanceController::class, 'payPenalty'])->name('schedule.penalty.pay');
        Route::post('/{contract}/schedule/{schedule}/penalty/waiver', [ContractFinanceController::class, 'requestWaiver'])->name('schedule.penalty.waiver');
        Route::post('/{contract}/schedule/{schedule}/penalty/waiver/apply', [ContractFinanceController::class, 'applyWaiver'])->name('schedule.penalty.waiver.apply');
        Route::post('/{contract}/escalation/preview', [ContractFinanceController::class, 'previewEscalation'])->name('escalation.preview');
        Route::post('/{contract}/escalation/apply', [ContractFinanceController::class, 'applyEscalation'])->name('escalation.apply');
        Route::post('/{contract}/amend', [ContractFinanceController::class, 'amend'])->name('amend');
        Route::post('/{contract}/usage/sync', [ContractFinanceController::class, 'syncUsage'])->name('usage.sync');
        Route::post('/{contract}/risk/rescore', [ContractFinanceController::class, 'rescoreRisk'])->name('risk.rescore');
    });
