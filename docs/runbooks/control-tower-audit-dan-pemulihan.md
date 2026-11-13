# Runbook Audit Dua Sumber & Pemulihan Control Tower (`tower:audit`)

Runbook ini digunakan oleh Site Reliability Engineer (SRE), Logistics Admin, Auditor Internal, dan on-call engineer saat melakukan verifikasi periodik maupun saat menanggapi kegagalan pemeriksaan integritas pada modul `ControlTower` (`sct_*`).

---

## 1. Tujuan & Frekuensi Eksekusi Audit

Perintah utama:
```bash
php artisan tower:audit
```
Perintah ini terhubung ke kontrak quality gate (`AuditCommandContractTest`) dan wajib mengembalikan exit code `0` pada kondisi sehat serta exit code `1` ketika terdapat pelanggaran invarian atau data hasil injeksi fixture sabotase (`ControlTowerAuditCorruptionFixture`).

**Jadwal disarankan:**
- Pra-publikasi S&OP bulanan (sebelum transisi ke status `published`).
- Harian otomatis pasca-pemindaian malam (`tower:alerts-scan`, `tower:risk-scan`, `tower:kpi`).
- Segera setelah proses migrasi data atau impor ulang inventori `Wms`.

---

## 2. Daftar Invarian yang Diaudit (`ControlTowerAuditReportService`)

| Kode Cek | Tabel / Sumber Diperiksa | Kondisi Lulus (Invariant) | Kondisi Gagal (Violation) |
| :--- | :--- | :--- | :--- |
| **INV-CT-01** | `sct_echelon_stocks` | `on_hand_qty >= 0`, `in_transit_qty >= 0`, `reserved_qty >= 0` | Terdapat kuantitas negatif di salah satu kolom stok. |
| **INV-CT-02** | `sct_echelon_stocks` | `reserved_qty <= (on_hand_qty + in_transit_qty)` | Reservasi ATP melampaui total stok fisik + transit pada simpul. |
| **INV-CT-03** | `sct_order_promises` | `atp_confirmed_qty <= requested_qty` | Janji ATP dialokasikan melebihi kuantitas pesanan asli. |
| **INV-CT-04** | Agregat `sct_order_promises` vs `sct_echelon_stocks` | Total ATP aktif per `item_code` seimbang dengan kapasitas pasokan tersedia + MPS. | Over-promise lintas pesanan pada SKU tertentu. |
| **INV-CT-05** | `sct_sop_cycles` | Seluruh baris berstatus di dalam `SopCycleStateMachine::states()` | Ditemukan siklus S&OP berstatus tidak sah (`corrupted_unknown_state` dsb.). |
| **INV-CT-06** | Sertifikat & Idempotensi Janji | `idempotency_key` aktif unik; `certificate_hash` tervalidasi | Duplikasi kunci idempotensi atau sertifikat rusak. |

---

## 3. Prosedur Diagnostik Ketika `tower:audit` Gagal (Exit Code 1)

### Langkah 1 — Inspeksi Laporan Terperinci
Jalankan diagnostik via artisan tinker atau uji regresi untuk membaca daftar pelanggaran dari `ControlTowerAuditReportService::buildDetailedReport()`:
```bash
php artisan tinker --execute="dump(app(\Modules\ControlTower\Application\Services\ControlTowerAuditReportService::class)->buildDetailedReport());"
```
Output berisi:
- `status`: `'VIOLATION'`
- `checked_at_counts`: jumlah baris teraudit pada `echelons`, `promises`, `alerts`, dan `sop_cycles`
- `violations`: array deskripsi eksplisit pelanggaran yang terdeteksi

### Langkah 2 — Identifikasi Konflik Alokasi per SKU
Jika pelanggaran terkait `INV-CT-02` atau `INV-CT-04`:
```bash
php artisan tinker --execute="dump(app(\Modules\ControlTower\Application\Services\ControlTowerAllocationConflictService::class)->detectOverAllocatedItems());"
```
Catat seluruh `item_code` yang memiliki selisih alokasi positif.

### Langkah 3 — Prosedur Pemulihan per Jenis Pelanggaran

1. **Kasus Over-Allocation / Stok Echelon Tertinggal dari WMS:**
   - Sinkronkan ulang posisi stok nyata dari modul `Wms` lewat `ControlTowerWmsSyncService` / `ItemAvailabilityReader`.
   - Segarkan perhitungan bucket ATP/CTP dengan menjalankan ulang horizon promise:
     ```bash
     php artisan tower:atp-refresh
     ```
   - Bila janji pesanan lama tidak lagi terdukung pasokan fisik, turunkan statusnya melalui revisi janji pesanan dengan mengalihkan sisa kebutuhan ke jalur CTP produksi atau menjadwalkan ulang tanggal janji.

2. **Kasus Over-Promise Buatan atau Data Rusak (`SABOTAGE-*`):**
   - Pastikan kerusakan bukan berasal dari sisa eksekusi fixture uji di lingkungan non-produksi.
   - Karantina baris janji bermasalah, evaluasi ulang order terbuka dengan `PromiseQuoteInterface`, dan informasikan ke tim Customer Service bila terdapat perubahan tanggal pengiriman.

3. **Kasus Status Siklus S&OP Ilegal (`INV-CT-05`):**
   - Periksa riwayat transisi terakhir di tabel `sct_sop_events` untuk siklus tersebut.
   - Koreksi status siklus hanya ke status terakhir yang sah sebelum anomali terjadi sesuai state machine `SopCycleStateMachine`, dan catat entri audit korektif di `sct_sop_events`.

4. **Kasus Forecast Override Tidak Konsisten / Sertifikat Mismatch:**
   - Jalankan ulang pemilihan model statistik untuk mengembalikan baseline konsensus:
     ```bash
     php artisan tower:forecast-select
     ```
   - Jika nilai override dari planner perlu dipertahankan, periksa deviasi melalui `/control-tower/forecast` agar tercatat dengan alasan bisnis yang valid.

---

## 4. Verifikasi Pasca-Pemulihan

Setelah langkah perbaikan diterapkan:
1. Jalankan ulang `php artisan tower:audit` dan pastikan output akhir menampilkan:
   `Supply Chain Control Tower audit PASSED with 0 discrepancy.` (exit code `0`).
2. Jalankan pemindaian risiko & peringatan agar status disrupsi termutakhirkan:
   ```bash
   php artisan tower:risk-scan
   php artisan tower:alerts-scan
   php artisan tower:kpi
   ```
3. Catat kejadian insiden, akar masalah, dan bukti exit code `0` ke log operasional pemeliharaan sistem.


---

## 4. Prosedur Operasional Standar Penanganan Insiden Control Tower

### 4.1 Insiden A: `php artisan tower:audit` Mengembalikan Exit Code `1` (`DISCREPANCY`)
Gejala: `discrepancy_count > 0` akibat `invalidStock > 0`, `invalidPromises > 0`, `atpOverPromises > 0`, atau `invalidSopStates > 0`.

Langkah triase & pemulihan deterministik:
1. Jalankan kueri diagnostik baca-saja untuk mengisolasi kategori pelanggaran:
   - **Over-reserve:** Cari baris pada `sct_echelon_stocks` dengan `reserved_qty > (on_hand_qty + in_transit_qty)`. Cocokkan dengan reservasi `Wms` lewat `StockAvailabilityReader` lalu jalankan rekonsiliasi kuantitas reservasi sebelum membuka kuotasi baru.
   - **ATP Over-promise:** Kelompokkan `SUM(atp_confirmed_qty)` per `item_code` pada `sct_order_promises` dan bandingkan terhadap `SUM(on_hand_qty + in_transit_qty)` di `sct_echelon_stocks`. Untuk janji ganda akibat replay non-idempoten dari client lama, periksa `idempotency_key` dan terapkan penyesuaian jadwal ke periode CTP berikutnya.
   - **Status S&OP Korup:** Periksa baris `sct_sop_cycles` dengan `status NOT IN SopCycleStateMachine::states()`. Gunakan `TransitionSopCycleAction` disertai alasan audit untuk mengembalikan siklus ke tahap kanonik terakhir sesuai `sct_sop_events`.
2. Jalankan ulang `php artisan tower:audit` dan pastikan kode keluar kembali `0`.

### 4.2 Insiden B: Kegagalan Integritas Zero-Mutation pada Twin What-If
Gejala: `before_hash !== after_hash` pada eksekusi `TwinWhatIfOrchestratorService`.
1. Periksa listener event atau observer model Eloquent di luar `SimulationRunnerService` yang mungkin terpicu selama tick simulasi.
2. Pastikan seluruh skenario dijalankan di dalam `DB::transaction` tertutup atau kloning state in-memory tanpa menulis baris permanen selain log hasil skenario yang memang diminta di jalur penyimpanan laporan.
3. Ulangi pengujian `Slice6TwinAndKpiTest::test_what_if_twin_runs_in_zero_mutation_sandbox_even_without_seeded_sim_run`.

### 4.3 Insiden C: Banjir Alert & Duplikasi Event (`tower.alert.raised.v1`)
1. Pastikan `AlertScannerService` selalu mengisi `dedup_key` deterministik berbentuk `<category>:<entity_ref>:<window_bucket>`.
2. Apabila ada alert tersangkut pada status `active`, ajukan proposal mitigasi melalui `ExecuteAutonomousReallocationPlaybookAction::proposeReallocation()` dan lakukan persetujuan empat mata lewat `approveReallocation()` oleh perencana berbeda sebelum menandai `mitigated`.

### 4.4 Insiden D: Deviasi Ekstrem & Pembatalan Override Forecast (`X53-4`)
1. Setiap override dengan deviasi absolut melebihi `20%` (`deviation_bps > 2000`) wajib berstatus `pending_approval` dan tidak langsung mengubah `sct_demand_forecasts.forecast_qty` hingga `ApprovalEngineInterface::approve()` dipanggil oleh penyetuju independen.
2. Untuk membatalkan override yang keliru, panggil `POST /control-tower/forecast/override/{override}/revert` atau `ForecastOverrideService::revert()`; sistem memulihkan `forecast_qty = previous_qty`, mengisi `reverted_at`, dan mempertahankan catatan audit historis tanpa menghapus baris.


---

## 5. Penanganan Penyimpangan Adaptor Lintas Modul & Pemulihan Antrean Approval

### 5.1 Penyimpangan Adaptor Lintas Modul (`Wms`, `Manufacturing`, `Distribution`, `Procurement`)
Apabila hasil kuotasi ATP/CTP (`PromiseQuoteService`), skor pemasok (`SupplierScorecardOrchestratorService`), atau KPI OTIF (`ControlTowerKpiDrilldownService`) tampak tidak sinkron dengan modul hulu/hilir, lakukan diagnostik per kontrak:

1. **`WmsItemAvailabilityReader` vs `EchelonItemAvailabilityReader`:**
   - Periksa binding aktif `ItemAvailabilityReader` di `ControlTowerServiceProvider`: `WmsItemAvailabilityReader` harus aktif untuk membaca posisi karantina nyata.
   - Bandingkan total `wms_stock_balances` yang berstatus tersedia vs karantina terhadap nilai `on_hand` dan `quarantine_hold` pada kuotasi terakhir.
   - Jalankan `php artisan tower:atp-refresh --item=<SKU>` setelah transaksi pelepasan karantina QC selesai di modul WMS.

2. **`ManufacturingCapacityAndMaterialProvider`:**
   - Pastikan baris `mfg_work_centers` aktif dan memiliki kapasitas menit/unit valid; verifikasi `mfg_mrp_plans` untuk rentang 12 minggu dari `as_of_date`.
   - Bila pesanan selalu berhenti pada `ATP_SUFFICIENT_ON_TIME` atau langsung `BOTTLENECK_SHORTFALL` walau ada slot mesin, periksa status `mfg_production_orders` yang mungkin sudah mengonsumsi seluruh kapasitas minggu berjalan.

3. **`DistributionOpenDemandProvider`:**
   - Verifikasi kueri `dist_orders` / `dist_order_lines` tidak mengecualikan status aktif yang semestinya terkomitmen (`open`, `confirmed`, `allocated`) dan memakai `requested_date` atau fallback `created_at`.
   - Untuk KPI OTIF, cek `dist_shipments.pod_at` sebagai bukti tanggal kirim riil sebelum jatuh ke `delivered_at`.

4. **`ProcurementSupplierLeadTimeStatistics`:**
   - Pastikan penerimaan GRN di `prc_receiving_reports` memiliki status `accepted`, `inspected`, atau `open` agar ikut terhitung ke `samples`, `mean_days`, `sigma_days`, dan `on_time_permil`.
   - Apabila sebuah item belum punya riwayat GRN, output resmi bernilai `0` pada seluruh metrik numerik (`AT_RISK`) dan tidak boleh memicu division-by-zero error.

### 5.2 Pemulihan Proposal Tertahan di `ApprovalEngineInterface`
- **Proposal Safety Stock (`sct_safety_stock_policies.status = 'proposed'`):**
  - Verifikasi bahwa penyetuju (`decided_by_user_id`) memiliki role `admin` atau `logistics_admin` DAN bukan pengaju (`proposed_by_user_id`).
  - Apabila pengaju tidak lagi aktif di organisasi, admin kedua mengeksekusi penolakan eksplisit (`decision = 'reject'`) agar kebijakan aktif lama tetap terjaga sebelum pengaju baru mengirim ulang `POST /control-tower/safety-stock/propose`.
- **Override Forecast (`sct_forecast_overrides.status = 'pending_approval'`):**
  - Untuk deviasi $\le 20\%$, sistem langsung menerapkan nilai baru. Untuk deviasi $> 20\%$, periksa catatan `approval_id` pada `TaskInboxService` dan eksekusi approve/reject atau gunakan `POST /forecast/override/{override}/revert` bila perlu pembatalan segera.

### 5.3 Audit Outbox Event Schema Registry
Ketiga event Control Tower terdaftar permanen di `Modules\Core\Domain\Events\EventCatalog`:
- `tower.promise.made.v1` — Wajib memuat `promise_code`, `order_reference`, `item_code`, `requested_qty`, `atp_confirmed_qty`, `ctp_manufacturing_qty`, `reason_code`.
- `tower.sop.published.v1` — Wajib memuat `cycle_code`, `period`, `demand_plan_qty`, `supply_plan_qty`, `capacity_utilization_bps`.
- `tower.alert.raised.v1` — Wajib memuat `alert_code`, `dedup_key`, `severity`, `category`, `affected_orders_count`.

Apabila `EventCatalogContractTest` gagal pada lingkungan pengembangan baru, pastikan konstanta `EVENT_NAME` / `EVENT_ALERT_RAISED` / `EVENT_SOP_PUBLISHED` tetap identik antara service penerbit dan daftar produser/konsumen di `EventCatalog.php`.
