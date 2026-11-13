# Runbook: Audit & Rekonsiliasi WMS (`wms:audit`)

## 1. Tujuan & Cakupan
Runbook ini menjadi pedoman standar bagi tim *Site Reliability*, *Warehouse Operations*, dan *Internal Audit* dalam menjalankan verifikasi integritas stok fisik dan buku besar modul **Wms**. Seluruh pemeriksaan dirancang agar idempoten dan aman dijalankan di lingkungan produksi maupun *staging* tanpa memodifikasi data transaksi.

Perintah utama:
```bash
php artisan wms:audit
```

Exit code:
- `0` (`Command::SUCCESS`): seluruh invarian terpenuhi (`0` selisih).
- `1` (`Command::FAILURE`): ditemukan minimal satu anomali (stok negatif, selisih `inv_`, baris transfer yatim, atau ketidaksinkronan ledger).

---

## 2. Jadwal Eksekusi Perintah Konsol WMS

| Perintah Artisan | Frekuensi | Parameter Penting | Deskripsi Operasional |
|---|---|---|---|
| `wms:audit` | Harian (01:00 WIB) | — | Memeriksa stok negatif (`qty < 0` atau `qty_base < 0`), perbandingan total bin vs `StoreProductStockPort`, integritas baris transfer, dan rekonsiliasi `WarehouseValuationReader` |
| `wms:post-opening-balance` | Sekali saat go-live gudang | `--warehouse_id=` | Memposting saldo awal `Σ(qty_base × cost_idr)` ke akun `wms:inventory:{kode}:IDR` dengan kunci `IDEM:WMS:OPENING:{kode}` |
| `wms:replenish` | Per shift (tiap 8 jam) | `--warehouse_id=`, `--min=20`, `--target=100` | Mendeteksi bin zona `pick` di bawah ambang minimum dan membuat baris `wms_replenishments` |
| `wms:cycle-count-plan` | Harian (06:00 WIB) | `--today=`, `--capacity=20` | Menjalankan `CycleCountScheduler` (H3) untuk memilih SKU overdue sesuai kapasitas harian |
| `wms:transit-sweep` | Tiap 4 jam | `--max-hours=72` | Mengambil pengiriman in-transit yang melewati SLA melalui `InTransitShipmentProvider` |
| `wms:reservation-sweep` | Tiap 15 menit | — | Menandai `wms_stock_reservations` berstatus `active` yang telah melewati `expires_at` menjadi `expired` |
| `wms:utilization-snapshot` | Harian (23:30 WIB) | `--warehouse_id=` | Menghitung `utilization_bps`, `dead_stock_sku_count`, dan `slotting_mismatch_count` ke `wms_utilization_snapshots` |
| `wms:revalue-cost` | Ad-hoc (perubahan HPP) | `product_id`, `warehouse_id`, `new_cost_idr`, `--method=std` | Memperbarui `wms_unit_costs` dan memposting selisih D41-10/D41-11 |

---

## 3. Matriks Diagnosa & Mitigasi Anomali

### 3.1 Anomali `bin_stocks.negatif`
- **Gejala**: Output tabel `wms:audit` menampilkan baris `bin_stocks.negatif` dengan status `SELISIH`.
- **Akar Masalah**:
  1. Pemotongan stok pada `wms_bin_stocks` dilakukan langsung via query SQL tanpa melalui `AllocateOutboundWavePickingAction` atau `StockReservationService`.
  2. Terjadi *race condition* atau migrasi data awal yang tidak ternormalisasi (`qty_base < 0`).
- **Langkah Perbaikan**:
  1. Identifikasi baris bermasalah:
     ```sql
     SELECT id, bin_id, product_id, qty, qty_base, stock_status, updated_at
     FROM wms_bin_stocks
     WHERE qty < 0 OR qty_base < 0;
     ```
  2. Periksa riwayat tugas pada bin tersebut melalui `wms_task_events`.
  3. Lakukan perhitungan fisik (*spot cycle count*) pada bin terkait, lalu eksekusi `RecordCycleCountAdjustmentAction` dengan nilai `countedQtyBase >= 0` agar subledger dan buku besar tersinkron kembali.
  4. Jalankan ulang `php artisan wms:audit` hingga status kembali `OK`.

### 3.2 Anomali `product:{id} bin>saldo`
- **Gejala**: Total stok suatu produk di seluruh bin WMS lebih besar daripada `cached_stock` pada modul `Store` / `Inventory`.
- **Akar Masalah**:
  1. Penerimaan GRN di WMS dicatat tanpa memperbarui stok global produk, atau sebaliknya terjadi penjualan di `Store` yang memotong stok global tanpa tugas `pick` di WMS.
- **Langkah Perbaikan**:
  1. Jalankan `InventoryReconciliationService::reconcileAll()` untuk melihat daftar `qty_anomalies`.
  2. Cocokkan mutasi `wms_receiving_receipts` dan `wms_tasks` terhadap `stock_movements`.
  3. Lakukan penyesuaian di sisi modul yang tertinggal lalu verifikasi ulang dengan `php artisan wms:audit`.

### 3.3 Anomali `transfer.diterima` (Baris Terbuka)
- **Gejala**: Header `wms_transfers` sudah berstatus `received`, namun masih terdapat `wms_transfer_lines` berstatus selain `received`.
- **Akar Masalah**: Pengiriman parsial diterima di gudang tujuan tetapi sisa baris hilang di perjalanan (*transit loss*) belum ditutup secara akuntansi.
- **Langkah Perbaikan**:
  1. Verifikasi berita acara kehilangan barang dengan pihak kurir (`Logistics`).
  2. Bila barang dinyatakan hilang, eksekusi `WmsLedgerPoster::postTransitLoss()` (D41-9) sebesar nilai `lost_qty_base × unit_cost_idr`, lalu perbarui status baris transfer menjadi `received` / `closed`.

### 3.4 Anomali `ledger:{KODE_GUDANG}` (Discrepancy IDR != 0)
- **Gejala**: `subledger_value_idr != ledger_balance_idr` pada `WarehouseValuationReader`.
- **Akar Masalah**:
  1. Gudang baru sudah memiliki stok di `wms_bin_stocks` dan biaya di `wms_unit_costs`, namun `wms:post-opening-balance` belum pernah dijalankan.
  2. Biaya standar di `wms_unit_costs` diubah secara manual di database tanpa melalui `RevalueInventoryCostAction`.
  3. Terjadi write-off atau penyesuaian bin tanpa memanggil `WmsLedgerPoster`.
- **Langkah Perbaikan**:
  1. Buka `/wms/valuation` atau ekspor `/wms/export/valuation.csv` untuk mengidentifikasi gudang yang berselisih.
  2. Periksa mutasi terakhir pada `bank_ledger_transactions` dengan `reference_type LIKE 'WMS_%'`.
  3. Jika penyebabnya adalah saldo awal yang belum diposting, jalankan:
     ```bash
     php artisan wms:post-opening-balance --warehouse_id={WAREHOUSE_ID}
     ```
  4. Jika penyebabnya adalah perubahan harga pokok tanpa revaluasi, kembalikan `wms_unit_costs` ke nilai sebelumnya lalu jalankan `php artisan wms:revalue-cost {product_id} {warehouse_id} {new_cost_idr}`.

---

## 4. Uji Sabotase Berkala (L.5 Verification)
Untuk memastikan deteksi `wms:audit` tidak pernah mengalami regresi (*silent pass*), jalankan suite pengujian:
```bash
vendor/bin/pest tests/Feature/Wms/WmsLedgerAndAuditSabotageTest.php --no-coverage
```
Pengujian tersebut menyuntikkan stok negatif menggunakan `WmsAuditCorruptionFixture` dan memverifikasi bahwa `wms:audit` mengembalikan exit code `1`.
