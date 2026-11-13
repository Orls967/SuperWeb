# Runbook: Cutover Gudang Baru & Penagihan 3PL

## 1. Prosedur Cutover Gudang Baru (Go-Live Checklist)
Saat sebuah pusat distribusi baru (mis. `DC-BJM`) mulai dioperasikan di WMS:

1. **Buat Hierarki Fisik**:
   - Daftarkan `Warehouse` (`code`, `name`, `kind = 'dc'`, `city`).
   - Buat minimal 3 zona standar (`putaway`, `pick`, `storage`) beserta rak dan bin dengan `capacity` integer yang akurat.
2. **Muat Harga Pokok Awal (`wms_unit_costs`)**:
   - Pastikan setiap SKU yang akan disimpan memiliki baris aktif (`is_active = true`) pada `wms_unit_costs` dalam integer minor units IDR.
3. **Muat Stok Awal (`wms_bin_stocks`)**:
   - Catat kuantitas fisik dalam `qty_base` (integer base unit) beserta `lot_number`, `expiry_date`, dan `stock_status = 'available'`.
4. **Posting Saldo Awal Buku Besar (D41-1)**:
   - Jalankan perintah idempoten:
     ```bash
     php artisan wms:post-opening-balance --warehouse_id={ID_GUDANG}
     ```
   - Perintah ini secara otomatis membuat akun `wms:inventory:{KODE_GUDANG}:IDR` (jika belum ada) melalui `WmsAccounts::ensureWarehouseInventoryAccount()` dan memposting saldo awal dengan `idempotency_key = IDEM:WMS:OPENING:{KODE_GUDANG}`.
5. **Verifikasi Akhir Cutover**:
   - Jalankan `php artisan wms:audit` dan pastikan exit code `0`.
   - Buka `/wms/valuation` dan pastikan `discrepancy_idr == 0`.

---

## 2. Operasional Jasa Gudang Pihak Ketiga (3PL Billing)
Modul WMS mendukung penagihan layanan penyimpanan dan *handling* untuk klien 3PL menggunakan:
- `ThreePlRateCard` (tarif per pallet-day, inbound handling per unit, outbound handling per unit, dan *minimum monthly fee*).
- `StorageBillingCalculator::summarizePeriod()` untuk mengakumulasi log harian klien.
- `Calculate3PlStorageHandlingBillingAction::execute()` untuk menghitung total tagihan dalam integer IDR dan memposting jurnal **D41-12**:
  - **Debit (`-`)**: `wms:3pl_receivable:IDR`
  - **Kredit (`+`)**: `wms:3pl_revenue:IDR`

Contoh perhitungan deterministik:
- `palletDays = 30`, `storageRate = 5.000` → `150.000`
- `handlingUnits = 20`, `handlingRate = 1.000` → `20.000`
- Total tagihan = `170.000 IDR` (dibandingkan dengan `minimumMonthlyFeeIdr`; diambil nilai terbesar).
