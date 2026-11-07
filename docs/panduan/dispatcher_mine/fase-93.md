# Panduan Operasional Peran: Mine Dispatcher (`dispatcher_mine`) — Fase 93

## 1. Tanggung Jawab Utama
Mine Dispatcher bertanggung jawab atas pengaturan alokasi armada truk ke loader/shovel secara real-time, monitoring siklus hauling di papan Gantt, dan melakukan intervensi manual (override) jika terjadi kendala operasional lapangan.

## 2. Alur Tugas & Prosedur Harian
1. **Membuka Papan Dispatch Shift**:
   - Buka menu **Fleet Dispatch** di `/mining/dispatch`.
   - Periksa ketersediaan armada truk dan shovel aktif pada shift berjalan.
   - Amati indikator **Match Factor (MF)**. Jika status menunjukkan `UNDERTRUCKED` (MF < 1.0) atau `OVERTRUCKED` (MF > 1.0), lakukan penyesuaian penugasan.

2. **Penugasan Truk Otomatis (M2 Need-Time)**:
   - Pilih unit truk yang baru tiba di dump hopper.
   - Pilih target pit dan shift kerja.
   - Klik **Jalankan M2 Dispatcher**. Sistem akan menugaskan truk tersebut ke shovel dengan waktu mulai pemuatan paling awal ($\min T_{start}$).

3. **Manual Override Penugasan Siklus**:
   - Jika shovel mengalami breakdown atau ada hambatan di jalur hauling, buka tabel **Haul Cycles**.
   - Pada baris siklus yang bersangkutan, pilih status baru (`HAULING`, `DUMPING`, `DELAYED`, `COMPLETED`).
   - Masukkan alasan override wajib (misal: "Shovel EX-02 breakdown hidrolik, muatan dialihkan ke Stockpile 3").
   - Klik **Override**. Riwayat transisi tersimpan secara append-only di `min_haul_cycle_histories`.

## 3. Verifikasi Hasil pada Sistem
- Siklus yang berstatus `COMPLETED` akan dihitung tonasenya saat penutupan shift (`php artisan min:shift-close`).
- Setiap override tercatat lengkap dengan identitas dispatcher dan stempel waktu.
