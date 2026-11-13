# Konsep — Gudang, Control Tower & Robotika Otomasi (Fase 279)

## 1. Gambaran Umum Domain
Control Tower Robotika Gudang (Fase 279) mengorkestrasi pergerakan armada robot otonom (AMR/AGV/ASRS) di dalam gudang (`wms_warehouses`) tanpa membahayakan pekerja manusia, tanpa kemacetan zona lorong sempit, dan dengan jaminan kesinambungan pemenuhan pesanan (`wms_tasks`) apabila robot mengalami kegagalan teknis.

Berbeda dengan Fase 41 (persediaan & pergerakan stok ber-ledger) dan Fase 53 (control tower SLA & insiden lintas rute), Fase 279 beroperasi murni pada ranah **eksekusi fisik mikro di lantai gudang** serta **evaluasi kelayakan investasi otomasi**, sehingga tidak memposting jurnal akuntansi (`posting ledger = 0`).

## 2. Invarian Sistem & Lokasi Kode Sumber
Setiap invarian di bawah ini dijaga di level kode dan diverifikasi oleh test suite:

1. **Invarian Alokasi Optimal & Prioritas**: Penugasan robot online (`battery_pct >= 20%`, tidak sedang aktif) ke misi berstatus `queued` dilakukan oleh `HungarianSolver` dengan penalti prioritas dan jarak zona (`modules/Wms/Application/Actions/AllocateRobotMissionsAction.php:40-125`, `modules/Wms/Domain/Algorithms/RobotFleetDispatchOptimizer.php:45-120`).
2. **Invarian Interlock Zona Staf**: Tidak ada misi yang boleh memasuki atau mereservasi `TrafficZone` ketika `staff_blocked = true` pada jendela waktu yang bertumpukan (`modules/Wms/Domain/Services/RoboticsZoneInterlockEvaluator.php:40-62`, `modules/Wms/Application/Actions/ReserveZoneForMissionAction.php:45-70`).
3. **Invarian Kapasitas Zona `J1` (Interval Half-Open)**: Pada setiap titik waktu `t`, jumlah reservasi aktif `[start_min, end_min)` di suatu zona tidak pernah melebihi `capacity_k` (`modules/Wms/Domain/Algorithms/ZoneReservationScheduler.php:35-95`, `modules/Wms/Domain/ValueObjects/IntervalWindow.php:14-35`).
4. **Invarian Konservasi Order Wave `J2`**: Total order pada seluruh wave keluaran `WaveBinPacker` sama persis dengan himpunan order masukan tanpa order hilang atau terduplikasi, termasuk saat replanning akibat `fallback_manual` (`modules/Wms/Domain/Algorithms/WaveBinPacker.php:30-105`, `modules/Wms/Application/Actions/PlanRoboticsWaveAction.php:35-80`).
5. **Invarian Batas Retry & Transisi State Machine**: `RobotMissionStateMachine` memiliki tepat 8 status (`queued`, `assigned`, `moving`, `picking`, `delivering`, `completed`, `failed`, `fallback_manual`); retry dari `failed` ke `queued` dibatasi maksimal `MAX_RETRY_ATTEMPTS = 2` (`modules/Wms/Domain/StateMachines/RobotMissionStateMachine.php:18-95`).
6. **Invarian Kompensasi Saga**: Apabila eksekusi misi gagal setelah reservasi zona dibuat, seluruh `ZoneReservation` aktif milik misi tersebut wajib dilepas (`released`) dan tugas manual dibuat di `wms_tasks` + `TaskInboxService` (`modules/Wms/Application/Services/RoboticsMissionLifecycleSaga.php:35-90`, `modules/Wms/Infrastructure/Adapters/WarehouseTaskCompletionBridge.php:43-78`).
7. **Invarian Pemisahan Tugas ROI `J3` (SoD)**: Pembuat proposal ROI tidak boleh menyetujui proposalnya sendiri (`created_by_user_id === approverUserId` ditolak); proposal dengan `npv_minor < 0` wajib menyertakan alasan tertulis (`modules/Wms/Application/Actions/EvaluateRoboticsRoiProposalAction.php:86-115`).
8. **Invarian Append-Only Log**: Rekaman `RobotMissionEvent`, `RobotTelemetry`, dan `RobotSafetyIncident` tidak dapat diubah atau dihapus setelah tersimpan (`modules/Wms/Models/RobotMissionEvent.php:25-40`, `modules/Wms/Models/RobotTelemetry.php:25-40`, `modules/Wms/Models/RobotSafetyIncident.php:25-40`).
9. **Invarian Isolasi Sandbox Simulasi**: Eksekusi `robotics:simulate` atau `RoboticsSimulationScenarioRunner` wajib menghasilkan `computeProductionStateHash()` yang identik sebelum dan sesudah simulasi (`modules/Wms/Console/Commands/RoboticsSimulateCommand.php:33-42`, `modules/Wms/Application/Services/RoboticsSimulationScenarioRunner.php:30-65`).
10. **Invarian Anti-IDOR**: Operator gudang (`hub_operator`) hanya dapat mengeksekusi fallback pada misi yang ditugaskan kepadanya atau belum bertuan (`modules/Wms/Http/Policies/RobotMissionPolicy.php:29-37`).

## 3. Sumber Referensi & Kepatuhan (T-8)
- **Standar Keselamatan Robotika Industri & AMR**: ISO 3691-4 (Driverless Industrial Trucks — Safety Requirements and Verification), diadaptasi menjadi interlock kecepatan koeksistensi (`MAX_COEXISTENCE_SPEED_MM_S = 800 mm/s` vs standar `1800 mm/s`) serta pemblokiran zona staf penuh.
- **Algoritma Penugasan**: Kuhn-Munkres / Hungarian Method (`O(N^3)`) dengan sertifikat dual `(u, v)` (`modules/Shared/Domain/Algorithms/Assignment/HungarianSolver.php`).
- **Algoritma Evaluasi Investasi**: Discounted Cash Flow (`NPV = -CAPEX + sum(CF_t / (1+r)^t)`) berbasis aritmetika integer & `RationalRate` tanpa galat IEEE-754.
- **Sumber Internal Proyek**: `docs/konsep/control-tower-dan-sop.md` (Fase 53), `modules/Logistics/Contracts/CarrierCutoffProvider.php`, `modules/Core/Contracts/SimTickHandlerInterface.php`.

## 4. Katalog Kasus Uji & Operasional (16 Kasus)
| Kode | Kategori | Skenario & Kondisi | Ekspektasi Sistem |
|---|---|---|---|
| `K279-01` | Normal | 20 robot online, 120 misi terdistribusi, semua zona terbuka | Alokasi Hungarian berjalan deterministik; `robotics:audit` exit 0 |
| `K279-02` | Normal | Reservasi `J1` pada `[0,5)` dan `[7,10)` dengan durasi 2 menit (`k=1`) | Masuk tepat di celah `[5,7)` tanpa menggeser reservasi lain |
| `K279-03` | Normal | Wave FFD `J2` 3 order `(40, 35, 30)` kapasitas `70` carrier `JNE` | Terbentuk tepat 2 wave (`[40,30]` dan `[35]`), nol order hilang |
| `K279-04` | Normal | Evaluasi ROI `J3` dengan arus kas positif melebihi CAPEX | `npv_minor > 0`, dapat disetujui oleh `admin` berbeda tanpa alasan tambahan |
| `K279-05` | Boundary | Robot baterai tepat `20%` (`MIN_BATTERY_PCT`) | Diizinkan menerima misi terakhir, lalu dijadwalkan charging oleh `ChargingSchedulePlanner` |
| `K279-06` | Boundary | Robot baterai `19%` atau `14%` (`LOW_BATTERY_ALERT_PCT`) | Ditolak dari alokasi misi; `IngestSimulatedTelemetryBatchAction` membuka insiden keselamatan |
| `K279-07` | Boundary | `RobotMission` gagal sebanyak 2 kali berturut-turut | Retry ke-1 dan ke-2 diizinkan (`failed -> queued`); percobaan retry ke-3 ditolak (`RobotMissionTransitionException`) |
| `K279-08` | Error/Interlock | Misi diarahkan ke `TrafficZone` dengan `staff_blocked = true` | `ReserveZoneForMissionAction` menolak transisi (`STAFF_ZONE_BLOCKED`) dan melepaskan lock |
| `K279-09` | Error/Interlock | Pembuat proposal ROI mencoba menyetujui proposalnya sendiri | `EvaluateRoboticsRoiProposalAction` melempar `InvalidArgumentException` (SoD violation) |
| `K279-10` | Error/Interlock | Admin menyetujui proposal ROI ber-NPV negatif tanpa alasan tertulis | Ditolak dengan `InvalidArgumentException`; berhasil bila alasan diisi |
| `K279-11` | Security/IDOR | `hub_operator` B memicu fallback pada misi milik `hub_operator` A | `RobotMissionPolicy::fallback()` mengembalikan `false` (HTTP 403) |
| `K279-12` | Security/RBAC | User `guest` atau `auditor` mengakses `/wms/robotics/zones` | Middleware `role` menolak dengan HTTP 403 Forbidden |
| `K279-13` | Concurrency/Deadlock | Siklus tunggu 3 misi `M-01 -> M-02 -> M-03 -> M-01` dengan prioritas `(90, 20, 50)` | `TrafficDeadlockDetector` mendeteksi siklus & memilih `M-02` (prioritas terendah) sebagai victim |
| `K279-14` | Rollback/Saga | Kegagalan terjadi setelah reservasi zona dibuat dalam `RoboticsMissionLifecycleSaga` | Reservasi aktif dilepas, misi pindah ke `fallback_manual`, tugas manual dibuat di `wms_tasks` |
| `K279-15` | Sandbox | Simulasi skenario kejutan `two_robots_offline_one_zone_blocked` | Simulasi selesai & `computeProductionStateHash()` sebelum == sesudah (`hash_identical = true`) |
| `K279-16` | Audit/Tamper | `RoboticsAuditCorruptionFixture` menyisipkan misi yatim / sertifikat energi dipalsukan | `robotics:audit` exit non-0 menyebut `MIS-CORRUPT-01`; `verify()` melempar `InvalidArgumentException` |

## 5. Siklus Hidup Telemetri, Energi & Pelaporan T1
1. **Akuisisi Telemetri Deterministik**: `IngestSimulatedTelemetryBatchAction` melakukan polling melalui kontrak `SimulatedDeviceAdapterInterface` (`SimulatedAmrDeviceAdapter` di lingkungan simulasi), lalu memanggil `RobotTelemetryIngestor` secara idempoten per `(robot_id, tickKey)`.
2. **Skoring Kesehatan & Insiden Otomatis**: `RobotHealthScorer` menghitung skor `[0..1000]` dari `battery_pct`, `error_count`, dan `downtime_minutes`. Jika `health_score < 400` atau `battery_pct < 15%`, sistem otomatis membuat tugas di `TaskInboxService` dan mencatat `RobotSafetyIncident`.
3. **Pelaporan Control Tower & T1**: `RoboticsT1SimulationReportService` menggabungkan `RoboticsFleetKpiAggregator`, `ZoneCongestionHeatmapCalculator`, `RoboticsRoiSensitivityMatrixBuilder`, dan `RoboticsTwoSourceAuditService` menjadi satu payload konsisten untuk rute `/wms/robotics/simulations` serta keluaran CLI `robotics:simulate`.
4. **ViewModels**: `RoboticsMissionBoardViewModel` dan `RoboticsRoiPortfolioViewModel` memformat status badge dan nilai minor IDR secara murni tanpa operasi `float` sebelum dirender oleh Blade `x-ds.*`.

