# Runbook Operasional Rumah Sakit (Hospital)

Dokumen ini memuat panduan eksekusi perintah terjadwal (CLI Commands), pemulihan bencana (disaster recovery), serta penanganan kegagalan sistem pada domain Rumah Sakit.

---

## 1. Perintah Terjadwal (Console Commands)

### 1.1 `hospital:bed-sweep`
- **Tujuan**: Memeriksa status kebersihan dan reservasi ranjang kedaluwarsa. Ranjang berstatus `cleaning` yang telah melebihi 120 menit tanpa konfirmasi sanitasi akan dinaikkan tiket eskalasinya ke tim pengawas kebersihan.
- **Jadwal Eksekusi**: Setiap 30 menit (`*/30 * * * *`).
- **Idempotensi**: Idempoten penuh. Menjalankan ulang perintah pada jam yang sama tidak menimbulkan efek samping ganda.
- **Cara Menjalankan Manual**:
  ```bash
  php artisan hospital:bed-sweep
  ```
- **Langkah Pemulihan Galat**: Jika perintah gagal karena timeout database, periksa kuncian ranjang aktif via query transaksi MySQL/PostgreSQL dan jalankan ulang dengan opsi `--chunk=100`.

### 1.2 `hospital:pathway-scan`
- **Tujuan**: Memindai seluruh Clinical Pathway Orders yang berstatus `ordered` atau `scheduled`. Mengevaluasi keterlambatan dengan algoritma B3 `ClinicalPathwayDelayDetector`. Mengirim alert perawat tepat 1 kali jika keterlambatan > 15 menit.
- **Jadwal Eksekusi**: Setiap 10 menit (`*/10 * * * *`).
- **Idempotensi**: Idempoten. Peringatan hanya dikirim jika flag `delay_alert_sent` masih bernilai `false`.
- **Cara Menjalankan Manual**:
  ```bash
  php artisan hospital:pathway-scan
  ```

### 1.3 `hospital:efficiency-report {date}`
- **Tujuan**: Menghitung indikator efisiensi tempat tidur Barber-Johnson (BOR, ALOS, TOI, BTO) untuk tanggal referensi tertentu, mencatat ringkasan ke tabel agregat audit, serta memperbarui cache dashboard.
- **Jadwal Eksekusi**: Harian pukul 01:00 WIB (`0 18 * * *` UTC).
- **Idempotensi**: Idempoten. Perhitungan ulang menggantikan catatan tanggal yang bersangkutan tanpa mengubah saldo historis.
- **Cara Menjalankan Manual**:
  ```bash
  php artisan hospital:efficiency-report 2026-11-09
  ```

### 1.4 `hospital:telemetry-scan`
- **Tujuan**: Memindai anomali telemetri vital pasien bangsal intensif, mengevaluasi pelanggaran SpO2/nadi via algoritma B5, dan memicu eskalasi alarm Code Blue jika terjadi pelanggaran ganda dalam 3 menit.
- **Jadwal Eksekusi**: Setiap 1 menit (`* * * * *`).
- **Idempotensi**: Idempoten. Pemicuan alarm Code Blue dilindungi constraint unik per episode perawatan aktif.
- **Cara Menjalankan Manual**:
  ```bash
  php artisan hospital:telemetry-scan
  ```

### 1.5 `hsp:audit` (Command Audit Dua Sumber)
- **Tujuan**: Memverifikasi rekonsiliasi antara catatan operasional modul Hospital dengan sistem Buku Besar Akuntansi (General Ledger), integritas hash chain paspor kesehatan, status okupansi ranjang anti-bentrok, dan konsistensi data medis terenkripsi.
- **Cara Menjalankan**:
  ```bash
  php artisan hsp:audit
  ```
- **Kriteria Lulus**: Keluar dengan exit code 0 (`HEALTHY`). Jika terdapat selisih pembukuan atau anomali data, keluar dengan exit code 1 dan mencantumkan rincian entitas rusak.
