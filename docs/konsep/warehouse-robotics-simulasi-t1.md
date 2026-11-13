# Spesifikasi Simulasi T1 & Isolasi Sandbox P3 — Robotika Gudang (Fase 279)

- **Fase**: 279 (W8-P6.3)
- **Komponen Utama**: `RoboticsT1Seeder`, `RoboticsSimulationTickHandler`, `RoboticsSimulationScenarioRunner`, `RoboticsT1SimulationReportService`, `RoboticsSimulateCommand`

## 1. Struktur Dataset Deterministik T1 (`RoboticsT1Seeder`)
Untuk menjamin seluruh pengujian penerimaan, audit dua sumber, dan simulasi skenario berjalan pada kondisi awal yang kaya namun 100% deterministik, `RoboticsT1Seeder` membangun data berikut untuk gudang `WH-ROB-T1`:

| Entitas | Jumlah Minimum T1 | Distribusi & Karakteristik Wajib |
|---|---|---|
| `Robot` (`wms_robots`) | `20` unit | Minimal `3` unit berstatus `is_online = false`; sisanya online dengan variasi `battery_pct` (termasuk unit `< 25%` untuk uji charging) |
| `TrafficZone` (`wms_traffic_zones`) | `6` zona | Minimal `2` zona berkapasitas sempit `capacity_k = 1`; minimal `1` zona bertanda `staff_blocked = true` untuk uji interlock |
| `RobotMission` (`wms_robot_missions`) | `120` misi | Mencakup seluruh `8` status SM (`queued`, `assigned`, `moving`, `picking`, `delivering`, `completed`, `failed`, `fallback_manual`) dengan `>= 10` misi `fallback_manual` |
| Order & `RobotWave` (`wms_robot_waves`) | `60` order / `>= 5` carrier | Tersebar di 5 carrier berbeda cutoff (`JNE`, `SICEPAT`, `ANTERAJA`, `JNT`, `POS`) |
| `RoboticsRoiProposal` (`wms_robotics_roi_proposals`) | `4` proposal | Minimal `1` proposal memiliki `npv_minor < 0` untuk uji kewajiban alasan tertulis & SoD |

## 2. Jaminan Isolasi Sandbox Mode P3
Setiap eksekusi simulasi (`php artisan robotics:simulate` atau `RoboticsSimulationScenarioRunner::runScenario()`) wajib mematuhi prinsip **nol mutasi produksi**:
1. Sebelum memulai run di `SimulationRunnerService::start("robotics-{$scenario}", $days, $seed, 'sandbox')`, sistem menghitung `hashBefore = RoboticsTwoSourceAuditService::computeProductionStateHash()`.
2. Hash produksi mencakup jumlah baris dan agregasi checksum dari `wms_robots`, `wms_traffic_zones`, `wms_zone_reservations`, `wms_robot_missions`, `wms_robot_waves`, dan `wms_robotics_roi_proposals`.
3. `RoboticsSimulationTickHandler::handle()` menjalankan perhitungan kapasitas dan alokasi di memori / sandbox tanpa menulis mutasi ke tabel produksi.
4. Setelah simulasi selesai, sistem menghitung `hashAfter = computeProductionStateHash()`. Apabila `$hashBefore !== $hashAfter`, sistem langsung melempar `RuntimeException('Pelanggaran sandbox simulasi: hash tabel produksi berubah.')`.

## 3. Skenario Uji Kejutan (`two_robots_offline_one_zone_blocked`)
Skenario kejutan wajib pada L.4 mensimulasikan kondisi gangguan mendadak di tengah shift:
- **Kondisi Kejutan**: 2 unit robot tambahan mendadak offline dan 1 zona utama diblokir staf secara bersamaan.
- **Metrik yang Diamati**:
  - `online_robots`: jumlah robot online tersisa yang masih memenuhi syarat alokasi (`battery_pct >= 20%`).
  - `simulated_zone_slots`: jumlah slot reservasi aman yang tetap dapat dijadwalkan oleh `ZoneReservationScheduler` (`J1`).
  - `completion_permil` & `fallback_permil`: rasio penyelesaian dan fallback manual dalam permil (integer tanpa `float`).
  - `hash_identical`: wajib bernilai `true` (`hash_identik=true` pada keluaran CLI).

## 4. Struktur Laporan `RoboticsT1SimulationReportService`
`RoboticsT1SimulationReportService::buildReport($warehouseId)` menggabungkan enam subsistem menjadi satu struktur laporan untuk Control Tower (`/wms/robotics/simulations`):
1. `kpi`: metrik dari `RoboticsFleetKpiAggregator` (`total_robots`, `online_robots`, `total_missions`, `completed_missions`, `fallback_missions`, `completion_permil`, `fallback_permil`, `avg_health_score`, `open_safety_incidents`, `peak_zone_utilization_permil`).
2. `status_breakdown`: distribusi tepat 8 status misi.
3. `zones_summary`: ringkasan setiap zona beserta level kemacetan (`LOW`, `MEDIUM`, `HIGH`) dari `ZoneCongestionHeatmapCalculator`.
4. `roi_portfolio`: ringkasan portofolio proposal ROI beserta matriks sensitivitas 3x3 dari `RoboticsRoiSensitivityMatrixBuilder`.
5. `wave_summary`, `charging_summary`, `safety_summary`: ringkasan wave, jadwal charging, dan insiden keselamatan.
6. `audit_status`: hasil langsung dari `RoboticsTwoSourceAuditService::runAudit()` (`passed`, `error_count`, `state_hash`).

## 5. Verifikasi Determinisme
Dengan seed tetap (`--seed=27901`), pemanggilan berulang `robotics:simulate` menghasilkan `fingerprint` run yang identik dan `computeProductionStateHash()` yang sama persis.

