# Runbook Audit & Pemulihan Control Tower (`tower:audit`)

## 1. Perintah Audit Rutin
```bash
php artisan tower:audit
```
Perintah ini memeriksa 4 invarian utama pada tabel `sct_*`:
1. `sct_echelon_stocks`: `reserved_qty <= on_hand_qty + in_transit_qty` dan tidak ada kuantitas negatif.
2. `sct_order_promises`: `atp_confirmed_qty <= requested_qty` pada setiap baris janji pesanan.
3. Invarian agregat ATP per SKU: `SUM(atp_confirmed_qty)` per `item_code` tidak boleh melebihi `SUM(on_hand_qty + in_transit_qty)`.
4. `sct_sop_cycles`: seluruh baris wajib berada pada salah satu dari 7 status resmi `SopCycleStateMachine`.

## 2. Prosedur Penanganan Kegagalan Audit
- Jika `tower:audit` mengembalikan exit code `1`:
  1. Jalankan `ControlTowerAuditReportService::buildDetailedReport()` untuk melihat daftar pelanggaran spesifik per SKU/siklus.
  2. Gunakan `ControlTowerAllocationConflictService::detectOverAllocatedItems()` untuk mengidentifikasi SKU dengan ATP over-allocated.
  3. Lakukan rekonsiliasi stok dari modul `Wms` menggunakan `ControlTowerWmsSyncService::syncWarehouseDcStock()`, lalu re-evaluasi order promise terkait atau alihkan ke jalur CTP manufaktur.
  4. Jalankan ulang `php artisan tower:audit` hingga status kembali `OK` (exit code `0`).

