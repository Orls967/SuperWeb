# Panduan Penilaian Biaya Persediaan & Penagihan 3PL WMS

Dokumen ini menjelaskan tata cara pengelolaan harga pokok persediaan (`wms_unit_costs`), eksekusi revaluasi biaya, serta perhitungan tagihan layanan gudang pihak ketiga (3PL).

---

## 1. Metode Penilaian Persediaan (`wms_unit_costs`)

Modul `Wms` mendukung tiga metode penilaian biaya pada kolom `method`:
1. **`std` (Standard Cost)**:
   - Biaya per unit ditetapkan secara berkala oleh tim akuntansi biaya (`admin`).
   - Seluruh penerimaan, pengeluaran, transfer, dan saldo awal dinilai menggunakan biaya standar aktif pada tanggal efektif terbaru.
2. **`avg` (Moving Weighted Average)**:
   - Setiap penerimaan stok baru menghitung ulang biaya rata-rata tertimbang melalui `WmsWeightedAverageCostService::recalculateOnReceipt()`.
   - Rumus integer murni:
     `new_unit_cost = intdiv((old_qty * old_cost) + (in_qty * in_cost), old_qty + in_qty)`.
   - Sisa pembulatan integer (`rounding_residual_idr`) dicatat agar tidak terjadi kehilangan nilai minor units.
3. **`fifo` (First-In, First-Out Cost Layer)**:
   - Digunakan bersama `FefoLotAllocator` untuk produk dengan pelacakan lot dan tanggal kedaluwarsa ketat.

---

## 2. Prosedur Revaluasi Harga Pokok (`RevalueInventoryCostAction`)

Ketika manajemen memutuskan mengubah harga pokok standar suatu SKU di gudang tertentu:
1. Akses rute `POST /wms/operations/revalue-cost` (hanya `role: admin`) atau jalankan perintah konsol:
   ```bash
   php artisan wms:revalue-cost {product_id} {warehouse_id} {new_cost_idr} --method=std
   ```
2. Sistem mengeksekusi langkah atomik berikut di dalam transaksi:
   - Membaca biaya aktif sebelumnya (`old_cost_idr`).
   - Menandai biaya lama `is_active = false`.
   - Menyimpan baris biaya baru di `wms_unit_costs` dengan `is_active = true`.
   - Menghitung total kuantitas aktif produk di gudang tersebut (`qty_base`).
   - Menghitung selisih nilai total: `delta_value_idr = abs(new_cost_idr - old_cost_idr) * qty_base`.
   - Jika `new_cost_idr > old_cost_idr`, memposting **D41-10** (`Debit wms:inventory:{gudang}:IDR / Kredit wms:cost_revaluation_variance:IDR`).
   - Jika `new_cost_idr < old_cost_idr`, memposting **D41-11** (`Debit wms:cost_revaluation_variance:IDR / Kredit wms:inventory:{gudang}:IDR`).
3. Setelah selesai, `WarehouseValuationReader::getWarehouseValuationIdr()` otomatis tetap memiliki `discrepancy_idr === 0`.

---

## 3. Penagihan Jasa Penyimpanan & Handling 3PL

Untuk gudang yang melayani klien pihak ketiga (*Third-Party Logistics*), WMS menyediakan tiga komponen utama:
1. **`ThreePlRateCard`**:
   - `clientCode`: kode unik klien 3PL.
   - `storageRatePerPalletDayIdr`: tarif sewa per palet per hari.
   - `inboundHandlingPerUnitIdr`: biaya bongkar & putaway per unit.
   - `outboundHandlingPerUnitIdr`: biaya pick & pack per unit.
   - `minimumMonthlyFeeIdr`: batas tagihan minimum bulanan.
2. **`StorageBillingCalculator::summarizePeriod()`**:
   - Mengagregasi log harian (`pallet_count * days`, `inbound_units`, `outbound_units`) dan menerapkan `max(minimumMonthlyFeeIdr, subtotal)`.
3. **`Calculate3PlStorageHandlingBillingAction::execute()`**:
   - Menghitung `storage_fee_idr + handling_fee_idr` dan memposting jurnal **D41-12**:
     - Debit (`-`): `wms:3pl_receivable:IDR`
     - Kredit (`+`): `wms:3pl_revenue:IDR`
   - Menggunakan kunci idempotensi `IDEM:WMS:3PL:{BILLING_REF}` agar penagihan periode yang sama tidak pernah terposting dua kali.

---

## 4. Verifikasi Akhir Bulan (*Period Closing*)
Pada akhir periode akuntansi, `EnterpriseFinance` memanggil `WmsSubledgerTotalAdapter::getSubledgerTotal()` (melalui tag `SubledgerTotalProvider`) untuk memperoleh:
- `subledger_total_idr`
- `ledger_total_idr`
- `discrepancy_idr` (wajib `0` sebelum periode ditutup).
