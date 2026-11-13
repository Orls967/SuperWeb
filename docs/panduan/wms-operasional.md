# Panduan Operasional Gudang (WMS Fase 41)

## 1. Peran & Hak Akses (`role`)

| Peran | Akses Halaman | Wewenang Operasional & Persetujuan |
|---|---|---|
| `admin` | Seluruh halaman `/wms/*` | Konfigurasi gudang, revaluasi harga pokok, persetujuan over-receipt & cycle count adjustment, write-off |
| `hub_operator` | `/wms`, `/wms/tasks`, `/wms/receiving`, `/wms/quarantine`, `/wms/utilization` | Eksekusi tugas handheld, directed putaway, wave picking, penerimaan GRN, pelepasan karantina |
| `procurement` | `/wms`, `/wms/receiving`, `/wms/valuation` | Pemantauan penerimaan GRN, tindak lanjut klaim kekurangan pengiriman vendor (*short receipt*) |
| `auditor` | `/wms`, `/wms/valuation`, `/wms/quarantine`, `/wms/utilization` | Audit stok karantina, inspeksi `wms:audit`, peninjauan log `wms_task_events`, ekspor CSV |
| `logistics_admin` | `/wms`, `/wms/tasks`, `/wms/receiving`, `/wms/utilization` | Koordinasi jadwal dock bay, cut-off kurir, transfer antar-gudang, dan penyeimbangan stok |

---

## 2. Alur Kerja Harian Gudang

### 2.1 Penerimaan Barang Inbound (GRN) — `/wms/receiving`
1. Petugas dock membuka halaman `/wms/receiving`.
2. Mengisi `receipt_number` (unik), `warehouse_id`, `bin_id` sementara/penerimaan, `product_id`, `expected_qty_base`, `received_qty_base`, `unit_cost_idr`, serta opsional `lot_number` dan `expiry_date`.
3. Sistem memanggil `WmsInboundReceivingWorkflowService` dan `ReceivingToleranceEvaluator`:
   - **`within_tolerance`** (`-500 <= variance_bps <= +500`): stok langsung masuk ke `wms_bin_stocks` dan memposting jurnal D41-2 (`Debit wms:inventory:{gudang}:IDR / Kredit wms:receiving_clearing:IDR`).
   - **`over_receipt`** (`variance_bps > +500`): sistem memblokir penyimpanan kecuali supervisor/admin menyetujui (`approve_over_receipt = true`).
   - **`short_receipt`** (`variance_bps < -500`): sistem mencatat kuantitas aktual yang diterima dan menghasilkan klasifikasi `OPEN_VENDOR_SHORTAGE_CLAIM` untuk tim `procurement`.
4. Jika kemasan rusak (`hasDamagedPackaging = true`), `ReceivingDiscrepancyClassifier` otomatis mengarahkan barang ke karantina QA (`QUARANTINE_DAMAGED_INBOUND`).

### 2.2 Directed Putaway & Tugas Handheld — `/wms/tasks`
1. Operator mengambil perangkat genggam (*handheld scanner*, tampilan responsif 375px pada `/wms/tasks`).
2. Untuk setiap barang hasil GRN, `ExecuteDirectedPutawayAction` menjalankan `PutawaySlotter` (H1) guna memilih bin terbaik sesuai kelas ABC produk.
3. Operator memindai barcode bin dan produk melalui `WmsHandheldOperationService::confirmScanAndComplete()`:
   - Sistem memvalidasi kesesuaian kode bin (`$bin->code`) dan `product_id`.
   - Status tugas bergerak otomatis melalui `WarehouseTaskStateMachine`: `created -> assigned -> in_progress -> confirmed -> closed`.
   - Setiap langkah tercatat di `wms_task_events`.

### 2.3 Wave Picking, Reservasi, & Pengiriman Outbound
1. Order outbound dinilai prioritasnya oleh `PickWavePriorityScorer` (`P1_CRITICAL`, `P2_HIGH`, `P3_STANDARD`) dan dikelompokkan oleh `WaveBatchOptimizer`.
2. Sebelum wave dirilis, `WaveReleaseReadinessChecker` memastikan:
   - Seluruh SKU memenuhi kuota `StockAvailabilityReader::getAvailableQty()`.
   - Dock bay sudah ditetapkan (`DockAppointmentScheduler`).
   - Waktu rilis belum melewati cut-off kurir (`CarrierCutoffProvider`).
3. `AllocateOutboundWavePickingAction` mengunci stok via `StockReservationService`, memilih lot berdasarkan FEFO (`expiry_date ASC`), menyusun rute via `PickPathPlanner` (H2), membuat `PackingListGenerator`, dan memposting jurnal D41-3 (`Debit wms:issue_clearing:IDR / Kredit wms:inventory:{gudang}:IDR`).

### 2.4 Replenishment Pick-Face
1. Saat stok di zona `pick` turun di bawah ambang minimum (`reorder_point`), jalankan `php artisan wms:replenish`.
2. `ReplenishmentMinMaxCalculator` menghitung jumlah pengisian ulang hingga mencapai `max_level`, lalu mencatat permintaan di tabel `wms_replenishments`.

### 2.5 Karantina Lot, Kadaluarsa, & Write-Off — `/wms/quarantine`
1. Lot kedaluwarsa dipindai otomatis oleh `QuarantineExpiredLotsAction` atau ditandai manual oleh tim QA via `LotQuarantineService::quarantineBinStock()`.
2. Barang berstatus `quarantine` tidak dihitung dalam `available_base` dan tidak dapat direservasi untuk order keluar.
3. Setelah inspeksi QA pada `/wms/quarantine`:
   - Jika lolos uji ulang: klik pelepasan karantina (`releaseFromQuarantine`), status kembali `available`.
   - Jika rusak permanen / kadaluarsa: eksekusi `writeOffBinStock` untuk mengosongkan stok dan memposting kerugian ke `wms:inventory_writeoff:IDR` (D41-8).

### 2.6 Cycle Count Harian & Penyesuaian Stok
1. Setiap pagi, jalankan `php artisan wms:cycle-count-plan` untuk mendapatkan daftar bin prioritas hari ini (`selected`) dan sisa antrean (`carried_over`) dari `CycleCountScheduler` (H3).
2. Operator melakukan hitung buta (*blind count*). Jika selisih melebihi `200 bps`, `WmsCycleCountExecutionService` mewajibkan hitung ulang (*recount*).
3. Jika selisih berada dalam batas kebijakan `CycleCountPolicy`, `RecordCycleCountAdjustmentAction` menyesuaikan saldo bin dan memposting D41-6 (*gain*) atau D41-7 (*shrinkage*); jika melebihi batas, wajib disetujui supervisor terlebih dahulu.

### 2.7 Penilaian Persediaan & Ekspor CSV — `/wms/valuation`
1. Halaman `/wms/valuation` menampilkan tabel rekonsiliasi per gudang: `subledger_value_idr` vs `ledger_balance_idr` vs `discrepancy_idr`.
2. Tersedia dua tautan ekspor CSV:
   - `/wms/export/valuation.csv`: rekapitulasi nilai persediaan & saldo buku besar per gudang.
   - `/wms/export/bin-stocks.csv`: rincian posisi stok per bin, lot, tanggal kedaluwarsa, dan status.

### 2.8 Analitik Utilisasi Ruang & What-If Slotting — `/wms/utilization`
1. Halaman `/wms/utilization` menampilkan riwayat snapshot utilisasi bin (dalam `bps`), jumlah SKU *dead stock* (>90 hari tanpa pergerakan), dan ketidaksesuaian slotting.
2. Tim perencana gudang dapat menjalankan simulasi *what-if* via `WmsSlottingRecommendationService` sebelum memindahkan posisi rak fisik.
