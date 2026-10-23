<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Hospital\Http\Controllers\HospitalController;
use Modules\Hospital\Http\Controllers\LabDiagnosticImagingController;
use Modules\Hospital\Http\Controllers\TelemedicinePharmacyController;

Route::prefix('hospital')->middleware(['web', 'auth'])->group(function () {
    // Fase 87 & Clinical Trials
    Route::middleware('role:admin,hospital_admin,doctor')->group(function () {
        Route::get('/', [HospitalController::class, 'index'])->name('hospital.index');
        Route::post('/trials/{trial}/bill-milestone', [HospitalController::class, 'billTrialMilestone'])->name('hospital.trials.bill');
    });

    // Fase 104 — Telemedicine, Prescription Safety & E-Pharmacy
    Route::get('/telemedicine', [TelemedicinePharmacyController::class, 'telemedicineIndex'])
        ->middleware('role:admin,auditor,doctor,nurse')
        ->name('hospital.telemedicine.index');

    Route::post('/telemedicine/prescriptions/evaluate', [TelemedicinePharmacyController::class, 'evaluatePrescription'])
        ->middleware('role:admin,doctor,pharmacist')
        ->name('hospital.telemedicine.prescriptions.evaluate');

    Route::get('/epharmacy/subscriptions', [TelemedicinePharmacyController::class, 'subscriptionsIndex'])
        ->middleware('role:admin,auditor,doctor,pharmacist')
        ->name('hospital.epharmacy.subscriptions.index');

    Route::post('/epharmacy/routing', [TelemedicinePharmacyController::class, 'dispatchRouting'])
        ->middleware('role:admin,pharmacist')
        ->name('hospital.epharmacy.routing.dispatch');

    // Fase 105 — Laboratory LOINC, Specimen Custody, Outsourcing & DICOM PACS
    Route::get('/laboratory/catalog', [LabDiagnosticImagingController::class, 'catalogIndex'])
        ->middleware('role:admin,auditor,doctor,nurse,pharmacist')
        ->name('hospital.laboratory.catalog.index');

    Route::post('/laboratory/specimens/custody', [LabDiagnosticImagingController::class, 'scanSpecimenCustody'])
        ->middleware('role:admin,doctor,nurse')
        ->name('hospital.laboratory.specimens.custody');

    Route::post('/laboratory/results/verify', [LabDiagnosticImagingController::class, 'verifyLabResult'])
        ->middleware('role:admin,doctor')
        ->name('hospital.laboratory.results.verify');

    Route::get('/laboratory/outsourcing', [LabDiagnosticImagingController::class, 'outsourcingIndex'])
        ->middleware('role:admin,auditor,doctor')
        ->name('hospital.laboratory.outsourcing.index');

    Route::get('/imaging/pacs', [LabDiagnosticImagingController::class, 'pacsIndex'])
        ->middleware('role:admin,auditor,doctor')
        ->name('hospital.imaging.pacs.index');
});
