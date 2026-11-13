# Runbook Audit Dua Sumber & Pemulihan Control Tower (`tower:audit`)

Runbook ini digunakan oleh Site Reliability Engineer (SRE), Logistics Admin, Auditor Internal, dan on-call engineer saat melakukan verifikasi periodik maupun saat menanggapi kegagalan pemeriksaan integritas pada modul `ControlTower` (`sct_*`).

---

## 1. Tujuan & Frekuensi Eksekusi Audit

Perintah utama:
```bash
php artisan tower:audit
```
Perintah ini terhubung ke kontrak quality gate (`AuditCommandContractTest`) dan wajib mengembalikan exit code `0` pada kondisi sehat serta exit code `1` ketika terdapat pelanggaran invarian atau data hasil injeksi fixture sabotase (`ControlTowerAuditCorruptionFixture`).

**Jadwal disarankan:**
- Pra-publikasi S&OP bulanan (sebelum transisi ke status `published`).
- Harian otomatis pasca-pemindaian malam (`tower:alerts-scan`, `tower:risk-scan`, `tower:kpi`).
- Segera setelah proses migrasi data atau impor ulang inventori `Wms`.

---

## 2. Daftar Invarian yang Diaudit (`ControlTowerAuditReportService`)

| Kode Cek | Tabel / Sumber Diperiksa | Kondisi Lulus (Invariant) | Kondisi Gagal (Violation) |
| :--- | :--- | :--- | :--- |
| **INV-CT-01** | `sct_echelon_stocks` | `on_hand_qty >= 0`, `in_transit_qty >= 0`, `reserved_qty >= 0` | Terdapat kuantitas negatif di salah satu kolom stok. |
| **INV-CT-02** | `sct_echelon_stocks` | `reserved_qty <= (on_hand_qty + in_transit_qty)` | Reservasi ATP melampaui total stok fisik + transit pada simpul. |
| **INV-CT-03** | `sct_order_promises` | `atp_confirmed_qty <= requested_qty` | Janji ATP dialokasikan melebihi kuantitas pesanan asli. |
| **INV-CT-04** | Agregat `sct_order_promises` vs `sct_echelon_stocks` | Total ATP aktif per `item_code` seimbang dengan kapasitas pasokan tersedia + MPS. | Over-promise lintas pesanan pada SKU tertentu. |
| **INV-CT-05** | `sct_sop_cycles` | Seluruh baris berstatus di dalam `SopCycleStateMachine::states()` | Ditemukan siklus S&OP berstatus tidak sah (`corrupted_unknown_state` dsb.). |
| **INV-CT-06** | Sertifikat & Idempotensi Janji | `idempotency_key` aktif unik; `certificate_hash` tervalidasi | Duplikasi kunci idempotensi atau sertifikat rusak. |

---

## 3. Prosedur Diagnostik Ketika `tower:audit` Gagal (Exit Code 1)

### Langkah 1 — Inspeksi Laporan Terperinci
Jalankan diagnostik via artisan tinker atau uji regresi untuk membaca daftar pelanggaran dari `ControlTowerAuditReportService::buildDetailedReport()`:
```bash
php artisan tinker --execute="dump(app(\Modules\ControlTower\Application\Services\ControlTowerAuditReportService::class)->buildDetailedReport());"
```
Output berisi:
- `status`: `'VIOLATION'`
- `checked_at_counts`: jumlah baris teraudit pada `echelons`, `promises`, `alerts`, dan `sop_cycles`
- `violations`: array deskripsi eksplisit pelanggaran yang terdeteksi

### Langkah 2 — Identifikasi Konflik Alokasi per SKU
Jika pelanggaran terkait `INV-CT-02` atau `INV-CT-04`:
```bash
php artisan tinker --execute="dump(app(\Modules\ControlTower\Application\Services\ControlTowerAllocationConflictService::class)->detectOverAllocatedItems());"
```
Catat seluruh `item_code` yang memiliki selisih alokasi positif.

### Langkah 3 — Prosedur Pemulihan per Jenis Pelanggaran

1. **Kasus Over-Allocation / Stok Echelon Tertinggal dari WMS:**
   - Sinkronkan ulang posisi stok nyata dari modul `Wms` lewat `ControlTowerWmsSyncService` / `ItemAvailabilityReader`.
   - Segarkan perhitungan bucket ATP/CTP dengan menjalankan ulang horizon promise:
     ```bash
     php artisan tower:atp-refresh
     ```
   - Bila janji pesanan lama tidak lagi terdukung pasokan fisik, turunkan statusnya melalui revisi janji pesanan dengan mengalihkan sisa kebutuhan ke jalur CTP produksi atau menjadwalkan ulang tanggal janji.

2. **Kasus Over-Promise Buatan atau Data Rusak (`SABOTAGE-*`):**
   - Pastikan kerusakan bukan berasal dari sisa eksekusi fixture uji di lingkungan non-produksi.
   - Karantina baris janji bermasalah, evaluasi ulang order terbuka dengan `PromiseQuoteInterface`, dan informasikan ke tim Customer Service bila terdapat perubahan tanggal pengiriman.

3. **Kasus Status Siklus S&OP Ilegal (`INV-CT-05`):**
   - Periksa riwayat transisi terakhir di tabel `sct_sop_events` untuk siklus tersebut.
   - Koreksi status siklus hanya ke status terakhir yang sah sebelum anomali terjadi sesuai state machine `SopCycleStateMachine`, dan catat entri audit korektif di `sct_sop_events`.

4. **Kasus Forecast Override Tidak Konsisten / Sertifikat Mismatch:**
   - Jalankan ulang pemilihan model statistik untuk mengembalikan baseline konsensus:
     ```bash
     php artisan tower:forecast-select
     ```
   - Jika nilai override dari planner perlu dipertahankan, periksa deviasi melalui `/control-tower/forecast` agar tercatat dengan alasan bisnis yang valid.

---

## 4. Verifikasi Pasca-Pemulihan

Setelah langkah perbaikan diterapkan:
1. Jalankan ulang `php artisan tower:audit` dan pastikan output akhir menampilkan:
   `Supply Chain Control Tower audit PASSED with 0 discrepancy.` (exit code `0`).
2. Jalankan pemindaian risiko & peringatan agar status disrupsi termutakhirkan:
   ```bash
   php artisan tower:risk-scan
   php artisan tower:alerts-scan
   php artisan tower:kpi
   ```
3. Catat kejadian insiden, akar masalah, dan bukti exit code `0` ke log operasional pemeliharaan sistem.
