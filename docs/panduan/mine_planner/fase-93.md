# Panduan Operasional Peran: Mine Planner (`mine_planner`) — Fase 93

## 1. Tanggung Jawab Utama
Mine Planner bertanggung jawab atas perancangan rencana penambangan bulanan, pemodelan cadangan blok pit berbasis voxel IDW/Kriging, dan penyesuaian alokasi shift harian berdasarkan curah hujan serta perawatan alat.

## 2. Alur Tugas & Prosedur Harian
1. **Membuat Rencana Blok Tambang Bulanan**:
   - Buka menu **Mine Planning** di `/mining/plans`.
   - Pilih Site (misal `SITE-01 Morowali`) dan Pit (misal `PIT-W01 Pit Barat`).
   - Masukkan periode bulan (`2026-11`), komoditas (`NICKEL`), target tonase (misal `50.000.000 kg` = 50.000 ton), dan target kadar (`18.000 ppm` = 1,8% Ni).
   - Klik **Generate & Time-Phase Plan**.
   - Sistem secara otomatis memanggil algoritma M1 `WeightedTimePhasing` untuk memecah kuota bulanan ke alokasi harian dan shift kerja dengan bobot ketersediaan.

2. **Menyesuaikan Rencana Akibat Kejutan Hujan (Re-Planning)**:
   - Jika curah hujan tinggi menyebabkan jam kerja hilang $> 4$ jam, buka daftar rencana aktif di `/mining/plans`.
   - Masukkan jam hilang tambahan (`additional_rain_hours`) pada form **Re-Plan Shift**.
   - Klik **Re-Plan Shift Targets**. Sistem akan mendistribusikan ulang sisa tonase ke hari-hari berikutnya secara proporsional.

3. **Menjalankan Estimasi Cadangan Voxel (CLI)**:
   - Jalankan command:
     ```bash
     php artisan mining:estimate-reserve PIT-W01
     ```
   - Command ini menginterpolasi kadar nikel 3D dari borehole assays menggunakan Inverse Distance Weighting dan mencatat blok model ke tabel `min_block_model_voxels`.

## 3. Verifikasi Hasil pada Sistem
- **Buku Besar / Ledger**: Perencanaan awal tidak menghasilkan mutasi kas, namun memvalidasi akun biaya penambangan yang akan diakui saat shift close (`min:hauling_contractor_expense:IDR`).
- **Dashboard Kurva-S**: Rencana yang dibuat menjadi garis dasar (Planned Cumulative Line) pada grafik kurva-S produksi pit.

