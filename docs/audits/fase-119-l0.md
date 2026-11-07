# Audit Awal L.0 — Fase 119: Underground & Quarry Digital Twin, Geotech & Blast Management

**Tanggal:** 2026-11-07  
**Fase:** 119 (Pertambangan: Bawah Tanah, Digital Twin Ventilasi, Manajemen Peledakan KCO, Geoteknik Slope & Operasi Tongkang Curah)  
**Status Audit:** 🟠 KERANGKA DIPERDALAM KE STANDAR KETAT W8  
**Modul Pemilik:** `Mining` (`min_`)  
**Prasyarat:** Fase 93 ✅ (PR #82), Fase 94 ✅ (PR #83) merged ke `master`.

---

## 1. Kondisi Awal Codebase (Baseline L.0)

Sebelum pendalaman Fase 119, modul Mining memiliki implementasi awal kerangka sederhana yang dibuat pada audit 2026-10-10 (`af81f27`, `375c23b`):
1. **Model & Migrasi:**
   - `min_blast_schedules`: Jadwal peledakan sederhana dengan kolom status teks (`SCHEDULED`, `DETONATED`, `CANCELLED`, `BLOCKED`).
   - `min_slope_stability_readings`: Sensor displacement dan kecepatan pergerakan lereng (`velocity_mm_day`).
   - `min_barge_shipments`: Tongkang curah dengan tonase manifest vs timbangan jembatan timbang.
2. **Keterbatasan Utama yang Wajib Diselesaikan:**
   - **Ketiadaan Solver Ventilasi Bawah Tanah:** Belum ada algoritma simulasi aliran udara jaringan stope/shaft bawah tanah (Hardy Cross / hukum Kirchhoff).
   - **Ketiadaan Model Fragmentasi Peledakan:** Prediksi ukuran butir batuan pasca-ledak masih angka statis 0%, belum ada model ilmiah Kuznetsov-Cunningham-Ouchterlony (KCO) untuk koreksi pola pemboran (*burden/spacing/powder factor*).
   - **Perhitungan Draft Kapal Sederhana:** Belum ada kalkulator hidrostatik 6-titik draft survey tongkang (*quarter mean*, koreksi trim, list, dan densitas fluida perairan payau/laut).
   - **State Machine Belum Bersertifikat:** Status jadwal peledakan dan alert geoteknik masih string biasa tanpa transisi formal berpagar guard.
   - **Ketiadaan Saga Kompensasi:** Belum ada alur orkestrasi peledakan terdistribusi yang membatalkan ledakan dan membebaskan area (*safety unlock*) bila pekerja menerobos geofence.
   - **Buku Besar Belum Komprehensif:** Belum ada posting jurnal akrual bahan peledak (ANFO), sewa tongkang muatan curah, dan depresiasi peralatan survei fotogrametri/drone.
   - **Ketiadaan Antarmuka UI Komprehensif:** Belum ada blade view responsif mobile untuk pusat komando digital twin bawah tanah dan pemantauan sensor slope geoteknik.

---

## 2. Kesenjangan Faktual vs Standar Ketat W8

| Dimensi | Kondisi Baseline | Target W8 Fase 119 | Kesenjangan / Rencana Aksi |
| :--- | :--- | :--- | :--- |
| **B1 Logika PHP** | $\sim 480$ baris | $\ge 6.000$ baris logika bersih | Perluas arsitektur domain, solver Hardy Cross, model KCO, kalkulator draft survey, state machine, saga, ledger, dan 25+ aksi bisnis terstruktur. |
| **B3 Dokumentasi** | $\sim 120$ baris | $\ge 1.500$ baris tulis-tangan | Tulis DoR, ADR arsitektur, panduan teknis, runbook operasional tanggap darurat lereng, galat runbook, dan dokumen algoritma M7, M8, M9. |
| **M-2 Behavioral** | $\sim 40\%$ | $\ge 55\%$ | Fokus pada percabangan bisnis, validasi geofence, iterasi numerik non-linier, kompensasi saga, dan posting pembukuan berpasangan. |
| **M-3 Data-only** | $\sim 30\%$ | $\le 20\%$ | Hindari DTO/enum pasif berlebihan; seluruh kelas publik wajib memiliki metode evaluasi/kalkulasi yang dipanggil aktif. |
| **M-4 Duplikasi** | N/A | $0$ hash 10-baris | Struktur kode modular tanpa blok logika berulang. |
| **M-5 Uncalled Classes** | N/A | $0$ kelas publik yatim | Seluruh action, service, solver, dan state machine terhubung di rute, saga, komando console, atau seeder T1. |
| **Algoritma Bersertifikat** | 0 | $\ge 3$ Algoritma (M7, M8, M9) | Implementasikan M7 `HardyCrossVentilationNetworkSolver`, M8 `KcoBlastingFragmentationModel`, M9 `HydrostaticDraftSurveyReconciler` lengkap dengan verifier HMAC-SHA256. |
| **State Machine** | 0 | $\ge 2$ State Machine ($\ge 8$ status) | Bangun `BlastClearanceRunStateMachine` (8 status) dan `GeotechIncidentStateMachine` (8 status). |
| **Saga Terdistribusi** | 0 | $\ge 1$ Saga Berkompensasi | Bangun `BlastExecutionLifecycleSaga` dengan 3 cabang kompensasi pembatalan dan pemulihan perimeter. |
| **Ledger Posting** | Parsial | 1 Service Jurnal Lengkap | Bangun `MiningFase119LedgerPostingService` mengawal transaksi ANFO, survei fotogrametri, dan biaya muat tongkang curah. |
| **Seeder T1** | Sederhana | $\ge 15$ kasus dunia nyata | Bangun `MiningFase119Seeder` dengan 15 skenario bernama, idempoten 2x eksekusi tanpa tabrakan kunci unik. |
| **Pengujian Minimal** | 1 test | $\le 5$ acceptance + vektor unit | Bangun 5 acceptance test end-to-end yang menguji skenario nyata dan ketahanan sabotase tanpa menambah suite test baru. |

---

## 3. Matriks Lingkup PROGRESS.md (Fase 119)

1. **119.1 Digital Twin Tambang Bawah Tanah**:
   - Model 3D terowongan, decline, stope, raises, dan jaringan ventilasi via `TwinBus`.
   - Solver numerik iterasi Hardy Cross untuk menghitung laju aliran udara ($Q$, $\text{m}^3/\text{s}$), rugi tekanan gesekan Atkinson ($H = R \cdot Q^2$), dan kebutuhan daya kipas utama (*main fan ventilation*).
   - Jalur evakuasi darurat bawah tanah dan deteksi titik sumbatan asap/gas beracun ($CO, CH_4$).
2. **119.2 Blast Management & KCO Model**:
   - Penjadwalan peledakan tambang, kalkulasi radius keamanan perimeter berbasis massa muatan bahan peledak per delay.
   - Pengecekan telematika GPS pekerja dan armada di dalam radius bahaya: penolakan otomatis peledakan (*interlock safe-fail*) jika radius belum steril.
   - Model fragmentasi peledakan KCO (Kuznetsov-Cunningham-Ouchterlony): estimasi $x_{50}$, fraksi bongkahan *oversize* ($>1000\text{ mm}$ butuh *secondary blasting*), dan *fines* ($<25\text{ mm}$ buang debu).
   - Rekomendasi koreksi pola pemboran (*burden*, *spacing*, *stemming*) untuk peledakan berikutnya jika deviasi melebihi ambang batas.
3. **119.3 Geotech & Slope Monitoring**:
   - Telemetri sensor inklinometer, piezometer air pori, dan mikroseismik batuan real-time.
   - Evaluasi faktor keamanan lereng (*Factor of Safety* / FoS) berbasis metode Bishop sederhana dan deteksi akselerasi kecepatan longsor (*inverse velocity method*).
   - Penegakan alarm darurat 4-tingkat (*Normal*, *Advisory*, *Pre-Warning*, *Immediate Evacuation*) dan penghentian otomatis kegiatan penambangan (*site lockout*).
4. **119.4 Survey & Volumetrik Stok 3D**:
   - Integrasi hasil fotogrametri drone / survey LiDAR point cloud.
   - Pemodelan permukaan mesh 3D triangulasi (TIN) untuk menghitung volume timbunan stockpile aktual vs rencana.
   - Rekonsiliasi bulanan varians volume topografi vs timbangan digital jembatan timbang (Fase 94.1) serta pencatatan beban depresiasi peralatan fotogrametri.
5. **119.5 Barge & Marine Ops (Quarry/Curah)**:
   - Pengoperasian tongkang muatan curah batuan/nikel/pasir (`lgx_vessels`).
   - Rekonsiliasi survei draf hidrostatik 6-titik (*Forward, Midship, Aft* di sisi *Port* dan *Starboard*), koreksi kerapatan air (*density correction*), koreksi defleksi hogging/sagging.
   - Rantai lacak kustodi (*chain of custody*) terenkripsi dari jembatan timbang pelabuhan muat hingga pelabuhan bongkar.

---

## 4. Kesimpulan Kesiapan Memulai L.1 & L.2

Dengan baseline master yang telah stabil dan lulus merge-guard penuh pada PR #83, seluruh kebutuhan arsitektur, algoritma numerik, model data, dan batas toleransi untuk Fase 119 telah siap untuk dituangkan ke dalam Definisi Siap (*Definition of Ready* / DoR) dan Dokumen Keputusan Arsitektur (ADR-0119).
