# Glosarium Istilah & Formula Metrik Control Tower — Fase 279

## 1. Glosarium Domain Robotika Gudang
- **AMR / AGV / ASRS**: Autonomous Mobile Robot, Automated Guided Vehicle, dan Automated Storage & Retrieval System yang terdaftar pada tabel `wms_robots`.
- **Interval Setengah-Terbuka `[start, end)`**: Representasi jendela waktu diskret dalam menit bulat (`IntervalWindow`) di mana `start` termasuk dan `end` tidak termasuk, sehingga `[0, 5)` dan `[5, 7)` tidak saling bertumpuk.
- **Interlock Zona Staf (`staff_blocked`)**: Mekanisme keselamatan keras yang melarang robot memasuki atau mereservasi `TrafficZone` ketika pekerja manusia aktif di lorong tersebut.
- **Koeksistensi (`800 mm/s`)**: Batas kecepatan aman maksimum ketika zona berkapasitas `k >= 2` sedang dipakai secara bersamaan (`MAX_COEXISTENCE_SPEED_MM_S`).
- **Wait-For Graph & Victim**: Graf ketergantungan antar-misi yang saling menunggu zona; ketika siklus terdeteksi (`TrafficDeadlockDetector`), misi berprioritas terendah dipilih sebagai victim untuk diputus sementara.
- **Fallback Manual (`fallback_manual`)**: Status terminal misi ketika robot gagal melebihi `MAX_RETRY_ATTEMPTS = 2` dan tugas dialihkan ke picker manusia (`wms_tasks` + `TaskInbox`).
- **Minor Integer IDR**: Penyimpanan nilai uang dalam sen (`1 Rp = 100 minor`) bertipe integer 64-bit tanpa `float`.
- **Basis Points (`bps`) & Permil (`‰`)**: Satuan rasio integer di mana `100 bps = 1%` dan `1000‰ = 100%`.

## 2. Formula KPI Control Tower (`RoboticsFleetKpiAggregator` & `RoboticsT1SimulationReportService`)
| Metrik | Formula Integer | Sumber Data |
|---|---|---|
| `online_ratio_permil` | `intdiv(online_robots * 1000, total_robots)` | `wms_robots` (`is_online = true`) |
| `completion_permil` | `intdiv(completed_missions * 1000, total_missions)` | `wms_robot_missions` (`status = completed`) |
| `fallback_permil` | `intdiv(fallback_missions * 1000, total_missions)` | `wms_robot_missions` (`status = fallback_manual`) |
| `avg_health_score` | `intdiv(sum(health_score), total_robots)` | `wms_robots.health_score` `[0..1000]` |
| `peak_zone_utilization_permil` | `intdiv(max_concurrent_in_bucket * 1000, capacity_k)` | `ZoneCongestionHeatmapCalculator` |
| `max_safe_range_m` | `intdiv((usable_pct * 4800) - 1500, 120 + 4 * payload_kg)` | `RobotEnergyConsumptionEstimator` |
| `battery_health_permil` | `max(0, 1000 - intdiv(cycles, 10) - deep_discharges * 3)` | `RobotBatteryDegradationTracker` |

