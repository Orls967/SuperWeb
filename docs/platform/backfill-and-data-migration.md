# Panduan Arsitektur Backfill Data Skala Besar & Migrasi Asinkron

Dokumen ini menjelaskan rancangan subsistem pemrosesan backfill data pada modul Platform, memastikan migrasi jutaan baris data berjalan secara idempoten, terfragmentasi (chunked), tahan terhadap kegagalan pekerja (fault-tolerant), dan tidak membebani replikasi database produksi.

---

## 1. Tantangan Pemrosesan Data Massal di Lingkungan Terdistribusi

Ketika melakukan migrasi data pada tabel produksi dengan skala jutaan baris:
- **Kelebihan Beban Memori**: Memuat seluruh dataset ke dalam memori PHP akan memicu batas kehabisan memori (`Allowed memory size exhausted`).
- **Kunci Transaksi Panjang**: Menjalankan kueri pembaruan tunggal dalam rentang besar mengunci indeks clustered dan memicu deadlocks.
- **Kegagalan Pekerja di Tengah Jalan**: Jika pekerja (worker process) mengalami kill sinyal (OOM atau deployment), pekerjaan yang belum selesai harus dapat dilanjutkan dari titik terakhir (checkpoint) tanpa mengulang dari awal atau menduplikasi data.
- **Beban I/O dan Replikasi**: Pembaruan tanpa pembatasan laju (rate limiting) dapat memicu kelambatan replikasi MySQL (slave lag puluhan detik).

---

## 2. Arsitektur Idempotent Chunking

Platform membagi dataset besar menjadi potongan-potongan terisolasi (`DataBackfillChunk`):

```
[ Tabel Target: 1.000.000 Baris ]
             │
             ├─► Chunk 1: ID 1       - 10.000    (Status: completed)
             ├─► Chunk 2: ID 10.001  - 20.000    (Status: completed)
             ├─► Chunk 3: ID 20.001  - 30.000    (Status: processing)
             ├─► Chunk 4: ID 30.001  - 40.000    (Status: pending)
             └─► Chunk N: ID N       - ...       (Status: pending)
```

### Komponen Pengendali:
1. **AdaptiveChunkSizer**: Menyesuaikan ukuran chunk secara dinamis berdasarkan metrik latensi p95 database. Jika kueri lambat, ukuran chunk diperkecil (misal dari 5.000 menjadi 1.000).
2. **BackfillRateLimiter**: Menjaga throughput berada dalam ambang batas aman (misal maksimum 500 baris/detik) untuk mencegah lonjakan CPU database.
3. **BackfillIntegrityChecksumVerifier**: Menghitung hash kriptografis SHA-256 dari baris yang diproses sebelum dan sesudah transformasi untuk menjamin tidak ada data yang hilang atau rusak.

---

## 3. Siklus Hidup Chunk dan Penanganan Kegagalan

Setiap chunk memiliki siklus hidup status:
- `pending`: Menunggu dialokasikan ke pekerja antrean.
- `processing`: Sedang dieksekusi oleh salah satu worker.
- `completed`: Berhasil dieksekusi dan diverifikasi.
- `failed`: Mengalami exception saat pemrosesan.

### Mekanisme Resumption & Dead-Letter:
- Jika pekerja mati tiba-tiba saat status masih `processing`, komponen `BackfillResumptionCoordinator` akan mendeteksi status usang (stale) setelah batas waktu 30 menit dan mengembalikan chunk ke status `pending`.
- Setiap chunk memiliki penghitung `retry_count`. Jika kegagalan berulang melampaui batas maksimum (misal 3 kali percobaan), chunk dialihkan ke status `failed` dan dicatat ke `DeadLetterChunkHandler` untuk diinvestigasi secara manual tanpa menghentikan pemrosesan chunk lainnya.

---

## 4. Perintah Artisan Operasional Backfill

### Menjalankan Pekerjaan Backfill:
```bash
php artisan platform:backfill-run --job-name="dual_write_uuid_migration" --chunk-size=2500
```

### Memeriksa Status dan Kemajuan:
```bash
php artisan platform:backfill-inspect --job-name="dual_write_uuid_migration"
```
Keluaran perintah mencakup:
- Persentase penyelesaian keseluruhan.
- Jumlah chunk berstatus pending, processing, completed, dan failed.
- Throughput rata-rata baris per detik.
- Estimasi waktu penyelesaian (ETA).

---

## 5. Audit dan Rekonsiliasi Integritas Data

Setelah seluruh chunk selesai diproses:
1. `DataReconciliationInspector` melakukan pemindaian acak pada 1% sampel baris data.
2. Membandingkan kolom sumber dan kolom target untuk memastikan konsistensi nilai.
3. Menghasilkan laporan audit kepatuhan integritas yang disimpan di `BackfillCheckpointAuditStore`.

Dengan pendekatan arsitektur ini, modul Platform menjamin proses backfill data berjalan secara aman, terukur, dan tidak mengganggu performa transaksi pengguna aktif.
