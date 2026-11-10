# Prosedur Audit Search & Inbox Dua Sumber

## Latar Belakang & Prinsip
Audit integritas data pencarian dan tugas kotak masuk menggunakan metodologi verifikasi dua sumber (*two-source dual audit*):
1. **Sumber Primer (Database Status)**: Tabel `core_search_indices` dan `core_tasks`.
2. **Sumber Pembanding (Log Telemetri / Event Ledger)**: Tabel `core_search_query_logs` dan `core_task_histories`.

## Prosedur Eksekusi
1. Jalankan perintah audit terstandarisasi:
   ```bash
   php artisan search:audit --verify-dual-source
   ```
2. Sistem akan memvalidasi koherensi data:
   - Memastikan setiap entitas terindeks di `core_search_indices` memiliki entri posting term yang valid di `core_search_terms`.
   - Memastikan tidak ada dangling posting term tanpa induk dokumen indeks.
   - Memeriksa apakah status terakhir pada `core_tasks` selaras dengan riwayat event terakhir di `core_task_histories`.
3. Deteksi Anomali:
   - Jika terdapat perbedaan antara status `core_tasks.status` dengan urutan riwayat di `core_task_histories`, command akan memicu status `CORRUPTED` dan mencatat selisih ke fixture audit.
   - Jika integritas bersih, status dinyatakan `CLEAN`.

## Penanganan Saat Status CORRUPTED
1. Lakukan freeze terhadap mutasi indexing baru pada modul yang bermasalah.
2. Eksekusi rekonsiliasi manual atau rebuild indeks:
   ```bash
   php artisan search:reindex --module=TARGET_MODULE --force
   ```
3. Lakukan verifikasi ulang hingga command mengembalikan exit code 0 dan status `CLEAN`.
