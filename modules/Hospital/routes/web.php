<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Hospital\Http\Controllers\HospitalBillingController;
use Modules\Hospital\Http\Controllers\HospitalCareController;
use Modules\Hospital\Http\Controllers\HospitalController;
use Modules\Hospital\Http\Controllers\LabDiagnosticImagingController;
use Modules\Hospital\Http\Controllers\OutpatientController;
use Modules\Hospital\Http\Controllers\TelemedicinePharmacyController;

Route::prefix('hospital')->middleware(['web', 'auth'])->group(function () {
    // hospital-rawat-jalan (Vertical Slice Outpatient)
    Route::get('/outpatient/registration', [OutpatientController::class, 'registrationIndex'])
        ->middleware('role:admin,hospital_registration')
        ->name('hospital.outpatient.registration.index');

    Route::post('/outpatient/registration', [OutpatientController::class, 'registrationStore'])
        ->middleware('role:admin,hospital_registration')
        ->name('hospital.outpatient.registration.store');

    Route::get('/outpatient/queue-board', [OutpatientController::class, 'queueIndex'])
        ->middleware('role:admin,nurse,doctor,hospital_registration')
        ->name('hospital.outpatient.queue.index');

    Route::post('/outpatient/queue/{queue}/call', [OutpatientController::class, 'queueCall'])
        ->middleware('role:admin,nurse,doctor')
        ->name('hospital.outpatient.queue.call');

    Route::post('/outpatient/encounters/{encounter}/triage', [OutpatientController::class, 'triage'])
        ->middleware('role:admin,nurse')
        ->name('hospital.outpatient.encounters.triage');

    Route::post('/outpatient/encounters/{encounter}/examine', [OutpatientController::class, 'examine'])
        ->middleware('role:admin,doctor')
        ->name('hospital.outpatient.encounters.examine');

    Route::post('/outpatient/encounters/{encounter}/prescribe', [OutpatientController::class, 'prescribe'])
        ->middleware('role:admin,doctor')
        ->name('hospital.outpatient.encounters.prescribe');

    Route::get('/outpatient/pharmacy', [OutpatientController::class, 'pharmacyIndex'])
        ->middleware('role:admin,pharmacist')
        ->name('hospital.outpatient.pharmacy.index');

    Route::post('/outpatient/prescriptions/{prescription}/dispense', [OutpatientController::class, 'dispense'])
        ->middleware('role:admin,pharmacist')
        ->name('hospital.outpatient.pharmacy.dispense');

    Route::get('/outpatient/cashier', [OutpatientController::class, 'cashierIndex'])
        ->middleware('role:admin,cashier,hospital_finance')
        ->name('hospital.outpatient.cashier.index');

    Route::post('/outpatient/invoices/{invoice}/pay', [OutpatientController::class, 'cashierPay'])
        ->middleware('role:admin,cashier')
        ->name('hospital.outpatient.cashier.pay');

    Route::post('/outpatient/invoices/{invoice}/claim-bpjs', [OutpatientController::class, 'claimBpjs'])
        ->middleware('role:admin,cashier,hospital_finance')
        ->name('hospital.outpatient.bpjs.claim');

    Route::post('/outpatient/claims/{claim}/adjudicate', [OutpatientController::class, 'adjudicateBpjs'])
        ->middleware('role:admin,hospital_finance')
        ->name('hospital.outpatient.bpjs.adjudicate');

    Route::get('/outpatient/reports/daily', [OutpatientController::class, 'dailyReport'])
        ->middleware('role:admin,hospital_finance,auditor')
        ->name('hospital.outpatient.reports.daily');

    Route::get('/outpatient/portal', [OutpatientController::class, 'portalIndex'])
        ->middleware('role:admin,patient')
        ->name('hospital.outpatient.portal.index');

    Route::get('/outpatient/encounters/{encounter}', [OutpatientController::class, 'encounterShow'])
        ->middleware('role:admin,doctor,nurse,patient')
        ->name('hospital.outpatient.encounters.show');

    Route::get('/outpatient/invoices/{invoice}', [OutpatientController::class, 'invoiceShow'])
        ->middleware('role:admin,cashier,hospital_finance,patient')
        ->name('hospital.outpatient.invoices.show');
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

    // Fase 87: Identitas Pasien, EMR, Bed Management, Clinical Pathway & Critical Care
    Route::get('/beds', [HospitalCareController::class, 'bedsIndex'])
        ->middleware('role:admin,bed_manager,rs_admin')
        ->name('hospital.beds.index');

    Route::get('/er', [HospitalCareController::class, 'erIndex'])
        ->middleware('role:admin,doctor,nurse')
        ->name('hospital.er.index');

    Route::get('/nursing', [HospitalCareController::class, 'nursingIndex'])
        ->middleware('role:admin,nurse')
        ->name('hospital.nursing.index');

    Route::get('/critical-care', [HospitalCareController::class, 'criticalCareIndex'])
        ->middleware('role:admin,doctor,nurse')
        ->name('hospital.critical_care.index');

    Route::get('/patients/{patient}/passport', [HospitalCareController::class, 'patientPassport'])
        ->middleware('role:admin,doctor,hospital_registration')
        ->name('hospital.patients.passport');

    Route::get('/efficiency', [HospitalCareController::class, 'efficiencyIndex'])
        ->middleware('role:admin,rs_admin')
        ->name('hospital.efficiency.index');

    Route::post('/admissions', [HospitalCareController::class, 'storeAdmission'])
        ->middleware('role:admin,bed_manager,rs_admin,hospital_registration')
        ->name('hospital.admissions.store');

    Route::post('/admissions/{admission}/accrue-bed-day', [HospitalCareController::class, 'accrueBedDay'])
        ->middleware('role:admin,bed_manager,rs_admin')
        ->name('hospital.admissions.accrue_bed_day');

    Route::post('/admissions/{admission}/discharge', [HospitalCareController::class, 'discharge'])
        ->middleware('role:admin,bed_manager,rs_admin')
        ->name('hospital.admissions.discharge');

    Route::post('/code-blue/{event}/ack', [HospitalCareController::class, 'ackCodeBlue'])
        ->middleware('role:admin,doctor,nurse')
        ->name('hospital.code_blue.ack');

    Route::post('/code-blue/{event}/resolve', [HospitalCareController::class, 'resolveCodeBlue'])
        ->middleware('role:admin,doctor')
        ->name('hospital.code_blue.resolve');

    // Fase 88: Order-to-Cash, Billing Episode, Waterfall CoB, Cold-Chain & Waste
    Route::post('/billing/episodes', [HospitalBillingController::class, 'createEpisode'])
        ->middleware('role:admin,hospital_finance,cashier,rs_admin')
        ->name('hospital.billing.episodes.create');

    Route::post('/billing/folio-items', [HospitalBillingController::class, 'addFolioItem'])
        ->middleware('role:admin,hospital_finance,cashier,doctor,nurse,rs_admin')
        ->name('hospital.billing.folio_items.add');

    Route::post('/billing/reconcile-settle', [HospitalBillingController::class, 'reconcileAndSettle'])
        ->middleware('role:admin,hospital_finance,cashier,rs_admin')
        ->name('hospital.billing.reconcile_settle');

    Route::post('/operations/cold-chain', [HospitalBillingController::class, 'recordColdChain'])
        ->middleware('role:admin,pharmacist,nurse,rs_admin')
        ->name('hospital.operations.cold_chain.record');

    Route::post('/operations/medical-waste', [HospitalBillingController::class, 'logWaste'])
        ->middleware('role:admin,nurse,rs_admin,environmental_officer')
        ->name('hospital.operations.waste.log');

    Route::post('/operations/medical-waste/handover', [HospitalBillingController::class, 'handoverWaste'])
        ->middleware('role:admin,environmental_officer,rs_admin')
        ->name('hospital.operations.waste.handover');

    Route::get('/operations/medical-waste/{manifestId}/verify-chain', [HospitalBillingController::class, 'verifyWasteCustody'])
        ->middleware('role:admin,environmental_officer,rs_admin')
        ->name('hospital.operations.waste.verify_chain');

    Route::get('/billing/aging-report', [HospitalBillingController::class, 'getAgingReport'])
        ->middleware('role:admin,hospital_finance,rs_admin')
        ->name('hospital.billing.aging_report');
});
