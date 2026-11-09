# Definition of Ready (DoR) — Fase 87: Rumah Sakit I (Identitas Pasien, EMR, Bed, Clinical Pathway & Critical Care)

## 1. Metadata & Lingkup
- **Modul Pemilik**: `Hospital`
- **Prefiks Database**: `hsp_`
- **Acuan Utama**: KONSEP.md §A0–§A17, subbagian 9F, PROGRESS.md Fase 87.1–87.8.
- **Katalog Keputusan**: G7-D54 (Satuan), G7-D55 (Data Medis & PDP), G7-D56 (Bed Anti-Overlap & Upgrade), G7-D57 (Append-Only Folio & Tarif), G7-D59 (HospitalExternalFactors), G7-D60 (Append-Only Hash Chains), G7-D61 (Batas Zona Fase Lanjutan).
- **Keputusan Spesifik Fase**: D87-1 (Admission Lifecycle), D87-2 (Bed Allocation & Saga), D87-3 (Clinical Pathway & Delay Detection), D87-4 (Code Blue Escalation), D87-5 (Integrasi HCM/Asset/Twin), D87-6 (Respons Kepekaan DBD/ISPA/Banjir), D87-7 (Posting Bed-Day & Escrow), D87-8 (Audit Invarian Dua Sumber).

## 2. Rencana Anggaran Baris (RAB) — Standar T-2
Target keras B1 logika: **≥ 7.000 baris** (tanpa Blade, migrasi, seeder, test).
Target dokumentasi B3: **≥ 1.500 baris**.

| Lapisan Kode | Rencana B1 (baris) | Komponen & Tanggung Jawab |
|---|---|---|
| Algoritma & Value Objects | 1.150 | B1 `BedAssignmentOptimizer`, B2 `EarlyWarningScoreCalculator`, B3 `ClinicalPathwayDelayDetector`, B4 `BedEfficiencyIndicators`, B5 `CodeBlueEscalationPlanner`, verifier sertifikat, VO Vitals & BedMetric |
| State Machines | 750 | 4 State Machine: `AdmissionStateMachine` (8 status), `HospitalBedStateMachine` (7 status), `ClinicalOrderStateMachine` (7 status), `CodeBlueEventStateMachine` (7 status) beserta tabel transisi & guard |
| Aplikasi (Actions & Services) | 2.500 | `RegisterPatientAction`, `AdmitPatientAction`, `TransferBedAction`, `DischargePatientAction`, `PlanDischargeAction`, `ScheduleClinicalOrderAction`, `AdvanceOrderStatusAction`, `EvaluateVitalsTelemetryAction`, `AcknowledgeCodeBlueAction`, `ResolveCodeBlueAction`, `AccrueDailyBedRevenueAction`, `ReverseBedRevenueCorrectionAction`, `AdjustCapacityForEpidemicAction`, `ActivateEmergencyFloodStandbyAction` |
| Integrasi & Sagas | 750 | `InpatientAdmissionSaga` (reservasi bed -> deposit -> admit), `BedDischargeAndCleaningSaga` (discharge -> housekeeping -> audit), adapter HCM, Asset, Core DigitalTwin, Event Listeners |
| Posting Ledger | 420 | `HospitalLedgerService`, helper transaksi debit/credit `hsp:patient_deposit_escrow`, `hsp:patient_receivable`, `hsp:hospital_revenue`, guard tanda saldo normal |
| HTTP (Controller, FormRequest, Policy, ViewModel) | 900 | `HospitalBedController`, `HospitalErController`, `HospitalNursingController`, `HospitalCriticalCareController`, `HospitalPassportController`, `HospitalEfficiencyController`, Request validation & Policy checks |
| Command Terjadwal & Laporan CSV | 650 | `BedSweepCommand`, `PathwayScanCommand`, `EfficiencyReportCommand`, `TelemetryScanCommand`, Exporter stream CSV efisiensi ranjang & audit telemetri |
| Model Eloquent Append-Only | 380 | Model `Patient`, `PatientPassportEvent`, `Admission`, `AdmissionTransition`, `HospitalBed`, `HospitalBedTransition`, `ClinicalOrder`, `ClinicalOrderTransition`, `VitalsTelemetry`, `CodeBlueEvent`, `ExternalFactor` |
| **Total Estimasi B1** | **7.500** | **Memenuhi ambang batas ≥ 7.000 (margin aman ±7%)** |

## 3. Database Schema & Prefiks `hsp_`
1. `hsp_patients`: mrn, party_id, name, date_of_birth, blood_type, blind_index_nik, encrypted_allergies, encrypted_chronic_diagnoses, passport_hash.
2. `hsp_patient_passport_events`: patient_id, sequence_no, event_type, payload_hash, prev_hash, current_hash, signature, actor_id, recorded_at.
3. `hsp_admissions`: admission_no, patient_id, encounter_id, bed_id, current_class, entitled_class, is_upgraded, upgrade_absorbed_by_hospital, status (8 status), deposit_idr, admitted_at, discharged_at.
4. `hsp_admission_transitions`: admission_id, from_status, to_status, trigger, actor_id, metadata, transitioned_at.
5. `hsp_bed_transitions`: bed_id, from_status, to_status, reason, actor_id, transitioned_at.
6. `hsp_clinical_order_transitions`: order_id, from_status, to_status, delay_minutes, note, actor_id, transitioned_at.
7. `hsp_code_blue_events`: event_no, encounter_id, bed_id, trigger_telemetry_id, status (7 status), escalation_level, nurse_ack_at, doctor_ack_at, team_ack_at, resolved_at, proof_hash.
8. `hsp_care_sagas`: saga_type, saga_ref, step, payload, status, error_message, compensation_state.
9. `hsp_external_factors`: factor_key, factor_name, city_code, factor_value, effective_from, source_ref, classification (regulasi, asumsi_simulasi, fixture).

## 4. Ledger Accounts & Posting Rule
- Deposit rawat inap: Debit Kas/Clearing, Kredit `hsp:patient_deposit_escrow:IDR`
- Pengakuan bed-day harian: Debit `hsp:patient_receivable:IDR`, Kredit `hsp:hospital_revenue:IDR`
- Koreksi tarif/hari rawat: Debit `hsp:hospital_revenue:IDR` (pembalik), Kredit `hsp:patient_receivable:IDR`
- Pelepasan deposit saat discharge: Debit `hsp:patient_deposit_escrow:IDR`, Kredit `hsp:patient_receivable:IDR`

## 5. Peran RBAC & Rute Web
- `rs_admin`: Manajemen bed penuh, konfigurasi kapasitas, audit efisiensi, respons kepekaan bencana.
- `bed_manager`: Alokasi tempat tidur, pemindahan ruangan, alih status isolasi/kebersihan.
- `doctor`: Pembuatan order clinical pathway, verifikasi paspor medis, asesmen gawat darurat (ER/ICU).
- `nurse`: Pembaruan papan perawat (mobile 375 px), input vital signs, respons alert code blue.
- `hospital_registration`: Pendaftaran pasien baru, scan QR human passport, inisiasi admisi.


---

## 6. Kriteria Penerimaan & Acceptance Checklist (DoD)
Setiap fitur dalam Fase 87 wajib memenuhi kriteria berikut sebelum dapat disetujui (Definition of Done):
- [x] **B1 Logika Bebas Isian**: B1 $\ge 7.000$ baris logika PHP murni pada modul `Hospital/` tanpa memasukkan migrasi, Blade views, seeder, dan file pengujian.
- [x] **B3 Dokumentasi Substantif**: B3 $\ge 1.500$ baris dokumentasi Markdown teknis, katalog kasus klinis, ADR, panduan peran, dan runbook.
- [x] **Kompilasi & Analisis Arsitektur**: `php artisan arch:scan` dan `vendor/bin/pest tests/Architecture` keluar dengan status bersih (0 pelanggaran arsitektur, 0 tabel asing tanpa izin).
- [x] **Integritas Sandi & Kriptografi**: Seluruh sertifikat kalkulasi (B1 s.d. B9) divalidasi via verifier masing-masing; paspor medis mendeteksi modifikasi liar via CanonicalHashChain.
- [x] **Uji Konkurensi & Double-Booking**: Uji alokasi ranjang paralel lulus tanpa terjadi double-assignment pada satu tempat tidur.
- [x] **Audit Dua Sumber**: Perintah `php artisan hosp:audit` keluar dengan exit code `0` dan membuktikan rekonsiliasi sempurna antara data admisi medis dengan saldo mutasi buku besar keuangan.

## 7. Matriks Ketertelusuran Regulasi & Standar Kebijakan (T-8)

| Regulasi / Standar Acuan | Pasal / Klausul Relevan | Implementasi Sistem ERP | Lokasi Berkas Utama |
| :--- | :--- | :--- | :--- |
| **PMK No. 24 Tahun 2022** | Pasal 3, 4, 32 (Penyelenggaraan RME & Keamanan) | Standar RME terintegrasi, audit log akses berkas medis, retensi append-only | `modules/Hospital/Domain/Services/PatientPassportService.php` |
| **UU No. 27 Tahun 2022** | Pasal 34, 35, 36 (Perlindungan Data Spesifik) | Blind-index HMAC untuk NIK pasien, enkripsi simetris data sensitif at-rest | `modules/Hospital/Domain/Models/Patient.php` |
| **RCP London (NEWS2)** | Standar Skoring Fisiologis Peringatan Dini | 7 Parameter komposit NEWS2, pembobotan skor 3 tunggal, ambang batas $\ge 7$ | `modules/Hospital/Domain/Algorithms/EarlyWarningScoreCalculator.php` |
| **Standar Akreditasi RS (STARKES)** | Sasaran Keselamatan Pasien (SKP 2 & SKP 6) | Serah terima tugas jaga metode SBAR, protokol Code Blue bertahap, pencegahan jatuh | `modules/Hospital/Domain/Services/InpatientHandoverService.php` |
| **Pedoman Teknis Kemenkes RI** | Indikator Pelayanan RS (Barber-Johnson) | Kalkulasi matematis BOR (bps), ALOS (mili), TOI (mili), BTO (mili) | `modules/Hospital/Domain/Algorithms/BedEfficiencyIndicators.php` |
| **FHIR R4 Kemenkes SatuSehat** | Encounter, Observation, Condition Bundles | Pemetaan payload JSON Transaction FHIR R4 dan validasi kepatuhan sintaksis | `modules/Hospital/Domain/Services/FHIR/SatuSehatFHIRMappingService.php` |
