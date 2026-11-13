# Spesifikasi Seeder Deterministik `RoboticsT1Seeder` — Fase 279

- **Kelas**: `Modules\Wms\database\seeders\RoboticsT1Seeder`
- **Target Gudang**: `WH-ROB-T1` (`kind = 'fulfillment'`, `is_active = true`)

## 1. Komposisi Data Deterministik
Seeder ini bersifat idempoten (`firstOrCreate` / `updateOrCreate`) dan membangun dataset T1 lengkap:
1. **20 Unit Robot (`wms_robots`)**: `17` unit online dan `3` unit `is_online = false`, dengan variasi `battery_pct` dan `health_score`, serta cerminan `wms_robot_units` untuk kompatibilitas backward.
2. **6 Zona Lalu Lintas (`wms_traffic_zones`)**: mencakup `2` zona lorong sempit (`capacity_k = 1`) dan `1` zona bertanda `staff_blocked = true` untuk menguji interlock keselamatan.
3. **120 Misi (`wms_robot_missions`)**: terdistribusi merata di seluruh `8` status `RobotMissionStateMachine` dengan minimal `10` misi berstatus `fallback_manual`.
4. **60 Order / Wave (`wms_robot_waves`)**: tersebar pada `>= 5` carrier (`JNE`, `SICEPAT`, `ANTERAJA`, `JNT`, `POS`) dengan jam cutoff berbeda.
5. **4 Proposal ROI (`wms_robotics_roi_proposals`)**: `3` ber-NPV positif dan `1` ber-NPV negatif (`npv_minor < 0`) untuk verifikasi kontrol SoD & alasan tertulis.

## 2. Kegunaan dalam Uji Otomatis
`WarehouseRoboticsFullAcceptanceTest` #1 dan #5 memanggil `$this->seed(RoboticsT1Seeder::class)` untuk memverifikasi seluruh rute Control Tower, `IngestSimulatedTelemetryBatchAction`, `RoboticsT1SimulationReportService`, dan `robotics:audit`.
