# Algoritma — `ChargingSchedulePlanner`

- **Kelas**: `Modules\Wms\Domain\Algorithms\ChargingSchedulePlanner`
- **Dipanggil oleh**: `PlanRobotChargingScheduleAction`, `RoboticsChargePlanCommand`

## 1. Tujuan & Aturan Pemilihan Robot
`ChargingSchedulePlanner` menentukan robot mana saja yang perlu masuk ke stasiun pengisian daya pada siklus berikutnya tanpa mengosongkan armada aktif secara serentak:
1. Robot dengan `battery_pct < triggerThresholdPct` (bawaan `35%`) diprioritaskan paling tinggi.
2. Pengurutan dilakukan secara deterministik berdasarkan `battery_pct` menaik, lalu `robot_id` menaik.
3. Durasi slot pengisian dihitung secara integer berdasarkan selisih menuju `target_battery_pct` (bawaan `90%`) dengan laju pengisian tetap (`2%` per menit, dibulatkan ke atas).

## 2. Keterkaitan dengan `ChargingBayContentionResolver`
Setelah `ChargingSchedulePlanner` menghasilkan jendela waktu kandidat `[window_start_min, window_end_min)`, `ChargingBayContentionResolver` melakukan pewarnaan interval terhadap bay charger fisik (`BAY-01..BAY-04`) agar tidak ada dua robot yang dijadwalkan pada bay dan menit yang sama.

## 3. Penjagaan Konkurensi & Audit
`PlanRobotChargingScheduleAction` membungkus penyimpanan `RobotChargeSchedule` dalam transaksi database (`DB::transaction`) dan memastikan setiap jadwal merujuk ke `wms_robots.id` dan `wms_warehouses.id` yang sah.
