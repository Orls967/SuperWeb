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
