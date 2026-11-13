# Skema Reservasi Stok, Karantina Lot, & Analitik Utilisasi Gudang (X41-2 s.d. X41-4)

Dokumen ini merinci mekanisme internal tiga subsistem kritis pada modul `Wms` Fase 41: reservasi stok berbasis TTL (`StockReservationService`), manajemen karantina lot (`LotQuarantineService`), dan analitik utilisasi ruang (`WarehouseUtilizationAnalyzer`).

---

## 1. Mekanisme Reservasi Stok & TTL (`StockReservationService` — X41-2)

### 1.1 Persamaan Ketersediaan Stok
Untuk setiap pasangan `(warehouse_id, product_id)`:
$$\text{available\_base} = \max\bigl(0,\; \text{on\_hand\_base} - \text{quarantined\_base} - \text{reserved\_base}\bigr)$$
Di mana:
- `on_hand_base`: total `qty_base` di seluruh bin pada gudang tersebut.
- `quarantined_base`: total `qty_base` dengan `stock_status IN ('quarantine', 'blocked')`.
- `reserved_base`: total `qty_base` pada `wms_stock_reservations` dengan `status = 'active'` dan `expires_at > $clock->now()`.

### 1.2 Siklus Hidup Reservasi
1. **Create (`reserve`)**:
   - Memverifikasi `available_base >= qtyBase` di dalam `DB::transaction()`.
   - Menyimpan baris dengan `status = 'active'` dan `expires_at = now + ttlMinutes`.
2. **Commit (`commit`)**:
   - Dipanggil oleh `AllocateOutboundWavePickingAction` saat operator mengonfirmasi pengambilan fisik.
   - Mengubah `status` menjadi `'committed'`.
3. **Release (`release`)**:
   - Mengembalikan stok ke kolam tersedia (`status = 'released'`) apabila order dibatalkan sebelum *picking*.
4. **Expire (`expireStaleReservations`)**:
   - Dijalankan oleh `php artisan wms:reservation-sweep` untuk mengubah reservasi lewat waktu menjadi `'expired'`.

---

## 2. Mekanisme Karantina Lot & Write-Off (`LotQuarantineService` — X41-3)

### 2.1 Pemicu Karantina
Stok pada `wms_bin_stocks` masuk ke status `'quarantine'` melalui dua jalur:
- **Otomatis (`QuarantineExpiredLotsAction`)**: memindai lot berstatus `'available'` yang memiliki `expiry_date < today` dan `qty_base > 0`.
- **Manual (`quarantineBinStock`)**: dipicu oleh inspeksi kualitas (QA) atau kemasan rusak saat penerimaan GRN.

### 2.2 Resolusi Karantina
1. **Pelepasan QA (`releaseFromQuarantine`)**:
   - Memastikan status saat ini adalah `'quarantine'`, lalu mengembalikan ke `'available'` dan mencatat `released_at`.
2. **Write-Off Akuntansi (`writeOffBinStock`)**:
   - Mengosongkan `qty_base = 0` dan mengubah `stock_status = 'blocked'`.
   - Menghitung nilai kerugian `qtyBase × unitCostIdr` dan memposting **D41-8** (`Debit wms:inventory_writeoff:IDR / Kredit wms:inventory:{gudang}:IDR`).

---

## 3. Analitik Utilisasi Ruang & What-If Slotting (X41-4)

### 3.1 Metrik `WarehouseUtilizationAnalyzer`
Setiap snapshot merekam:
- `utilization_bps = intdiv(occupied_bins * 10000, total_bins)` (skala `0..10000` bps).
- `dead_stock_sku_count`: jumlah SKU unik dengan `qty_base > 0` yang tidak mengalami pembaruan selama > 90 hari (`updated_at < today - 90d`).
- `slotting_mismatch_count`: jumlah baris pada `wms_slottings` di mana `current_zone_id != recommended_zone_id`.

### 3.2 Simulasi Relokasi (`SlottingScenarioPlanner`)
Sebelum tim gudang melakukan pemindahan fisik rak, `SlottingScenarioPlanner::simulateRelocation()` menjalankan `PutawaySlotter` (H1) pada daftar SKU target dan menghitung total penghematan jarak tempuh (`Σ max(0, (currentDistance - targetDistance) × allocatedQty)`).
