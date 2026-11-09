# Panduan Pengujian & Verifikasi Kepatuhan Sistem Rumah Sakit Fase 88

## 1. Strategi Pengujian Berlapis (Testing Pyramid Strategy)

Implementasi Fase 88 modul `Hospital` menerapkan pendekatan pengujian perangkat lunak berlapis (*Test-Driven Development* & *Behavior-Driven Development*) guna menjamin keandalan sistem dalam menangani transaksi bernilai finansial tinggi dan keselamatan pasien (*patient safety*).

```
                      / \
                     /   \
                    / ACT \      Acceptance Tests (10 Skenario End-to-End)
                   /-------\     - tests/Acceptance/HospitalBilling/
                  /   INT   \    Integration & Console Tests (Commands)
                 /-----------\   - Audit, Telemetri, Aging, Festronik
                /    UNIT     \  Domain Services & Math Calculators (25+ Tests)
               /---------------\ - INA-CBG, MKT, CoB, Festronik, SOFA, UDI
```

---

## 2. Matriks Pengujian Penerimaan (Acceptance Test Matrix)

File utama: `tests/Acceptance/HospitalBilling/HospitalBillingAcceptanceTest.php`  
Menjalankan 10 skenario komprehensif yang memvalidasi seluruh fungsi kritis Fase 88:

| Skenario | Nama Uji Penerimaan | Ruang Lingkup Verifikasi | Assertions Utama |
| :---: | :--- | :--- | :--- |
| **Test 1** | *RBAC Route Access for Billing & Operations* | Memverifikasi pembatasan otorisasi berbasis peran (Role-Based Access Control). Perawat, kasir, koder medis, dokter spesialis, dan admin memiliki gerbang rute terpisah. | HTTP 200 untuk peran yang berhak, HTTP 403 Forbidden untuk akses tanpa wewenang. |
| **Test 2** | *Folio Aggregation & Mathematical Integrity* | Menguji pembentukan item folio tagihan dari berbagai instalasi (poliklinik, IGD, kamar bedah, lab). Menghitung subtotal matematis tanpa pembulatan liar. | $\sum \text{Folio} \equiv \text{Total Charges}$. Status episode berpindah ke `CLOSED`. |
| **Test 3** | *INA-CBG Grouping & Class Upgrade Excess* | Menguji koder medis menginput diagnosis ICD-10 (`I21.0`) dan prosedur ICD-9-CM. Menghitung tarif dasar regional dan batasan selisih naik kelas rawat inap (cap 75%). | Ekses tarif kelas 1 ke VIP dihitung presisi sesuai Permenkes No. 3/2023. |
| **Test 4** | *Waterfall Coordination of Benefits with K31* | Menguji integrasi kontrak G7-K31 `CashlessGuaranteeRequester`. Alokasi bertingkat: BPJS menanggung paket dasar, asuransi swasta menanggung ekses, pasien melunasi copay. | Total penyerapan waterfall sama dengan tagihan kotor. Status episode berpindah ke `SETTLED`. |
| **Test 5** | *Cold Chain Thermal Excursion & Quarantine Workflow* | Mensimulasikan lonjakan suhu chiller farmasi ($T = 14.8^\circ\text{C}$). Menguji penguncian otomatis stok vaksin (`is_quarantined = true`) dan pemulihan via stability protocol. | Stok otomatis terblokir saat ekskursi dan pulih kembali setelah clearance protocol diterbitkan. |
| **Test 6** | *Medical Waste Festronik Chain-of-Custody & Tamper Detection* | Menguji rantai kustodi limbah B3 medis dari ruangan ICU -> TPS -> Transporter truk. Menguji tamper detection jika data berat di basis data dimanipulasi secara ilegal. | Hash chain SHA-256 valid saat normal, dan `verifyCustodyChainIntegrity()` bernilai `false` saat data dimanipulasi. |
| **Test 7** | *Claim Aging Analysis & Provision Calculation* | Menguji agregasi umur piutang klaim ke dalam 4 ember (*aging buckets*). Menghitung provisi cadangan penurunan nilai piutang ragu (*Expected Credit Loss* / ECL). | Seluruh ember penagihan terisi, total provisi terhitung akurat sesuai rasio PSAK 71. |
| **Test 8** | *Hospital Audit Passes Cleanly with Phase 88 Invariants* | Mengeksekusi perintah konsol `php artisan hsp:audit`. Memeriksa invarian INV-88-01, INV-88-02, INV-88-03, dan INV-88-04 pada lingkungan database aktif. | Command menghasilkan pesan `Hospital audit PASSED with 0 discrepancy` dengan Exit Code 0. |
| **Test 9** | *Dispute Appeal, Catastrophic Stop-Loss & Charity Subsidization* | Menguji berkas sanggahan banding koding INA-CBG, pemicuan klaim reasuransi stop-loss internal RS (defisit $> \text{Rp } 75\text{jt}$), dan subsidi amal pasien dhuafa ber-SKTM. | Banding menghasilkan skor kelayakan $> 80$, stop-loss terpicu menanggung $85\%$ ekses defisit, subsidi menggratiskan pasien pra-sejahtera. |
| **Test 10** | *Surgical Implant UDI & Blood Bank Hemovigilance Verification* | Menguji verifikasi nomor registrasi AKL/AKD Kemenkes dan barcode GS1 UDI implan kamar bedah, serta verifikasi rantai dingin produk darah dan uji silang serologis crossmatch. | Implan terverifikasi steril dengan token audit permanen, dan kantung darah kompatibel disetujui untuk transfusi pasien. |

---

## 3. Matriks Pengujian Unit Domain Services (Unit Test Suite)

Daftar pengujian unit murni yang memvalidasi logika matematika, formulasi sains klinis, dan aturan bisnis tanpa ketergantungan jaringan eksternal:

1. `AutoclaveSterilizationCycleServiceTest`:
   - Memvalidasi siklus sterilisasi pra-vakum $134^\circ\text{C}$ (2.1 bar, 15 menit) dan gravitasi $121^\circ\text{C}$.
   - Memastikan kegagalan jika suhu drop atau spora *Geobacillus stearothermophilus* positif tumbuh.
2. `CatastrophicCaseClaimEngineTest`:
   - Menguji perhitungan defisit kasus katastropik dan ambang proteksi stop-loss $\text{Rp } 75.000.000$.
3. `HospitalTariffMasterServiceTest`:
   - Menguji pengali regionalisasi tarif nasional Zona 1 s/d Zona 5 dan diskon korporasi tier Platinum/Gold.
4. `HospitalCharitySubsidizationEngineTest`:
   - Menguji asesmen pendapatan per kapita keluarga, verifikasi SKTM, dan subsidi penyelamatan jiwa darurat.
5. `ColdChainEquipmentCalibrationServiceTest`:
   - Menguji analisis drift sensor suhu PT100/NTC dan evaluasi sertifikat kalibrasi ISO/IEC 17025.
6. `HazardousSpillEmergencyProtocolEngineTest`:
   - Menguji respon tanggap darurat tumpahan sitotoksik kemoterapi (kantung ungu), merkuri belerang amalgamasi (kantung cokelat), dan darah infeksius (kantung kuning).
7. `DialysisWaterPurificationMonitorServiceTest`:
   - Menguji batas mutu air hemodialisis (AAMI / ISO 23500: konduktivitas, endotoksin EU/mL, klorin bebas ppm).
8. `RadioactiveMedicalWasteTrackerServiceTest`:
   - Menguji kinetika peluruhan radioaktif eksponensial Technetium-99m dan Iodium-131 hingga mencapai batas clearance $10 \text{ Bq/g}$.
9. `InaCbgDisputeResolutionEngineTest`:
   - Menguji penyusunan berkas justifikasi klinis sanggahan sengketa klaim INA-CBG ke TKMKB.
10. `GenerateO2cKpiReportActionTest`:
    - Menguji kalkulasi metrik Days Sales Outstanding (DSO), Clean Claim Rate (CCR), dan Initial Denial Rate (IDR).
11. `HospitalSurgicalImplantTraceabilityServiceTest`:
    - Menguji validasi nomor izin edar AKL/AKD Kemenkes, penapisan recall pabrik, dan masa simpan sterilitas.
12. `BloodBankHemovigilanceServiceTest`:
    - Menguji kecocokan serologis crossmatch mayor/minor dan batasan suhu penyimpanan PRC, FFP, dan Trombosit.
13. `MedicalGasPipelineSystemMonitorServiceTest`:
    - Menguji telemetri tekanan manifold gas medis ($\text{O}_2$, Medical Air, Suction Vacuum) dan deteksi kebocoran pipa.
14. `CriticalCareOrganSupportScoringServiceTest`:
    - Menguji perhitungan skor Sequential Organ Failure Assessment (SOFA) 6 sistem organ untuk justifikasi klaim ICU.
15. `EmergencyTriageEsiAlgorithmServiceTest`:
    - Menguji algoritma triase gawat darurat ESI Level 1 (Resusitasi seketika) hingga ESI Level 5 (Non-Urgent).
16. `HospitalRadioprotectionDosimetryServiceTest`:
    - Menguji pemantauan dosis radiasi TLD badge pekerja radiologi sesuai regulasi BAPETEN (maksimal $20 \text{ mSv/tahun}$).
17. `ChemotherapyDoseRegimenCalculatorServiceTest`:
    - Menguji perhitungan luas permukaan tubuh Mosteller, klirens kreatinin Cockcroft-Gault, rumus Calvert AUC Carboplatin, dan batas kumulatif kardiotoksisitas Doxorubicin ($450 \text{ mg/m}^2$).
18. `HospitalSurgicalSafeSurgeryChecklistServiceTest`:
    - Menguji verifikasi tiga tahap WHO Surgical Safety Checklist (Sign-In, Time-Out, Sign-Out) dan hitungan kasa/jarum operasi.
19. `HospitalBedTurnaroundOptimizationServiceTest`:
    - Menguji efisiensi pembersihan tempat tidur pasca-pulang (*Bed Turnaround Time*) dan validasi uji swab ATP bioluminesensi.

---

## 4. Panduan Verifikasi Pra-Push (Pre-Push Verification Playbook)

Sebelum melakukan `git push` ke repositori pusat, tim pelaksana wajib mengeksekusi urutan verifikasi berikut:

```bash
# 1. Jalankan Pindai Arsitektur Bebas Pelanggaran
php artisan arch:scan
# Wajib: 0 Baru, 0 Basi

# 2. Jalankan Seluruh Test Suite Modul Hospital
vendor/bin/pest tests/Acceptance/HospitalBilling/HospitalBillingAcceptanceTest.php
vendor/bin/pest modules/Hospital/tests/Unit/
# Wajib: 100% PASS tanpa ada failure atau error

# 3. Jalankan Pengujian Invarian Audit Konsol
php artisan hsp:audit
# Wajib: Exit Code 0 (Hospital audit PASSED with 0 discrepancy)

# 4. Jalankan Gerbang Pra-Push (Script /tmp/gerbang.sh)
/tmp/gerbang.sh 5b5086f0c913e96ae95bc4b2bc6cc2222713c3a3 7000
# Wajib: Menampilkan "GERBANG PRA-PUSH LOLOS" dengan Exit Code 0

# 5. Jalankan Pemeriksaan Kualitas Metrik (Script /tmp/metrik.py)
python3 /tmp/metrik.py 5b5086f0c913e96ae95bc4b2bc6cc2222713c3a3
# Wajib:
# - M-2 Behavioral >= 55%
# - M-3 Data-only <= 20%
# - M-4 Duplikasi = 0 duplikasi ditemukan
```
Kepatuhan terhadap playbook ini menjamin branch siap di-*merge* ke `master` tanpa menimbulkan regresi pada Continuous Integration (CI).
