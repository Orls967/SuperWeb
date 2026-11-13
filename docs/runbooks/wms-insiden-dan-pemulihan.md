# Runbook: Penanganan Insiden & Pemulihan Operasional WMS

## 1. Insiden: Lonjakan Reservasi Kedaluwarsa (`active` Menumpuk)
### Gejala
Operator melaporkan produk tidak dapat dialokasikan ke *wave picking* baru meskipun stok fisik di rak masih tersedia (`available_base == 0`, `reserved_base > 0`).

### Langkah Pemulihan
1. Periksa jumlah reservasi aktif yang sudah melewati `expires_at`:
   ```sql
   SELECT COUNT(*) FROM wms_stock_reservations
   WHERE status = 'active' AND expires_at <= CURRENT_TIMESTAMP;
   ```
2. Jalankan perintah sapu reservasi:
   ```bash
   php artisan wms:reservation-sweep
   ```
3. Verifikasi bahwa `StockAvailabilityReader::getBreakdown()` kini menampilkan `available_base` yang benar.

---

## 2. Insiden: Pengiriman Transfer Antar-Gudang Melewati SLA (`InTransit` Macet)
### Gejala
Stok sudah dipotong dari gudang asal (`D41-4`), namun setelah >72 jam belum tercatat diterima di gudang tujuan (`D41-5`).

### Langkah Pemulihan
1. Jalankan pemindaian SLA in-transit:
   ```bash
   php artisan wms:transit-sweep --max-hours=72
   ```
2. Evaluasi tingkat keterlambatan menggunakan `TransitSlaEvaluator::evaluate()`:
   - Jika `72 < hours <= 168` (`overdue`): koordinasikan pelacakan dengan tim `Logistics`.
   - Jika `hours >= 168` (`presumed_lost`): ajukan klaim kehilangan dan jalankan `WmsLedgerPoster::postTransitLoss()` (D41-9) untuk membersihkan akun `wms:inventory_in_transit:IDR`.

---

## 3. Insiden: Penarikan Lot / Produk Kedaluwarsa (*Lot Recall*)
### Gejala
Ditemukan batch produksi bermasalah atau lot yang telah melewati tanggal kedaluwarsa masih berstatus `available` di bin.

### Langkah Pemulihan
1. Lacak seluruh posisi lot menggunakan `WmsLotTraceabilityService::traceLot($lotNumber)` untuk mengetahui penerimaan GRN, bin penyimpanan, dan reservasi aktif.
2. Pindahkan seluruh stok lot terkait ke karantina:
   - Untuk lot kedaluwarsa otomatis: jalankan `QuarantineExpiredLotsAction`.
   - Untuk *recall* manual: panggil `LotQuarantineService::quarantineBinStock($binStockId, 'Lot recall QA')`.
3. Pastikan halaman `/wms/quarantine` menampilkan seluruh unit yang ditahan dan tidak ada order baru yang dapat mereservasinya.
4. Bila lot dimusnahkan, eksekusi `LotQuarantineService::writeOffBinStock()` agar kerugian tercatat pada `wms:inventory_writeoff:IDR` (D41-8).

---

## 4. Insiden: Tugas Handheld Macet di Status `exception`
### Gejala
Tugas tidak dapat diselesaikan operator karena bin penuh, barang rusak, atau ketidaksesuaian barcode.

### Langkah Pemulihan
1. Tinjau alasan penangguhan pada tabel `wms_task_events` (`to_status = 'exception'`).
2. Gunakan `ResolveTaskExceptionAction::resolve()`:
   - Mode `'retry'`: kembalikan tugas ke `in_progress` setelah masalah fisik di lorong teratasi.
   - Mode `'resolve'`: konfirmasi penyelesaian tugas jika sudah ditangani secara manual oleh supervisor.
   - Mode `'cancel'`: batalkan tugas secara permanen (status terminal `cancelled`).

---

## 5. Checklist Pemulihan Pasca-Insiden
Setelah setiap insiden ditangani, wajib jalankan verifikasi akhir:
1. `php artisan wms:audit` → harus exit `0`.
2. `WmsInventoryIntegrityVerifier::verify()` → `is_healthy === true`.
3. `WmsValuationReconciliationReportService::generateReport()` → `is_reconciled === true`.

