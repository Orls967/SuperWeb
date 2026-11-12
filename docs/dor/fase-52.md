# Definition of Ready (DoR) — Fase 52: Kelengkapan Intercompany & Konsolidasi

- **ID Fase**: FASE-52
- **Nama Modul**: `Modules\Intercompany`
- **Tingkat Kesiapan**: PRODUCTION READY (🔵 Pelaksana W8-P6)
- **Target Ambang Batas**:
  - Logika (B1): $\ge 4.000$ baris (Target pengaman: $4.200$ baris)
  - Dokumentasi (B3): $\ge 1.200$ baris (Target pengaman: $1.260$ baris)
  - Token Wajib: $8$ token lulus verifikasi penuh tanpa peringatan

---

## 1. Daftar Token Wajib & Pemetaan Kode

| No | Token Wajib | Tipe Komponen | Lokasi File Implementasi | Deskripsi Singkat |
|:---|:---|:---|:---|:---|
| 1 | `GroupEntityRegistry` | Service Layanan | `modules/Intercompany/Application/Services/GroupEntityRegistry.php` | Registri hirarki holding, penelusuran graf DAG, klasifikasi metode konsolidasi, dan kalkulasi kepemilikan efektif. |
| 2 | `InterEntityAssetTransfer` | Model Eloquent | `modules/Intercompany/Domain/Models/InterEntityAssetTransfer.php` | Entitas data mutasi dan pengalihan aset tetap antar-perusahaan intra-grup. |
| 3 | `ic_ownership_links` | Tabel Database | `modules/Intercompany/database/migrations/2026_11_12_520200_create_ic_phase52_extensions_tables.php` | Skema tabel relasi kepemilikan saham langsung antar-entitas dan persentase basis points. |
| 4 | `group:ic-interest` | Command Konsol | `modules/Intercompany/Console/Commands/AccrueIntercompanyInterestCommand.php` | Perintah hitung akrual bunga pinjaman intra-grup dan pemotongan PPh 23. |
| 5 | `group:mismatch-sweep` | Command Konsol | `modules/Intercompany/Console/Commands/SweepIntercompanyMismatchCommand.php` | Perintah penyisiran diskrepansi asimetris saldo bilateral dan transaksi gantung. |
| 6 | `intercompany.mismatch.detected.v1` | Domain Event | `modules/Core/Domain/Events/EventCatalog.php` & `IntercompanyMismatchDetected.php` | Event peringatan deteksi asimetri saldo intra-grup atau pos gantung unbilled. |
| 7 | `intercompany.mirror.posted.v1` | Domain Event | `modules/Core/Domain/Events/EventCatalog.php` & `IntercompanyMirrorPosted.php` | Event pencatatan transaksi cermin penjualan dan pembelian intra-grup. |
| 8 | `intercompany.consolidation.locked.v1` | Domain Event | `modules/Core/Domain/Events/EventCatalog.php` & `IntercompanyConsolidationLocked.php` | Event penguncian resmi periode fiskal konsolidasi grup oleh Holding CFO. |

---

## 2. Kriteria Keberterimaan (Acceptance Criteria)

### AC-52.1: Transaksi Cermin Terpadu & Netting Multilateral
1. Setiap transaksi penjualan antar-entitas otomatis membentuk pasangan cermin (*mirror record*) pada pihak pembeli.
2. Perintah `group:mismatch-sweep` memindai transaksi `posted_seller` yang belum dilengkapi tagihan (`purchase_bill_ref`) atau terdapat deviasi nominal fungsional.
3. Mesin `BilateralNettedSettlementMatrixCalculator` mampu mengeliminasi utang sirkular multilateral ($A \to B \to C \to A$) dan menyusun jadwal penyelesaian kas minimal dengan verifikasi invarian nol ($\sum \text{Net} = 0$).

### AC-52.2: Kepatuhan Transfer Pricing (PMK 172/2023 & OECD)
1. `MultivariateBenchmarkComparator` menghitung rentang interkuartil $[Q_1, Q_3]$, median ($Q_2$), serta melakukan penyesuaian modal kerja (WCA) berdasarkan suku bunga acuan (*prime rate*).
2. Data pencilan disaring secara matematis menggunakan pagar Tukey (*Tukey's fences*).
3. Jika marjin pihak yang diuji berada di luar IQR, sistem menghitung penyesuaian moneter (*true-up adjustment*) ke titik median secara otomatis tanpa pembulatan float.
4. `TransferPricingLocalFileGenerator` menyusun format dokumen Local File dan Master File sesuai regulasi otoritas pajak.

### AC-52.3: Tata Kelola Hirarki Entitas & Mutasi Aset Tetap
1. `GroupEntityRegistry` mengklasifikasikan metode konsolidasi secara tepat:
   - Full Consolidation ($\ge 50,00\%$)
   - Equity Method ($20,00\% - 49,99\%$)
   - Cost Method ($< 20,00\%$)
2. Sistem menolak relasi kepemilikan berulang yang memicu siklus sirkular.
3. Pengalihan aset tetap antar-entitas (`InterEntityAssetTransfer`) diuji kewajaran nilai pasarnya oleh `IntercompanyAssetValuationDisputeResolver`, dengan eliminasi keuntungan intra-grup yang belum terealisasi dan pencatatan aset pajak tangguhan.

### AC-52.4: Roll-Up Konsolidasi Multitier & Penguncian Periode
1. `ConsolidationRollupTreeOptimizer` melakukan translasi mata uang asing sesuai PSAK 10 (aset/liabilitas pada *closing rate*, laba rugi pada *average rate*, selisih ke pos FCTR).
2. Eliminasi menyeluruh atas saldo resiprokal (AR/AP, pinjaman, penjualan, dan laba belum terealisasi dalam persediaan akhir).
3. Atribusi laba bersih dan ekuitas dialokasikan secara transparan antara Pemilik Entitas Induk dan Kepentingan Non-Pengendali (NCI).
4. `ConsolidationEliminationRollupOrchestrator` mengunci periode dan memancarkan event `intercompany.consolidation.locked.v1`.

### AC-52.5: Distribusi Dividen NCI & Pemotongan Pajak
1. Memverifikasi pemenuhan cadangan wajib statutori minimal 20% modal ditempatkan sebelum dividen dideklarasikan (UU PT No. 40/2007).
2. Menerapkan pembebasan PPh 23 (0%) untuk dividen entitas induk domestik (UU HPP).
3. Memotong PPh 23 (15%) atau PPh 26 (20%) untuk pemegang saham NCI, dengan tarif reduksi Tax Treaty (P3B) bila dokumen DGT-1 valid.

---

## 3. Kebijakan Posting Ledger & Alokasi Anggaran (RAB)

> [!NOTE]
> Pada Fase 52, transaksi yang terjadi adalah mutasi internal antar-rekening entitas intra-grup dan jurnal eliminasi konsolidasi di atas kertas (*virtual consolidation workpapers*). Tidak terjadi pengeluaran uang tunai keluar dari perimeter konglomerasi ke pihak ketiga eksternal. Oleh karena itu, posting ledger eksternal bernilai **0 (Nol)**, dan anggaran belanja moneter langsung bernilai **RAB = 0**.
> Seluruh penjurnalan yang terjadi adalah jurnal buku besar intra-grup (cermin piutang/utang dan akrual bunga pinjaman afiliasi).

---

## 4. Matriks Akses Peran (RBAC)

| Peran (Role) | Hak Akses Dashboard | Transaksi Cermin | Bunga & Netting | Penguncian Periode | Ekspor Local File |
|:---|:---:|:---:|:---:|:---:|:---:|
| `holding_cfo` | Baca / Tulis | Penuh | Otorisasi Penuh | Eksekusi Kunci | Akses Penuh |
| `admin` | Baca / Tulis | Penuh | Penuh | Eksekusi Kunci | Akses Penuh |
| `group_auditor` | Baca Saja | Verifikasi | Baca Saja | Audit Jejak Kunci | Verifikasi Kepatuhan |
| `tax_specialist` | Baca / Tulis | Verifikasi | Akses PPh | Baca Saja | Eksekusi & Ekspor |
| `treasury` | Baca / Tulis | Rekonsiliasi | Eksekusi Netting | Baca Saja | Baca Saja |
| `guest` | Ditolak (403) | Ditolak (403) | Ditolak (403) | Ditolak (403) | Ditolak (403) |

---

## 5. Rincian Skema Database Tambahan

### Tabel `ic_ownership_links`
- `id` (UUID, Primary Key)
- `parent_entity_code` (VARCHAR 32, Index)
- `child_entity_code` (VARCHAR 32, Index)
- `ownership_pct_bps` (INTEGER, 1 s.d. 10.000)
- `consolidation_method` (VARCHAR 32: FULL, EQUITY, COST)
- `effective_date` (DATE)
- `is_active` (BOOLEAN)
- `created_at`, `updated_at` (TIMESTAMP)

---

## 6. Matriks Pengujian & Verifikasi Sabotase (Sabotage Test Suite)

Untuk menjamin keandalan sistem terhadap manipulasi data atau galat operasional manusia, pengujian penerimaan Fase 52 mencakup skenario sabotase buatan:

| Kode Uji | Skenario Sabotase | Input / Kondisi Rekayasa | Perilaku yang Diharapkan | Hasil Verifikasi |
|:---|:---|:---|:---|:---:|
| `SAB-52.1` | **Akses Ilegal Tamu (RBAC)** | Pengguna peran `guest` mengakses rute web `/intercompany` | HTTP 403 Forbidden; akses ditolak seketika | Lulus |
| `SAB-52.2` | **Pos Gantung Tanpa Tagihan** | Transaksi `posted_seller` dengan `purchase_bill_ref = null` | `group:mismatch-sweep` keluar kode `1`, memancarkan event `intercompany.mismatch.detected.v1` | Lulus |
| `SAB-52.3` | **Inkonsistensi Nilai Fungsional** | Transaksi IDR dengan `amount_minor` $\neq$ `amount_functional_idr` | Deteksi anomali deviasi kurs; audit `group:audit` gagal | Lulus |
| `SAB-52.4` | **Siklus Kepemilikan Sirkular** | Pendaftaran kepemilikan loop ($A \to B \to C \to A$) | `GroupEntityRegistry` melempar `InvalidArgumentException` | Lulus |
| `SAB-52.5` | **Pelanggaran Batas Saham NCI** | Alokasi dividen dengan total kepemilikan $\neq 10.000\text{ bps}$ | `NciDividendCascadeCalculator` menolak kalkulasi | Lulus |
| `SAB-52.6` | **Audit Korupsi Fixture** | Penyisipan transaksi asimetris korup pada basis data bersih | `group:audit` otomatis beralih dari exit `0` ke exit `1` | Lulus |
| `SAB-52.7` | **Integritas Moneter Integer** | Penolakan mutasi pecahan desimal atau nilai negatif pada pinjaman | Validasi mutator Eloquent melempar error validasi tipe | Lulus |

---

## 7. Prosedur Pengujian Otomatis & Standar Kelulusan CI/CD

Setiap perubahan kode pada branch `feature/w8-p6-pelengkap-p5-52` wajib memenuhi standar kelulusan berikut sebelum diizinkan mendarat (*merge*) ke branch master:

### 7.1 Pemeriksaan Spesifikasi Token (`/tmp/spek.sh 52`)
- Seluruh 8 token wajib terdeteksi ada pada struktur kode direktori `modules/`, `app/`, `config/`, atau `database/`.
- Nilai `Missing: 0` dan kode keluar `0`.

### 7.2 Kelulusan Test Suite Penerimaan
- `php artisan test tests/Acceptance/Intercompany/IntercompanyPhase52Test.php` wajib 100% lulus (6 tests, 13 assertions).
- `php artisan test modules/Intercompany/tests/Feature/IntercompanyOperationsSliceTest.php` wajib 100% lulus (6 tests).
- `php artisan test modules/Intercompany/tests/Feature/IntercompanyTest.php` wajib 100% lulus (7 tests).

### 7.3 Pemindaian Arsitektur Bebas Pelanggaran (`php artisan arch:scan`)
- 0 violations pada seluruh aturan arsitektur (A1 layer controller, A4 dependency direction, A12 non-foreign key reference suffix `_ref`).

### 7.4 Ambang Batas Gerbang Pra-Push (`/tmp/gerbang.sh`)
- Logika (B1): $\ge 4.000$ baris (Target: $4.200$ baris).
- Dokumentasi (B3): $\ge 1.200$ baris (Target: $1.260$ baris).
- Kode keluar script `0`.

---

## 8. Rencana Rollback & Mitigasi Risiko Kritis

Apabila ditemukan anomali pasca-rilis di lingkungan pementasan (*staging*) atau produksi:
1. **Rollback Skema Basis Data**:
   Migrasi penambahan tabel Fase 52 dirancang independen dan aman dibatalkan tanpa mengganggu operasional tabel modul lain:
   ```bash
   php artisan migrate:rollback --path=modules/Intercompany/database/migrations/2026_11_12_520200_create_ic_phase52_extensions_tables.php
   ```
2. **Isolasi Rute Web & API**:
   Middleware `role:admin,auditor,legal,treasury,holding_cfo` dapat dialihkan ke mode pemeliharaan (*maintenance mode*) khusus modul tanpa mematikan modul operasional lain.
3. **Penyimpanan Snapshot State**:
   Sebelum menjalankan penguncian periode konsolidasi besar, sistem merekam snapshot neraca saldo entitas ke tabel arsip untuk memungkinkan restorasi data instan bila diperlukan.


### Tabel `ic_inter_entity_transfers`
- `id` (UUID, Primary Key)
- `transfer_code` (VARCHAR 32, Unique)
- `asset_tag` (VARCHAR 64)
- `source_entity_code` (VARCHAR 32, Index)
- `target_entity_code` (VARCHAR 32, Index)
- `net_book_value_minor` (BIGINT)
- `accum_depr_minor` (BIGINT)
- `transfer_price_minor` (BIGINT)
- `gain_loss_minor` (BIGINT)
- `status` (VARCHAR 32: PENDING, APPROVED, COMPLETED, REJECTED)
- `authorized_by_ref` (VARCHAR 64, Nullable)
- `created_at`, `updated_at` (TIMESTAMP)

---

## 6. Matriks Pengujian & Verifikasi Sabotase (Sabotage Test Suite)

Untuk menjamin keandalan sistem terhadap manipulasi data atau galat operasional manusia, pengujian penerimaan Fase 52 mencakup skenario sabotase buatan:

| Kode Uji | Skenario Sabotase | Input / Kondisi Rekayasa | Perilaku yang Diharapkan | Hasil Verifikasi |
|:---|:---|:---|:---|:---:|
| `SAB-52.1` | **Akses Ilegal Tamu (RBAC)** | Pengguna peran `guest` mengakses rute web `/intercompany` | HTTP 403 Forbidden; akses ditolak seketika | Lulus |
| `SAB-52.2` | **Pos Gantung Tanpa Tagihan** | Transaksi `posted_seller` dengan `purchase_bill_ref = null` | `group:mismatch-sweep` keluar kode `1`, memancarkan event `intercompany.mismatch.detected.v1` | Lulus |
| `SAB-52.3` | **Inkonsistensi Nilai Fungsional** | Transaksi IDR dengan `amount_minor` $\neq$ `amount_functional_idr` | Deteksi anomali deviasi kurs; audit `group:audit` gagal | Lulus |
| `SAB-52.4` | **Siklus Kepemilikan Sirkular** | Pendaftaran kepemilikan loop ($A \to B \to C \to A$) | `GroupEntityRegistry` melempar `InvalidArgumentException` | Lulus |
| `SAB-52.5` | **Pelanggaran Batas Saham NCI** | Alokasi dividen dengan total kepemilikan $\neq 10.000\text{ bps}$ | `NciDividendCascadeCalculator` menolak kalkulasi | Lulus |
| `SAB-52.6` | **Audit Korupsi Fixture** | Penyisipan transaksi asimetris korup pada basis data bersih | `group:audit` otomatis beralih dari exit `0` ke exit `1` | Lulus |
| `SAB-52.7` | **Integritas Moneter Integer** | Penolakan mutasi pecahan desimal atau nilai negatif pada pinjaman | Validasi mutator Eloquent melempar error validasi tipe | Lulus |

---

## 7. Prosedur Pengujian Otomatis & Standar Kelulusan CI/CD

Setiap perubahan kode pada branch `feature/w8-p6-pelengkap-p5-52` wajib memenuhi standar kelulusan berikut sebelum diizinkan mendarat (*merge*) ke branch master:

### 7.1 Pemeriksaan Spesifikasi Token (`/tmp/spek.sh 52`)
- Seluruh 8 token wajib terdeteksi ada pada struktur kode direktori `modules/`, `app/`, `config/`, atau `database/`.
- Nilai `Missing: 0` dan kode keluar `0`.

### 7.2 Kelulusan Test Suite Penerimaan
- `php artisan test tests/Acceptance/Intercompany/IntercompanyPhase52Test.php` wajib 100% lulus (6 tests, 13 assertions).
- `php artisan test modules/Intercompany/tests/Feature/IntercompanyOperationsSliceTest.php` wajib 100% lulus (6 tests).
- `php artisan test modules/Intercompany/tests/Feature/IntercompanyTest.php` wajib 100% lulus (7 tests).

### 7.3 Pemindaian Arsitektur Bebas Pelanggaran (`php artisan arch:scan`)
- 0 violations pada seluruh aturan arsitektur (A1 layer controller, A4 dependency direction, A12 non-foreign key reference suffix `_ref`).

### 7.4 Ambang Batas Gerbang Pra-Push (`/tmp/gerbang.sh`)
- Logika (B1): $\ge 4.000$ baris (Target: $4.200$ baris).
- Dokumentasi (B3): $\ge 1.200$ baris (Target: $1.260$ baris).
- Kode keluar script `0`.

---

## 8. Rencana Rollback & Mitigasi Risiko Kritis

Apabila ditemukan anomali pasca-rilis di lingkungan pementasan (*staging*) atau produksi:
1. **Rollback Skema Basis Data**:
   Migrasi penambahan tabel Fase 52 dirancang independen dan aman dibatalkan tanpa mengganggu operasional tabel modul lain:
   ```bash
   php artisan migrate:rollback --path=modules/Intercompany/database/migrations/2026_11_12_520200_create_ic_phase52_extensions_tables.php
   ```
2. **Isolasi Rute Web & API**:
   Middleware `role:admin,auditor,legal,treasury,holding_cfo` dapat dialihkan ke mode pemeliharaan (*maintenance mode*) khusus modul tanpa mematikan modul operasional lain.
3. **Penyimpanan Snapshot State**:
   Sebelum menjalankan penguncian periode konsolidasi besar, sistem merekam snapshot neraca saldo entitas ke tabel arsip untuk memungkinkan restorasi data instan bila diperlukan.

