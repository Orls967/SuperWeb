# Runbook Simulasi Kejutan & Evaluasi Kapasitas — Fase 279

- **Fase**: 279 (W8-P6.3)
- **Komponen Utama**: `RoboticsSimulateCommand`, `RoboticsSimulationScenarioRunner`, `RoboticsSimulationTickHandler`, `RoboticsT1SimulationReportService`

## 1. Kapan Menjalankan Simulasi Pra-Shift?
Simulasi sandbox P3 wajib dijalankan pada kondisi berikut:
1. Sebelum memulai shift puncak (promo/harbolnas) untuk memastikan kapasitas armada dan bay charger mencukupi.
2. Setelah melakukan perubahan pada `capacity_k` zona atau menandai zona baru sebagai `staff_blocked = true`.
3. Sebagai bagian dari verifikasi rilis L.4 untuk memastikan `hash_identik=true` dan `Audit=PASS`.

## 2. Menjalankan Skenario Standar & Skenario Kejutan
```bash
# Skenario baseline (kondisi normal T1)
php artisan robotics:simulate baseline --days=3 --seed=27901

# Skenario kejutan (2 robot tambahan offline + 1 zona diblokir staf)
php artisan robotics:simulate two_robots_offline_one_zone_blocked --days=3 --seed=27901
```

## 3. Membaca Keluaran CLI `robotics:simulate`
Keluaran terdiri atas dua baris terstruktur:
1. **Baris Status Sandbox**:
   `robotics:simulate [two_robots_offline_one_zone_blocked] run=<uuid> fingerprint=<12-hex> online_robots=16 simulated_slots=5 hash_identik=true`
   - `fingerprint`: harus identik jika `--seed` dan data awal sama.
   - `hash_identik=true`: membuktikan `computeProductionStateHash()` sebelum == sesudah.
2. **Baris Ringkasan T1 (`RoboticsT1SimulationReportService::formatCliSummary`)**:
   `Warehouse=<uuid> | Robots=20 | Missions=120 | CompletionPermil=116 | FallbackPermil=216 | Zones=6 | Waves=29 (Orders=0) | ROI_Proposals=4 (NegNPV=1) | Audit=PASS`
   - Periksa `Audit=PASS`: jika `FAIL`, segera jalankan `php artisan robotics:audit` untuk melihat rincian isu.

## 4. Menangani Hasil Simulasi Buruk
- Jika `online_robots` terlalu rendah saat 2 unit offline: periksa jadwal charging (`robotics:charge-plan`) dan batasi maintenance bersamaan (`RobotMaintenanceWindowPlanner`).
- Jika `simulated_zone_slots` menyempit tajam saat 1 zona diblokir: buka rute alternatif atau aktifkan pembatasan kecepatan koeksistensi `800 mm/s` pada zona `capacity_k >= 2`.
