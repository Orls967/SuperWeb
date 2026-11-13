# Runbook: Pemantauan KPI Gudang, SLA Pengiriman, & Serah-Terima Shift

Dokumen ini menjadi panduan harian bagi *Warehouse Supervisor* dan *Hub Lead* untuk memantau kesehatan operasional menggunakan agregator KPI, evaluasi SLA in-transit, dan laporan serah-terima shift.

---

## 1. Pemantauan KPI Operasional (`WmsKpiAggregator`)

| Metrik | Rumus / Sumber | Target Sehat | Tindakan Bila Menyimpang |
|---|---|---|---|
| `completion_rate_bps` | `intdiv(closed_tasks * 10000, total_tasks)` | `>= 9500 bps` (95%) | Tambah alokasi operator shift atau periksa bottleneck pada tugas `in_progress` |
| `exception_tasks` | Jumlah `wms_tasks` berstatus `exception` | `0` | Tindak lanjuti segera dengan `ResolveTaskExceptionAction` |
| `active_reservations` | Jumlah `wms_stock_reservations` berstatus `active` | Terkendali | Jalankan `php artisan wms:reservation-sweep` jika ada reservasi menggantung |
| `quarantined_bins` | Jumlah bin dengan `stock_status = 'quarantine'` | `< 2%` total bin | Jadwalkan inspeksi QA atau write-off agar kapasitas rak segera pulih |

---

## 2. Prosedur Serah-Terima Shift (`WmsShiftHandoverSummaryService`)
Pada akhir setiap shift (07:00, 15:00, 23:00 WIB), supervisor shift lama wajib mencatat ringkasan dari `WmsShiftHandoverSummaryService::buildHandover($warehouseId)`:
1. `open_tasks_count`: jumlah tugas yang masih `created`, `assigned`, atau `in_progress`.
2. `exception_tasks_count`: tugas yang tertahan dan membutuhkan keputusan supervisor.
3. `pending_receipts_count`: penerimaan GRN yang belum dipindah ke rak penyimpanan (*putaway*).
4. `active_reservations_count`: reservasi order outbound yang sedang berlangsung.
5. `pending_replenishments_count`: permintaan isi ulang *pick-face* yang belum dieksekusi.

---

## 3. Evaluasi SLA Transfer & Carrier Cut-Off
1. **Carrier Cut-Off (`WmsDockAndCutoffService`)**:
   - Pastikan seluruh wave picking untuk kurir reguler (`JNE 17:00`, `SICEPAT 18:30`, `ANTERAJA 20:00`) sudah selesai dipack minimal 30 menit sebelum cut-off.
2. **In-Transit Sweep (`wms:transit-sweep`)**:
   - Pengiriman antar-gudang yang melebihi `72 jam` ditandai `overdue` oleh `TransitSlaEvaluator`.
   - Pengiriman yang melebihi `168 jam` ditandai `presumed_lost` (`recommend_loss_claim = true`) dan dieskalasi ke tim klaim logistik serta akuntansi (D41-9).

---

## 4. Evaluasi Penuaan Stok & Shrinkage Berkala
Setiap pekan, tim inventori menjalankan dua analisis berikut:
1. **`InventoryAgingAnalyzer::analyze()`**: mengelompokkan stok ke bucket `0_30`, `31_60`, `61_90`, `91_180`, dan `over_180` hari serta menghitung estimasi cadangan penurunan nilai (`200 / 500 / 1500 / 5000 bps`).
2. **`CycleCountVarianceAnalyzer::analyze()`**: menghitung `shrinkage_rate_bps` dan mengidentifikasi `repeat_offender_products` (SKU dengan selisih negatif berulang `>= 2` kali atau tingkat penyusutan `> 200 bps`).

