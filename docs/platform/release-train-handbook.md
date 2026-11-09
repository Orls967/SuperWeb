# Release Train Handbook — Platform Cadence & Release Management

Buku panduan ini menguraikan arsitektur Release Train pada modul Platform, mencakup siklus hidup rilis mingguan, tata kelola hotfix, gerbang pembekuan kode (code freeze), dan penghitungan metrik DORA.

---

## 1. Konsep Utama Release Train

Release Train adalah mekanisme orkestrasi rilis berkala yang terjadwal secara deterministik:
- Rilis berangkat tepat waktu sesuai jadwal tanpa menunggu fitur yang terlambat.
- Fitur yang belum siap atau belum melewati uji regresi tidak boleh menunda keberangkatan train; fitur tersebut ditunda ke train berikutnya.
- Model ini mengurangi ketidakpastian waktu rilis dan memastikan integritas stabilitas sistem produksi.

---

## 2. Siklus Hidup Release Train

State machine train dikendalikan oleh `ReleaseTrainStateMachine` dengan tahapan status sebagai berikut:

```
[ open ] ──► [ freezing ] ──► [ frozen ] ──► [ deploying ] ──► [ deployed ]
  │                                                                 │
  ▼                                                                 ▼
[ cancelled ]                                                 [ archived ]
```

### Tahapan Status:
1. **Open**: Kereta rilis dibuka untuk menerima Change Request yang telah disetujui. Pengembang mengaitkan perubahan fitur ke versi train yang aktif.
2. **Freezing**: Periode pembekuan awal di mana penambahan fitur baru dihentikan. Hanya perbaikan bug penting (bugfix) yang diizinkan masuk.
3. **Frozen**: Kode dikunci sepenuhnya untuk pengujian regresi menyeluruh, verifikasi keamanan skema, dan pembuatan manifest rilis final.
4. **Deploying**: Penerapan artefak ke lingkungan staging, pre-production, dan dilanjutkan ke produksi bertahap.
5. **Deployed**: Seluruh paket rilis berhasil diverifikasi di produksi.
6. **Archived**: Kereta rilis ditutup dan diarsipkan sebagai riwayat resmi.
7. **Cancelled**: Pembatalan kereta rilis dalam keadaan kritis (sangat jarang terjadi).

---

## 3. Protokol Penanganan Hotfix (Out-of-Band Patches)

Ketika terjadi kegagalan sistemik atau regresi kritis pada train yang sedang berjalan atau sudah dirilis:
- Hotfix disuntikkan melalui `InjectReleaseHotfixAction`.
- Hotfix diklasifikasikan ke dalam 3 tingkat keparahan (`HotfixSeverity`):
  - `patch`: Perbaikan minor tanpa perubahan skema database. Versi semver dinaikkan pada komponen patch (misal: 1.4.0 -> 1.4.1).
  - `urgent`: Perbaikan signifikan yang mempengaruhi alur transaksi utama.
  - `critical`: Perbaikan darurat keamanan data atau ketersediaan sistem.
- Setiap hotfix wajib melalui karantina menggunakan `HotfixImpactQuarantineManager` untuk menjamin tidak ada regresi sampingan pada train aktif.

---

## 4. Manifest Rilis dan Pembuatan Catatan Rilis (Release Notes)

Sebelum train memasuki tahap `frozen`, sistem menyusun manifest rilis melalui `ReleaseTrainManifestBuilder`.
Manifest mengagregasi data berikut:
- Daftar Change Request yang diikutsertakan beserta nomor tiket.
- Daftar kontributor dan reviewer yang memberikan tanda tangan digital.
- Ringkasan perubahan skema database dan rencana mitigasi risiko.
- Hash commit git awal dan git akhir rilis.

Perintah artisan otomatis untuk menghasilkan catatan rilis:
```bash
php artisan platform:release-notes --train-version="v1.4.0" --format=markdown
```
Catatan rilis yang dihasilkan mencakup bagian Fitur Baru, Perbaikan Bug, Peringatan Pembaruan Kritis, dan Petunjuk Migrasi Skema.

---

## 5. Integrasi Metrik DORA (DevOps Research and Assessment)

Sistem platform secara otomatis melacak dan mengkalkulasi 4 metrik DORA utama menggunakan `DoraMetricsCalculator`:

### 1. Deployment Frequency (Frekuensi Penerapan)
Mengukur seberapa sering organisasi berhasil menerapkan kode ke lingkungan produksi:
- **Elite**: Multiple deployments per day
- **High**: Antara sekali per hari hingga sekali per minggu
- **Medium**: Antara sekali per minggu hingga sekali per bulan
- **Low**: Lebih jarang dari sekali per bulan

### 2. Lead Time for Changes (Waktu Tunggu Perubahan)
Waktu yang dibutuhkan dari saat commit kode dibuat hingga kode tersebut aktif berjalan di produksi.

### 3. Change Failure Rate (Tingkat Kegagalan Perubahan)
Persentase penerapan produksi yang menyebabkan insiden, memerlukan hotfix segera, atau berujung pada rollback.

### 4. Mean Time to Restore (MTTR / Waktu Pemulihan Rata-rata)
Durasi rata-rata yang diperlukan untuk memulihkan layanan setelah terjadi kegagalan atau insiden penerapan di produksi.

Untuk menghasilkan laporan metrik DORA periodik:
```bash
php artisan platform:dora-metrics --window-days=30
```

---

## 6. Prosedur Pembekuan Kode Operasional (Code Freeze SOP)

Berikut langkah operasional saat jendela rilis mingguan:
1. **H-2 Keberangkatan**: Menjalankan perintah pembekuan train:
   ```bash
   php artisan platform:release-train-freeze --version="v1.4.0"
   ```
2. **H-1 Keberangkatan**: Menjalankan linting skema database:
   ```bash
   php artisan platform:migration-lint
   ```
3. **Hari Keberangkatan**: Membuka deployment canary dan memantau error rate selama jendela observasi minimum 30 menit.

