# Runbook Operasional Sistem & Prosedur Darurat Perhotelan (Fase 91)

Dokumen ini berisi instruksi teknis pengoperasian baris perintah (CLI artisan), jadwal pekerjaan latar belakang (cron jobs), dan penanganan insiden darurat.

---

## 1. Jadwal Perintah Terjadwal (Artisan Cron Registry)

| Perintah Baris Perintah | Frekuensi Jadwal | Idempotensi | Prosedur Pemulihan Saat Gagal |
| :--- | :--- | :--- | :--- |
| `php artisan hotel:rate-publish {property} {from} {to}` | Harian (00:05) | Aman dijalankan ulang; versi tarif lama ditandai superseded. | Jalankan ulang manual untuk rentang tanggal yang belum memiliki versi aktif. |
| `php artisan hotel:overbooking-plan {date}` | Harian (06:00) | Menghasilkan rekomendasi kuota k harian per tipe kamar. | Cek tabel parameter distribusi no-show jika kalkulasi tidak konvergen. |
| `php artisan hotel:housekeeping-plan {date}` | Harian (08:00) | Menugaskan kamar kotor ke jadwal harian staf kamar. | Periksa apakah ada kamar checkout baru pasca jam 08:00 dan jalankan ulang. |
| `php artisan hotel:setback-scan` | Tiap 30 Menit | Memindai kamar tanpa gerak > 15 menit dan menyalakan mode hemat AC. | Pastikan koneksi gateway IoT dan adapter Core DigitalTwin responsif. |
| `php artisan hotel:apoa-submit` | Tiap 2 Jam | Mengirimkan laporan tamu WNA tertunda ke imigrasi. | Jika koneksi portal imigrasi putus, laporan tetap berstatus pending dan terkirim pada siklus berikutnya. |
| `php artisan hotel:audit` | Harian (02:00) | Membandingkan fisik folio dengan ledger; keluar 0 jika seimbang. | Bila keluar non-zero, periksa entri rekonsiliasi yang belum seimbang. |

---

## 2. Prosedur Pemulihan Kegagalan Night Audit (Disaster Recovery)

Proses penutupan hari (Night Audit) dirancang tahan terhadap kegagalan mendadak (server crash, listrik padam, kegagalan database):
1. **Pemeriksaan Checkpoint**:
   - Sistem mencatat kemajuan proses audit pada kolom `last_checkpoint` di tabel `htl_business_dates`.
2. **Langkah Pemulihan Re-Run**:
   - Operator cukup menjalankan kembali perintah penutupan hari dengan tanggal bisnis yang sama.
   - Sistem mendeteksi snapshot harian yang sudah tercatat sebagian, melanjutkan pembebanan yang belum diproses, dan memajukan tanggal bisnis tepat 1 hari.
   - Panggilan berulang tidak akan menggandakan pembebanan kamar ke folio tamu (karena dilindungi nomor kunci unik transaksi).
