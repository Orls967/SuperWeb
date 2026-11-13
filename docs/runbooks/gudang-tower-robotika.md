# Runbook Operasional — Gudang, Control Tower & Robotika (Fase 279)

## 1. Perintah Operasional CLI (`robotics:*`)
Seluruh command di bawah ini dijalankan dari root aplikasi dengan akun berwenang:

| Command | Fungsi | Frekuensi / Pemicu |
|---|---|---|
| `php artisan robotics:allocate {warehouse_id}` | Menjalankan alokasi Hungarian untuk misi `queued` ke robot online | Setiap siklus dispatch (1–5 menit) |
| `php artisan robotics:reservation-sweep` | Membersihkan reservasi zona kedaluwarsa & melepas lock usang | Setiap 5 menit (cron/scheduler) |
| `php artisan robotics:wave-plan {warehouse_id}` | Menyusun wave order FFD `J2` sesuai cutoff kurir | Sebelum jendela picking batch |
| `php artisan robotics:charge-plan {warehouse_id}` | Menjadwalkan slot pengisian baterai untuk robot `< 30%` | Setiap 15 menit |
| `php artisan robotics:deadlock-scan {warehouse_id}` | Mendeteksi siklus graf tunggu (`TrafficDeadlockDetector`) & memutus victim | Setiap 1–2 menit |
| `php artisan robotics:roi {warehouse_id}` | Menghitung ulang metrik NPV/IRR proposal ROI yang masih `pending` | Sesuai kebutuhan perencanaan |
| `php artisan robotics:simulate {scenario}` | Menjalankan simulasi sandbox P3 (`baseline` / `two_robots_offline_one_zone_blocked`) | Uji kapasitas / pra-shift |
| `php artisan robotics:audit` | Audit dua sumber deterministik seluruh integritas tabel robotika | Setiap jam & sebelum rilis |

## 2. Prosedur Penanganan Insiden Lapangan
### Skenario A: Robot Terjebak Deadlock di Lorong Sempit
1. Jalankan `php artisan robotics:deadlock-scan <warehouse_id>` untuk mengidentifikasi siklus tunggu dan memilih victim berprioritas terendah.
2. Pastikan misi victim berpindah ke `failed` (`DEADLOCK_VICTIM_PREEMPTED`) dan reservasi zonanya dilepas.
3. Jika robot tetap tidak merespons setelah 2 kali retry, biarkan sistem mengalihkan ke `fallback_manual` dan kirim operator manusia menggunakan tugas `MAN-<mission_code>`.

### Skenario B: Pekerja Staf Harus Memasuki Zona Robot
1. Set `staff_blocked = true` (dengan `block_start_min` & `block_end_min`) pada `TrafficZone` terkait melalui panel `/wms/robotics/zones`.
2. Jalankan `php artisan robotics:reservation-sweep` untuk memastikan tidak ada misi baru memasuki zona selama jendela blokir.
3. Setelah staf keluar, kembalikan `staff_blocked = false` dan jalankan `php artisan robotics:allocate <warehouse_id>`.

### Skenario C: Pengujian Simulasi Sandbox Pra-Shift
1. Jalankan `php artisan robotics:simulate two_robots_offline_one_zone_blocked --days=3 --seed=27901`.
2. Pastikan keluaran mencetak `hash_identik=true` dan `Audit=PASS`.
3. Jika `hash_identik=true` tidak terpenuhi (exception), hentikan eksekusi simulasi dan periksa koneksi database sandbox.

## 3. Seeder Deterministik T1
Untuk mengisi dataset simulasi standar (20 robot, 6 zona, 120 misi di 8 status, 60 order lintas 5 carrier, 4 proposal ROI):
```bash
php artisan db:seed --class="Modules\\Wms\\database\\seeders\\RoboticsT1Seeder"
```
