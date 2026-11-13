# Konsep Arsitektur WMS Lanjutan (Fase 41)

## 1. Tujuan & Batasan Sistem

Modul **Wms (Warehouse Management System)** pada Fase 41 dirancang untuk menjamin ketepatan fisik persediaan di setiap bin gudang sekaligus menjaga kesesuaian penuh dengan buku besar akuntansi (`LedgerService`). Seluruh perhitungan kuantitas, kapasitas, jarak, dan nilai uang menggunakan aritmetika **integer murni** (base unit dan IDR minor units) tanpa tipe `float`, sehingga tidak pernah terjadi galat pembulatan akumulatif maupun selisih rekonsiliasi antar-modul.

### 1.1 Hierarki Fisik Gudang
Setiap gudang (`wms_warehouses`) memiliki struktur hierarki fisik empat tingkat:
1. **Gudang (Warehouse / DC)** — entitas akuntansi dan pusat distribusi dengan kode unik (mis. `DC-JKT`, `DC-SBY`, `DC-BJM`). Identitas primary key menggunakan UUID (`HasUuids`).
2. **Zona (Zone)** — pengelompokan fungsional (`putaway`, `pick`, `storage`, `bulk`, `cold`, `quarantine`).
3. **Rak (Rack)** — unit struktural bernomor lorong (*aisle*) dan level vertikal (`rack_level`).
4. **Bin (Bin)** — lokasi terkecil penyimpanan stok dengan kapasitas unit, batas berat (gram), dan volume (cm³) terukur.

### 1.2 Prinsip Determinisme & Non-Float (D81)
- Tidak ada penggunaan `float` atau `double` pada perhitungan kuantitas, biaya, skor, maupun rasio.
- Seluruh rasio dan persentase dinyatakan dalam **basis points (`bps`)**, di mana `10.000 bps = 100%`, `500 bps = 5%`, `100 bps = 1%`.
- Seluruh waktu operasi di Domain dan Application diperoleh melalui `ClockInterface`, bukan `now()` atau `Carbon::now()` secara langsung, sehingga simulasi S2 dan pengujian unit selalu deterministik.

---

## 2. Model Kuantitas & Penilaian Persediaan

### 2.1 Normalisasi Kuantitas (`QuantityNormalizer`)
Seluruh input kuantitas dari penerimaan barang, pemindahan bin, atau data legacy `decimal(18,6)` dikonversi melalui [`QuantityNormalizer`](file:///Users/orl123456/Documents/typeshit/sw-w8-p6/modules/Wms/Domain/Support/QuantityNormalizer.php) ke integer `qty_base`.

| Satuan (`uom`) | Faktor Konversi ke Base Unit | Contoh Input | Hasil `qty_base` |
|---|---|---|---|
| `pcs` / `unit` | `1` | `15 pcs` | `15` |
| `pack` | `6` | `4 pack` | `24` |
| `box` | `12` | `10 box` | `120` |
| `carton` | `24` | `5 carton` | `120` |
| `pallet` | `480` | `2 pallet` | `960` |
| `kg` | `1000` (gram) | `3 kg` | `3000` |
| `l` | `1000` (ml) | `2 l` | `2000` |

Untuk data legacy bertipe string desimal (`"12.000000"`), metode `QuantityNormalizer::fromDecimalString()` melakukan parsing integer murni tanpa casting ke `float`.

### 2.2 Penilaian Harga Pokok (`wms_unit_costs`)
Setiap produk di suatu gudang memiliki catatan biaya satuan aktif (`cost_idr` dalam integer minor units IDR) pada tabel `wms_unit_costs`.
- Mendukung metode `std` (Standard Cost), `avg` (Moving Weighted Average via `WmsWeightedAverageCostService`), dan `fifo`.
- Biaya dapat ditentukan spesifik per gudang (`warehouse_id` terisi) atau sebagai fallback global (`warehouse_id IS NULL`).
- Perubahan harga pokok tidak pernah menimpa riwayat secara diam-diam:
  1. Rekaman aktif sebelumnya dinonaktifkan (`is_active = false`).
  2. Rekaman baru disimpan dengan `effective_date` dan `created_by_user_id`.
  3. Selisih nilai atas seluruh stok aktif di gudang (`(new_cost - old_cost) × Σ qty_base`) diposting otomatis ke buku besar sebagai `COST_REVALUATION_MARKUP` (D41-10) atau `COST_REVALUATION_MARKDOWN` (D41-11).

### 2.3 Moving Weighted Average (`WmsWeightedAverageCostService`)
Saat penerimaan stok baru dengan metode `avg`:
- `new_total_value_idr = (existing_qty × existing_cost) + (incoming_qty × incoming_cost)`
- `new_unit_cost_idr = intdiv(new_total_value_idr, existing_qty + incoming_qty)`
- Sisa pembulatan integer dicatat eksplisit sebagai `rounding_residual_idr` agar tidak ada selisih nilai tersembunyi.

---

## 3. Integrasi Buku Besar (D41-9 / W8-D83)

WMS mengelola **12 akun standar** yang didaftarkan melalui [`WmsAccounts`](file:///Users/orl123456/Documents/typeshit/sw-w8-p6/modules/Wms/Ledger/WmsAccounts.php) ke `LedgerAccountsSeeder`, serta **12 tipe transaksi** pada [`WmsTransactionTypes`](file:///Users/orl123456/Documents/typeshit/sw-w8-p6/modules/Wms/Ledger/WmsTransactionTypes.php) yang terdaftar di `TransactionTypeRegistry`.

### 3.1 Konvensi Tanda Tunggal (`KONSEP §A2.1`)
- **Debit** direpresentasikan bernilai negatif (`-`).
- **Kredit** direpresentasikan bernilai positif (`+`).
- Jumlah seluruh entri per transaksi wajib **0** (`Σ = 0`).
- Akun aset persediaan per gudang (`wms:inventory:{KODE_GUDANG}:IDR`) bertambah melalui sisi **Debit** (sehingga `cached_balance` bernilai negatif). Oleh karena itu, seluruh akun aset dan beban pada `WmsAccounts` dikonfigurasi dengan `allow_negative => true`, dan pembacaan saldo aset positif dihitung sebagai `-cached_balance`.

### 3.2 Matriks 12 Kejadian Uang WMS

| Kode | Kejadian Uang | Tipe Transaksi (`WmsTransactionTypes`) | Akun Debit (`-`) | Akun Kredit (`+`) |
|---|---|---|---|---|
| D41-1 | Saldo awal persediaan gudang | `wms_opening_balance` | `wms:inventory:{gudang}:IDR` | `wms:opening_balance_equity:IDR` |
| D41-2 | Penerimaan stok inbound (GRN) | `wms_inbound_receipt` | `wms:inventory:{gudang}:IDR` | `wms:receiving_clearing:IDR` |
| D41-3 | Pengeluaran stok outbound | `wms_outbound_issue` | `wms:issue_clearing:IDR` | `wms:inventory:{gudang}:IDR` |
| D41-4 | Transfer keluar antar-gudang | `wms_transfer_out` | `wms:inventory_in_transit:IDR` | `wms:inventory:{asal}:IDR` |
| D41-5 | Transfer masuk di gudang tujuan | `wms_transfer_in` | `wms:inventory:{tujuan}:IDR` | `wms:inventory_in_transit:IDR` |
| D41-6 | Cycle count positif (gain) | `wms_cycle_count_gain` | `wms:inventory:{gudang}:IDR` | `wms:inventory_gain:IDR` |
| D41-7 | Cycle count negatif (shrinkage) | `wms_cycle_count_shrinkage` | `wms:inventory_shrinkage:IDR` | `wms:inventory:{gudang}:IDR` |
| D41-8 | Write-off stok rusak / kadaluarsa | `wms_inventory_writeoff` | `wms:inventory_writeoff:IDR` | `wms:inventory:{gudang}:IDR` |
| D41-9 | Transit loss (hilang di jalan) | `wms_transit_loss` | `wms:transit_loss:IDR` | `wms:inventory_in_transit:IDR` |
| D41-10 | Revaluasi harga pokok (mark-up) | `wms_cost_revaluation_markup` | `wms:inventory:{gudang}:IDR` | `wms:cost_revaluation_variance:IDR` |
| D41-11 | Revaluasi harga pokok (mark-down) | `wms_cost_revaluation_markdown` | `wms:cost_revaluation_variance:IDR` | `wms:inventory:{gudang}:IDR` |
| D41-12 | Tagihan jasa 3PL | `wms_3pl_billing` | `wms:3pl_receivable:IDR` | `wms:3pl_revenue:IDR` |

### 3.3 Idempotensi & Penjagaan Ganda
Setiap posting melalui [`WmsLedgerPoster`](file:///Users/orl123456/Documents/typeshit/sw-w8-p6/modules/Wms/Application/Actions/WmsLedgerPoster.php) wajib menyertakan `idempotency_key` deterministik berformat `IDEM:WMS:{AKSI}:{ID}` dan mengecek tabel `bank_ledger_transactions` sebelum memanggil `LedgerService::post()`. Posting bernilai `0` diabaikan secara aman tanpa melempar galat.

---

## 4. State Machine Tugas Gudang (D41-1)

[`WarehouseTaskStateMachine`](file:///Users/orl123456/Documents/typeshit/sw-w8-p6/modules/Wms/Domain/StateMachines/WarehouseTaskStateMachine.php) mengontrol seluruh tugas operasional (`putaway`, `pick`, `pack`, `stage`, `replenish`) dalam 7 status deterministik:

| Status Awal | Aksi (`action`) | Status Tujuan | Efek Samping |
|---|---|---|---|
| `created` | `assign` | `assigned` | Set `assigned_to` jika belum terisi |
| `created` | `cancel` | `cancelled` | Status terminal; catat alasan |
| `assigned` | `start` | `in_progress` | Set `started_at` dari `ClockInterface` |
| `assigned` | `cancel` | `cancelled` | Status terminal; catat alasan |
| `in_progress` | `confirm` | `confirmed` | Set `completed_at` dari `ClockInterface` |
| `in_progress` | `flag_exception` | `exception` | Tangguhkan eksekusi & eskalasi |
| `in_progress` | `cancel` | `cancelled` | Status terminal |
| `exception` | `retry` | `in_progress` | Kembalikan ke antrean pengerjaan |
| `exception` | `resolve` | `confirmed` | Selesaikan dengan catatan resolusi |
| `exception` | `cancel` | `cancelled` | Status terminal |
| `confirmed` | `close` | `closed` | Status terminal; arsipkan tugas |

Setiap transisi menulis jejak audit append-only ke tabel `wms_task_events` lengkap dengan `from_status`, `to_status`, `action`, `actor_user_id`, `reason`, `payload_json`, dan `created_at`.

---

## 5. Kontrak Lintas Modul & Adaptor

| Kontrak | Modul Pemilik | Implementasi / Adaptor | Fungsi |
|---|---|---|---|
| `StockAvailabilityReader` | `Wms` | `StockReservationService` | Menghitung stok tersedia netto (`on_hand - quarantined - active_reserved`) |
| `WarehouseValuationReader` | `Wms` | `WarehouseValuationReaderAdapter` | Membandingkan nilai subledger gudang vs saldo `wms:inventory:*:IDR` |
| `SubledgerTotalProvider` | `Intercompany` | `WmsSubledgerTotalAdapter` | Menyediakan total nilai persediaan WMS ke period closing `EnterpriseFinance` |
| `InventoryBalanceProvider` | `Inventory` | `InventoryBalanceProviderAdapter` | Menyediakan saldo stok produk untuk rekonsiliasi `wms:audit` |
| `InTransitShipmentProvider` | `Logistics` | `InTransitShipmentProviderAdapter` | Memantau pengiriman in-transit menuju/dari gudang & SLA overdue |
| `CarrierCutoffProvider` | `Logistics` | `CarrierCutoffProviderAdapter` | Menyediakan jadwal cut-off kurir harian per gudang untuk dock & wave |

---

## 6. Fitur Lanjutan (X41-1 s.d. X41-5)

- **X41-1 Toleransi Penerimaan**: `ReceivingToleranceEvaluator` menghitung varians dalam `bps`. Penerimaan di atas `over_tolerance_bps` ditolak kecuali disertai persetujuan supervisor eksplisit (`approve_over_receipt`). `ReceivingDiscrepancyClassifier` menentukan peran eskalasi (`admin`, `procurement`, `auditor`).
- **X41-2 Reservasi Stok & TTL**: `StockReservationService` mengelola siklus `active -> committed | released | expired` dengan TTL deterministik. Perintah `wms:reservation-sweep` melepas reservasi kedaluwarsa secara berkala.
- **X41-3 Karantina Lot & QA**: `LotQuarantineService` memindahkan lot bermasalah/kedaluwarsa ke status `quarantine` sehingga otomatis dikecualikan dari `FefoLotAllocator`, mendukung pelepasan QA, atau write-off akuntansi (D41-8).
- **X41-4 Analitik Utilisasi & What-If Slotting**: `WarehouseUtilizationAnalyzer` merekam snapshot utilisasi bin (bps) dan jumlah SKU *dead stock* (>90 hari tanpa mutasi) ke `wms_utilization_snapshots`, sementara `SlottingScenarioPlanner` mensimulasikan penghematan jarak tempuh sebelum relokasi fisik dilakukan.
- **X41-5 Fixture Sabotase Audit**: `WmsAuditCorruptionFixture` menyediakan injeksi anomali terisolasi untuk membuktikan bahwa `php artisan wms:audit` mendeteksi stok negatif, selisih `inv_`, maupun ketidaksinkronan nilai subledger vs buku besar.

---

## 7. Event Domain & Outbox (`WmsEventCatalog`)

WMS menerbitkan tiga event domain versi `v1` melalui `OutboxBusInterface` dan Laravel `Event`:
1. `warehouse.task.confirmed.v1` (`WarehouseTaskConfirmedEvent`) — dipicu saat tugas `putaway` atau `pick` dikonfirmasi.
2. `warehouse.stock.adjusted.v1` (`WarehouseStockAdjustedEvent`) — dipicu saat `RecordCycleCountAdjustmentAction` mencatat selisih stok.
3. `warehouse.transfer.received.v1` (`WarehouseTransferReceivedEvent`) — dipicu saat transfer antar-gudang selesai diterima di tujuan.
