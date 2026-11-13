# Telemetri Armada, Konsumsi Energi & Manajemen Baterai — Fase 279

- **Fase**: 279 (W8-P6.3)
- **Komponen Utama**: `SimulatedDeviceAdapterInterface`, `SimulatedAmrDeviceAdapter`, `IngestSimulatedTelemetryBatchAction`, `RobotTelemetryIngestor`, `RobotEnergyConsumptionEstimator`, `RobotBatteryDegradationTracker`, `ChargingBayContentionResolver`

## 1. Arsitektur Akuisisi Telemetri
Sistem memisahkan protokol perangkat keras fisik/simulasi dari logika bisnis gudang melalui kontrak `Modules\Wms\Contracts\SimulatedDeviceAdapterInterface`:
- Pada lingkungan pengujian & simulasi, `WmsServiceProvider` mengikat kontrak tersebut ke `SimulatedAmrDeviceAdapter`.
- `SimulatedAmrDeviceAdapter::pollTelemetry($robotCode, $seed)` menghasilkan pembacaan deterministik `battery_pct`, `error_count`, `position_x`, dan `position_y` berdasarkan hash deterministik `robotCode` dan `seed` (tanpa RNG non-deterministik).

### Alur Ingesti Batch (`IngestSimulatedTelemetryBatchAction`)
1. Mengunci transaksi database (`DB::transaction`) dan mengambil seluruh `Robot` pada `warehouse_id` tertentu terurut berdasarkan `id`.
2. Untuk setiap robot:
   - Memanggil `$this->deviceAdapter->pollTelemetry($robot->robot_code, $seed + $idx)`.
   - Menyimpan rekaman immutable ke `wms_robot_telemetries` lewat `RobotTelemetryIngestor::ingest()` dengan key idempoten `tel:{robot_id}:{tickKey}` (memenuhi Aturan `arch:scan` A4).
   - Menghitung skor kesehatan `[0..1000]` melalui `RobotHealthScorer::score($battery, $errorCount, $downtime)`.
   - Menghitung jarak tempuh aman maksimum (`maxSafeRangeMeters`) melalui `RobotEnergyConsumptionEstimator`.
3. **Pembukaan Insiden Otomatis**:
   - Apabila `health_score < 400` (`CRITICAL_HEALTH_THRESHOLD`) atau `battery_pct < 15%` (`LOW_BATTERY_ALERT_PCT`), sistem otomatis mencatat `RobotSafetyIncident` dengan `incident_code = INC-TEL-{robot_id}-{tickKey}` dan mengirim tugas ke `TaskInboxService` (`assignee_role = hub_operator`).

## 2. Model Konsumsi Energi Diskret (`RobotEnergyConsumptionEstimator`)
Seluruh perhitungan energi menghindari `float` dan memakai satuan miliwatt-jam (`mWh`):
- **Kapasitas Standar Pak Baterai**: `480_000 mWh` (`4_800 mWh` per `1%` baterai).
- **Biaya Lintasan Dasar**: `120 mWh / meter`.
- **Faktor Beban Payload**: `+4 mWh / (meter * kg)`.
- **Overhead Aktuator per Misi**: `1_500 mWh` (siklus angkat/turun & docking).
- **Penalti Kemiringan Ramp**: `intdiv(travelMwh * zone_slope_pct, 100)`.
- **Pembulatan Aman**: Persentase drain selalu dibulatkan ke atas (`intdiv($missionMwh + 4_799, 4_800)`) agar robot tidak pernah kehabisan daya di tengah lorong.

Setiap evaluasi batch menghasilkan `RobotEnergyCertificate` yang diverifikasi langsung oleh `verify()` sebelum hasil digunakan oleh `RobotFleetDispatchOptimizer`.

## 3. Degradasi Baterai & Penjadwalan Bay Charger
1. **Pelacakan Degradasi (`RobotBatteryDegradationTracker`)**:
   - Menghitung `health_permil = max(0, 1000 - intdiv(cycle_count, 10) - deep_discharge_events * 3)`.
   - Unit dengan `health_permil <= 750‰` ditandai `replace_recommended = true` pada `BatteryDegradationCertificate`.
2. **Alokasi Bay Charger (`ChargingBayContentionResolver`)**:
   - Menggunakan pewarnaan interval pada `DEFAULT_MAX_BAYS = 4` (`BAY-01..BAY-04`).
   - Memastikan tidak ada dua robot menempati bay yang sama secara bersamaan; kelebihan permintaan ditunda (`deferred_robot_ids`) ke siklus berikutnya.
3. **Pemeliharaan Preventif (`RobotMaintenanceWindowPlanner`)**:
   - Membatasi jumlah unit yang ditarik ke maintenance bersamaan maksimal `15%` dari total armada (`MAX_CONCURRENT_MAINTENANCE_PCT = 15`), memprioritaskan unit dengan `health_score` terendah terlebih dahulu.
