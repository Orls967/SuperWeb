# Katalog Galat, Invarian & Resolusi — Control Tower & S&OP (Fase 53)

Dokumen ini mendaftar seluruh kode galat terstruktur, pengecualian idempotensi, batas toleransi sertifikat, dan langkah mitigasi operasional di modul `ControlTower` serta adaptor kontrak lintas modulnya.

---

## 1. Katalog Kode Galat Domain & Application

| Kode Galat / Exception | Kelas / Layanan Sumber | Penyebab Teknis | Tindakan Mitigasi Operasional |
| :--- | :--- | :--- | :--- |
| `INVALID_QUANTITY` | `AtpCtpNetter`, `PromiseQuoteService` | Kuantitas permintaan `<= 0` atau periode permintaan `< 1`. | Tolak pada layer `QuotePromiseRequest`; pastikan order line meminta bilangan bulat positif `>= 1`. |
| `HORIZON_EXCEEDED` | `AtpCtpNetter` | Permintaan jatuh di luar panjang horizon bucket periode aktif (`requestedPeriod > T`). | Perpanjang horizon bucket perencanaan (`periods`) atau jadwalkan ulang tanggal minta pelanggan. |
| `INSUFFICIENT_CAPACITY` | `AtpCtpNetter` | Defisit ATP terbentur batas kapasitas kumulatif lini produksi pabrik (`capacity_cumulative`). | Jalankan simulasi S&OP skenario penambahan shift atau alihkan permintaan ke periode berikutnya. |
| `INSUFFICIENT_MATERIAL` | `AtpCtpNetter` | Kapasitas pabrik cukup tetapi bahan baku BOM kumulatif (`material_cumulative`) belum tersedia. | Percepat jadwal rilis PO bahan baku via modul `Procurement` / MRP run sebelum menjanjikan order. |
| `IdempotencyConflictException` | `PromiseQuoteService` | Pemanggilan `quote()` mengulang `idempotency_key` sama namun dengan parameter payload (SKU/qty/periode) berbeda. | Gunakan `idempotency_key` unik per permintaan baru atau pertahankan payload identik saat melakukan retry jaringan. |
| `INVALID_FORECAST_HISTORY` | `DemandForecastEngine`, `ForecastModelSelector` | Panjang deret historis `< 3` periode atau `itemCode` kosong. | Lengkapi minimal 3 periode data penjualan aktual (disarankan >= 6 periode agar backtest berjalan penuh). |
| `NEGATIVE_DEMAND_HISTORY` | `DemandForecastEngine`, `ForecastModelSelector` | Ditemukan nilai permintaan historis `< 0`. | Koreksi entri retur agar deret permintaan non-negatif (`>= 0`). |
| `NEGATIVE_FORECAST_QTY` | `ForecastOverrideService`, `OverrideDemandForecastAction` | Kuantitas override dari planner bernilai `< 0`. | Masukkan target kuantitas konsensus bilangan bulat `>= 0`. |
| `OVERRIDE_REASON_REQUIRED` | `ForecastOverrideService`, `OverrideDemandForecastAction` | Alasan bisnis override kosong atau `< 5` karakter. | Sertakan justifikasi komersial/promo minimal 5 karakter agar lolos jejak audit S&OP. |
| `INVALID_OVERRIDE_REVERT` | `ForecastOverrideService` | Percobaan *revert* pada override yang berstatus ditolak atau sudah dikembalikan. | Periksa riwayat status override pada `/control-tower/forecast` sebelum membalik perubahan. |
| `INVALID_PROMISE_REQUEST` | `AtpCtpCalculator` | `itemCode` kosong atau `requestedQty <= 0`. | Validasi SKU terdaftar dan kuantitas positif sebelum memanggil kalkulator. |
| `INVALID_SAFETY_STOCK_INPUT` | `SafetyStockAbcXyzOptimizer`, `MultiEchelonSafetyStock` | Deret historis permintaan kosong atau `avgLeadTimeDays < 1`. | Pastikan pemasok utama memiliki lead time >= 1 hari atau nilai default parameter perencanaan valid. |
| `FOUR_EYES_VIOLATION` | `SopPublicationOrchestrator`, `DecideSafetyStockProposalAction` | Aktor yang menyetujui/mempublikasikan identik dengan pembuat proposal/pengaju S&OP. | Pisahkan akun perencana (`planner`) dan peninjau akhir (`logistics_admin` / `admin`) sesuai gerbang four-eyes. |
| `INVALID_SOP_TRANSITION` | `SopCycleStateMachine` | Perpindahan status S&OP melompati tahap berurutan atau berpindah dari state terminal (`closed` / `archived`). | Ikuti urutan kanonik `data_gathering -> demand_review -> supply_review -> pre_sop -> exec_sop -> published -> closed`. |
| `SOP_ROLLBACK_REASON_REQUIRED` | `SopCycleStateMachine`, `SopPublicationOrchestrator` | Transisi mundur (misalnya `supply_review -> demand_review`) dilakukan tanpa alasan non-kosong. | Isi kolom `notes`/`reason` yang menjelaskan gap permintaan atau kapasitas sebelum mengembalikan tahap S&OP. |
| `INVALID_TWIN_INPUT` | `WhatIfTwinSimulator` | `durationDays < 1` atau `baselineOtifBps` di luar rentang `0..10000`. | Gunakan basis points `0..10000` (`10000 = 100%`) dan durasi kejut minimal 1 hari. |
| `TWIN_STATE_MUTATION_DETECTED` | `TwinWhatIfOrchestratorService` | Digest SHA-256 tabel operasional berubah setelah simulasi What-If selesai. | Hentikan promosi skenario; audit tick handler yang menulis ke tabel nyata di luar blok sandbox. |
| `INVALID_RISK_INPUT` | `OrderRiskScorer` | Parameter item kosong atau komponen metrik berada di luar batas wajar. | Pastikan pemanggilan pemindai risiko memakai `item_code` kanonik dan statistik lead time yang valid. |
| `INVALID_HEALTH_SNAPSHOT_INPUT` | `InventoryHealthAnalyzer` | Umur lot negatif atau stok fisik negatif saat pemindaian kesehatan inventori. | Jalankan `wms:audit` untuk membersihkan anomali data lot atau stok di gudang sebelum pemindaian ulang. |

---

## 2. Kegagalan Verifikasi Sertifikat & Hash Audit

Sistem menerbitkan tiga kelas sertifikat deterministik yang dilengkapi checksum SHA-256:
1. `ForecastAccuracyCertificate` -> diverifikasi oleh `ForecastAccuracyCertificateVerifier`.
2. `OrderPromiseCertificate` -> diverifikasi oleh `OrderPromiseCertificateVerifier`.
3. `SafetyStockPolicyCertificate` -> diverifikasi oleh `SafetyStockPolicyCertificateVerifier`.

**Jika verifikasi sertifikat mengembalikan `false`:**
1. Bandingkan payload masukan tercanonicalisasi (array histori, parameter bps/skala, kuantitas akhir) dengan kolom tersimpan pada database.
2. Pastikan tidak ada modifikasi manual langsung di kolom `forecast_qty`, `atp_confirmed_qty`, atau `safety_stock_qty` tanpa melewati service resmi.
3. Eksekusi ulang perintah rekalkulasi terkait (`tower:forecast-select`, `tower:atp-refresh`, atau `tower:safety-stock`), lalu jalankan `php artisan tower:audit` hingga status kembali bersih (`0` diskrepansi).

---

## 3. Kegagalan Gerbang Kompatibilitas Database & Event Schema

1. **Pelanggaran Evolusi Skema Event (`SchemaBreakingChangeException`)**:
   - Event `tower.alert.raised.v1`, `tower.sop.published.v1`, dan `tower.promise.made.v1` tunduk pada `EventSchemaRegistry` aturan aditif A6: field lama tidak boleh dihapus atau berubah tipe.
   - Setiap field tambahan baru di masa depan wajib bertipe opsional/aditif atau diterbitkan sebagai `.v2`.
2. **Gagal Audit Dua Sumber (`tower:audit` exit code `1`)**:
   - Merujuk pada daftar pelanggaran di `ControlTowerAuditReportService::buildDetailedReport()`:
     - Baris `sct_echelon_stocks` negatif atau `reserved_qty > on_hand_qty + in_transit_qty`.
     - Baris `sct_order_promises` dengan `atp_confirmed_qty > requested_qty` atau agregat ATP SKU melebihi total stok tersedia fisik + pasokan masuk.
     - Baris `sct_sop_cycles` berada di luar status sah `SopCycleStateMachine::states()`.
     - Duplikasi `idempotency_key` aktif atau korupsinya penanda sabotase uji (`ControlTowerAuditCorruptionFixture`).
   - Prosedur pemulihan terperinci terdokumentasi di `docs/runbooks/control-tower-audit-dan-pemulihan.md`.


---

## 3. Katalog Lengkap Kode Alasan & Galat HTTP Control Tower (`FIX-2`)

| Kode / Pengecualian | Status HTTP / Layer | Pemicu Teknis | Tindakan Penanganan Client |
|---|---|---|---|
| `ATP_SUFFICIENT_ON_TIME` | 200 Domain Reason (`I1`) | Kumulatif stok fisik nettable WMS mencukupi pada periode diminta | Pesanan dijanjikan tepat waktu (`late_periods = 0`) |
| `CTP_ON_TIME` | 200 Domain Reason (`I1`) | Stok fisik kurang, namun kapasitas mesin + material MRP periode diminta cukup | Jadwalkan WO/MPS produksi untuk menutup selisih CTP |
| `CTP_LATE` | 200 Domain Reason (`I1`) | Kapasitas + material baru mencukupi pada periode > `requested_period` | Konfirmasi ulang ETA mundur (`promised_period`, `late_periods > 0`) |
| `BOTTLENECK_SHORTFALL` | 200 Domain Reason (`I1`) | Total horizon ATP + CTP 12 minggu masih lebih kecil dari `requested_qty` | Jalankan eskalasi pasokan / parsial / split shipment |
| `PromiseIdempotencyConflictException` | `409 Conflict` | `Idempotency-Key` sama dikirim ulang dengan `item_code`, `quantity`, `requested_period`, atau `order_reference` berbeda | Gunakan `Idempotency-Key` baru untuk parameter kuotasi berbeda |
| `InvalidArgumentException` (Min History `I2`) | `422 Unprocessable` | Panjang seri historis `< 3` titik atau terdapat nilai permintaan `< 0` | Lengkapi minimal 3 periode permintaan non-negatif |
| `InvalidArgumentException` (Override `X53-4`) | `422 Unprocessable` | `reason` < 5 karakter atau `new_qty < 0` atau override sudah di-revert | Perbaiki alasan audit (>= 5 karakter) dan angka non-negatif |
| `DomainException` (Four-Eyes Guard) | `422 Unprocessable` | Pengaju (`proposed_by_user_id` / `overridden_by_user_id`) mencoba menyetujui proposal sendiri | Tugaskan pengguna lain dengan role `admin` / `logistics_admin` |
| `DomainException` (S&OP Backward) | `422 Unprocessable` | Transisi mundur pada `SopCycleStateMachine` dilakukan tanpa mengisi `notes` / alasan | Sertakan alasan perubahan fase secara eksplisit pada payload transisi |
| `InvalidArgumentException` (Twin Shock) | `422 Unprocessable` | `shock_type` berada di luar 6 tipe valid sandbox digital twin | Pilih `PORT_CLOSURE`, `MATERIAL_INFLATION`, `PLANT_EXPANSION`, `DEMAND_SURGE`, `SUPPLIER_OUTAGE`, atau `LEAD_TIME_SPIKE` |
| `tower:audit DISCREPANCY` | CLI Exit `1` | Pelanggaran invarian stok terreservasi, over-promise ATP, atau status S&OP korup | Ikuti SOP triase pada `docs/runbooks/control-tower-audit-dan-pemulihan.md` |


---

## 4. Matriks Reproduksi Pengujian & Langkah Perbaikan per Kode Galat

| Kode / Kejadian | Cara Memverifikasi di Pengujian Automated (`Pest`) | Langkah Pemulihan Operasional bila Muncul di Produksi |
|---|---|---|
| `PromiseIdempotencyConflictException` (`409`) | `Slice1AtpCtpPromiseTest::test_real_cross_module_adapters_and_promise_quote_idempotency` | Pastikan integrasi client membuat `Idempotency-Key` baru setiap kali `qty` atau `requested_period` diubah. |
| `ATP_SUFFICIENT_ON_TIME` vs `CTP_LATE` vs `BOTTLENECK_SHORTFALL` | `Slice1AtpCtpPromiseTest::test_i1_netter_ctp_on_time_and_ctp_late_vectors` & `test_i1_netter_bottleneck_and_boundary_reason_codes` | Buka jadwal work-center di `Manufacturing` (`mfg_work_centers`) atau percepat PO di `Procurement` untuk memangkas horizon CTP. |
| Penolakan Seri `< 3` atau Negatif (`I2`) | `Slice2ForecastSelectionAndOverrideTest::test_i2_selector_rejects_short_or_negative_history` | Perbaiki data histori penjualan sebelum mengeksekusi `php artisan tower:forecast-select`. |
| Pengawal Approval Deviasi `> 20%` (`X53-4`) | `Slice2ForecastSelectionAndOverrideTest::test_x53_4_large_deviation_triggers_approval_engine_and_supports_approve_and_reject` | Koordinasikan persetujuan ke supervisor (`TaskInboxService`); gunakan tombol Revert bila input awal keliru. |
| Pemblokiran Self-Approval (`I3` Four-Eyes) | `Slice3MultiEchelonSafetyStockTest::test_propose_and_four_eyes_approve_updates_safety_stock_policy_and_echelon_stocks` | Ajukan persetujuan kepada user `admin` atau `logistics_admin` yang berbeda dari pengaju proposal. |
| Penolakan Transisi Mundur S&OP Tanpa Alasan | `Slice4SopCycleWorkflowTest::test_backward_transition_fails_without_reason_and_succeeds_with_reason_recording_sop_event` | Isi field `notes` secara jelas untuk jejak audit di `sct_sop_events`. |
| Alert Duplikat Tercegah (`dedup_key`) | `Slice5RiskAndAlertsTest::test_alert_scanner_is_idempotent_emits_outbox_and_registers_schema` | Tidak memerlukan tindakan perbaikan; pemindaian ulang aman dan hanya menerbitkan `tower.alert.raised.v1` satu kali per window. |
| Skenario Twin Tidak Didukung | `Slice6TwinAndKpiTest::test_what_if_rejects_unsupported_scenario` | Pilih salah satu dari 6 `shock_type` resmi di `TwinWhatIfOrchestratorService`. |
| `tower:audit` Sabotage Detection (`Exit 1`) | `ControlTowerSopAndTwinAcceptanceTest::it fails tower audit when sabotage fixture introduces atp over-promise` | Jalankan runbook pemulihan Section 4.1 pada `docs/runbooks/control-tower-audit-dan-pemulihan.md`. |
