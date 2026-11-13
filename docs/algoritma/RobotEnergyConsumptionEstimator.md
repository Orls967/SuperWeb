# Algoritma — `RobotEnergyConsumptionEstimator`

- **Kelas**: `Modules\Wms\Domain\Algorithms\RobotEnergyConsumptionEstimator`
- **Sertifikat**: `Modules\Wms\Domain\Certificates\RobotEnergyCertificate`
- **Uji Vektor**: `tests/Acceptance/WarehouseRobotics/RoboticsAlgorithmsVectorTest.php`

## 1. Model Fisika Diskret (Tanpa Floating-Point)
Agar estimasi konsumsi energi robot AMR/AGV deterministik di seluruh arsitektur CPU tanpa galat IEEE-754, perhitungan memakai satuan miliwatt-jam (`mWh`) dan persentase baterai integer:
- `BASE_MWH_PER_METER = 120` mWh/meter.
- `PAYLOAD_MWH_PER_METER_KG = 4` mWh/(meter * kg).
- `ACTUATOR_OVERHEAD_MWH = 1_500` mWh per misi (lift/gripper/docking).
- `MWH_PER_BATTERY_PCT = 4_800` mWh per `1%` baterai (pak baterai 480 Wh).
- `DEFAULT_RESERVE_MIN_PCT = 20%` batas cadangan aman baterai.

Untuk setiap misi ke-`i` dengan jarak `d_i` meter, beban `w_i` kg, dan kemiringan zona `slope_i`%:
1. `travelMwh = d_i * (120 + 4 * w_i)`
2. `slopePenaltyMwh = intdiv(travelMwh * slope_i, 100)`
3. `missionMwh = travelMwh + slopePenaltyMwh + 1_500`
4. `drainPct = intdiv(missionMwh + 4_800 - 1, 4_800)` *(pembulatan ke atas / ceiling)*

## 2. Verifikasi Sertifikat (`RobotEnergyCertificate::verify()`)
Sertifikat menyimpan `totalDrainPct`, `projectedEndBatteryPct`, `totalEnergyMwh`, `allFeasible`, dan `signatureHash = sha256(canonical_json)`. Metode `verify()` memastikan:
- Jumlah `estimated_drain_pct` seluruh item sama persis dengan `totalDrainPct`.
- Jumlah `energy_mwh` seluruh item sama persis dengan `totalEnergyMwh`.
- `max(0, initialBatteryPct - totalDrainPct) === projectedEndBatteryPct`.
- `allFeasible` bernilai `false` apabila `projectedEndBatteryPct < reserveMinBatteryPct`.
- `hash_equals($expectedHash, $this->signatureHash)` bernilai `true`.

## 3. Penggunaan Operasional
- `RobotFleetDispatchOptimizer` memanggil `estimateBatch()` untuk menolak penugasan misi jika robot berisiko kehabisan baterai sebelum mencapai stasiun pengisian.
- `IngestSimulatedTelemetryBatchAction` memanggil `maxSafeRangeMeters()` untuk menghitung sisa jarak aman setiap unit armada pada dashboard Control Tower.
