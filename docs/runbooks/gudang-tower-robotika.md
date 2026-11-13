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


## 4. Referensi Detail Seluruh Command CLI (`robotics:*`)
### 4.1 `robotics:allocate {warehouse_id}`
- **Tujuan**: Mengalokasikan misi berstatus `queued` ke armada robot online yang tersedia (`battery_pct >= 20%`).
- **Opsi**: `--window=SHIFT-1` (label jendela alokasi), `--actor=` (ID user operator).
- **Keluaran Sukses (Exit 0)**: Mencetak jumlah misi teralokasi, daftar `unassigned_mission_ids`, serta total biaya Hungarian.
- **Tindak Lanjut Jika Banyak Unassigned**: Periksa apakah zona target sedang berstatus `staff_blocked = true` atau jumlah robot online kurang dari antrean misi.

### 4.2 `robotics:reservation-sweep`
- **Tujuan**: Membersihkan reservasi zona yang sudah melewati jendela waktu atau tertaut pada misi yang sudah terminal (`completed`, `failed`, `fallback_manual`).
- **Keluaran Sukses (Exit 0)**: Mencetak jumlah reservasi yang dilepas (`released`).

### 4.3 `robotics:wave-plan {warehouse_id}`
- **Tujuan**: Mengelompokkan order ke dalam wave FFD `J2` berdasarkan `carrier` dan `cutoff` dari `CarrierCutoffProvider`.
- **Opsi**: `--capacity=70` (kapasitas bin per wave), `--batch=BATCH-CLI`.
- **Keluaran Sukses (Exit 0)**: Mencetak jumlah wave terbentuk, utilisasi per wave, dan hash `WaveBinPackerCertificate`.

### 4.4 `robotics:charge-plan {warehouse_id}`
- **Tujuan**: Menyusun jadwal pengisian daya untuk robot dengan baterai di bawah ambang aman dan menyimpannya ke `wms_robot_charge_schedules`.
- **Keluaran Sukses (Exit 0)**: Mencetak jumlah slot charger yang dijadwalkan.

### 4.5 `robotics:deadlock-scan {warehouse_id}`
- **Tujuan**: Membangun graf tunggu dari reservasi aktif dan antrean misi, menjalankan `TrafficDeadlockDetector`, dan memutus satu victim berprioritas terendah bila siklus ditemukan.
- **Keluaran Sukses (Exit 0)**: Mencetak `deadlock=false` atau nama `victim_node` yang telah diputus.

### 4.6 `robotics:roi {warehouse_id}`
- **Tujuan**: Mengevaluasi ulang metrik `npv_minor`, `irr_bps`, dan `payback_months` seluruh proposal ROI yang masih `pending` menggunakan `AutomationNpvCalculator` (`J3`).
- **Keluaran Sukses (Exit 0)**: Mencetak tabel ringkas proposal beserta status NPV (`positif`/`negatif`).

### 4.7 `robotics:simulate {scenario}`
- **Tujuan**: Menjalankan simulasi skenario kepadatan lalu lintas atau kejutan operasional di atas `SimulationRunnerService` mode `sandbox`.
- **Opsi**: `--days=3`, `--seed=27901`.
- **Skenario Standar**: `baseline`, `traffic_density`, `two_robots_offline_one_zone_blocked`.
- **Verifikasi Wajib**: Output harus mencantumkan `hash_identik=true` dan `Audit=PASS`. Jika hash produksi berubah, command melempar `RuntimeException` (exit non-0).

### 4.8 `robotics:audit`
- **Tujuan**: Menjalankan pemeriksaan dua sumber deterministik (`RoboticsTwoSourceAuditService`) terhadap seluruh data robotika di basis data.
- **Keluaran Sukses (Exit 0)**: `robotics:audit PASSED (issues=0, hash=<sha256>).`
- **Keluaran Gagal (Exit 1)**: Mencetak daftar terformat dari `WmsRoboticsAuditCliReportFormatter` beserta entitas bermasalah (misal `MIS-CORRUPT-01`).

## 5. Daftar Periksa Pra-Rilis & Pemulihan Darurat
1. Jalankan `php artisan db:seed --class="Modules\\Wms\\database\\seeders\\RoboticsT1Seeder"` pada lingkungan staging.
2. Jalankan `php artisan robotics:simulate two_robots_offline_one_zone_blocked` -> pastikan `hash_identik=true` dan `Audit=PASS`.
3. Jalankan `php artisan robotics:audit` -> pastikan exit code `0`.
4. Jika terjadi insiden keselamatan di lantai gudang:
   - Aktifkan `staff_blocked = true` pada zona terkait di `/wms/robotics/zones`.
   - Tinjau insiden pada `/wms/robotics/incidents` dan pastikan operator mengonfirmasi tugas manual `MAN-*` di `TaskInbox`.
