<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Procurement\Http\Controllers\ProcurementController;

Route::middleware(['web', 'auth', 'verified', 'role:admin,procurement,supplier'])
    ->prefix('procurement')
    ->name('procurement.')
    ->group(function () {
        // 33.8 Dashboard — angka anggaran/spend hanya untuk internal.
        Route::get('/', [ProcurementController::class, 'dashboard'])
            ->middleware('role:admin,procurement')->name('dashboard');

        // 33.1 PR (admin/procurement)
        Route::post('/requisitions', [ProcurementController::class, 'storeRequisition'])
            ->middleware('role:admin,procurement')->name('requisitions.store');
        Route::get('/requisitions/{requisition}', [ProcurementController::class, 'showRequisition'])->name('requisitions.show');
        Route::post('/requisitions/{requisition}/approve', [ProcurementController::class, 'approveRequisition'])
            ->middleware('role:admin,procurement')->name('requisitions.approve');

        // 33.2 RFQ — daftar/tampilan untuk internal, kirim penawaran untuk supplier
        Route::post('/rfqs', [ProcurementController::class, 'storeRfq'])
            ->middleware('role:admin,procurement')->name('rfqs.store');
        Route::get('/rfqs/{rfq}', [ProcurementController::class, 'showRfq'])->name('rfqs.show');
        Route::post('/rfqs/{rfq}/quotes', [ProcurementController::class, 'storeQuote'])->name('quotes.store');
        Route::post('/rfqs/{rfq}/award', [ProcurementController::class, 'awardQuote'])
            ->middleware('role:admin,procurement')->name('rfqs.award');

        // 33.3 Tender
        Route::post('/tenders', [ProcurementController::class, 'storeTender'])
            ->middleware('role:admin,procurement')->name('tenders.store');
        Route::get('/tenders/{tender}', [ProcurementController::class, 'showTender'])->name('tenders.show');
        Route::post('/tenders/{tender}/seal', [ProcurementController::class, 'sealBid'])->name('tenders.seal');
        Route::post('/tenders/{tender}/open', [ProcurementController::class, 'openBids'])
            ->middleware('role:admin,procurement')->name('tenders.open');
        Route::post('/tenders/{tender}/evaluate', [ProcurementController::class, 'evaluateTender'])
            ->middleware('role:admin,procurement')->name('tenders.evaluate');
        Route::post('/tenders/{tender}/award', [ProcurementController::class, 'awardTender'])
            ->middleware('role:admin,procurement')->name('tenders.award');

        // 33.4–33.5 PO (admin/procurement)
        Route::post('/pos', [ProcurementController::class, 'storePurchaseOrder'])
            ->middleware('role:admin,procurement')->name('pos.store');
        Route::get('/pos/{po}', [ProcurementController::class, 'showPurchaseOrder'])->name('pos.show');
        Route::post('/pos/{po}/revise', [ProcurementController::class, 'revisePurchaseOrder'])
            ->middleware('role:admin,procurement')->name('pos.revise');
        Route::post('/pos/{po}/close', [ProcurementController::class, 'closePurchaseOrder'])
            ->middleware('role:admin,procurement')->name('pos.close');
        Route::post('/pos/{po}/cancel', [ProcurementController::class, 'cancelPurchaseOrder'])
            ->middleware('role:admin,procurement')->name('pos.cancel');
        Route::post('/pos/{po}/import-profile', [ProcurementController::class, 'storeImportProfile'])
            ->middleware('role:admin,procurement')->name('pos.import-profile');

        // 33.7 Inbound shipment (admin/procurement)
        Route::post('/pos/{po}/inbound', [ProcurementController::class, 'bookInbound'])
            ->middleware('role:admin,procurement')->name('pos.inbound');
    });
