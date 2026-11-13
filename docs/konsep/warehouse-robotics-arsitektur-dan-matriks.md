# Arsitektur Kontrak Lintas Modul & Ketelusuran MATRIKS — Fase 279

- **Fase**: 279 (W8-P6.3)
- **Modul**: `Wms` (`Modules\Wms\*`)

## 1. Isolasi Batas Modul & Kontrak (`A1 = 0`)
Sesuai aturan arsitektur `A1`, `modules/Wms/` dilarang keras mengimpor kelas dari `Modules\{Lain}\Domain\*`. Seluruh kebutuhan lintas modul pada Fase 279 dipenuhi melalui kontrak antarmuka atau layanan aplikasi terdaftar:

| Kebutuhan Lintas Modul | Kontrak / Layanan yang Digunakan | Implementasi Target |
|---|---|---|
| Jadwal cutoff kurir untuk `WaveBinPacker` (`J2`) | `Modules\Logistics\Contracts\CarrierCutoffProvider` | Disediakan modul `Logistics` melalui service container |
| Waktu deterministik untuk TTL, audit, & telemetri | `Modules\Core\Contracts\ClockInterface` | `SimClockService` / `SystemClockService` |
| Penerbitan event outbox idempoten (`robotics.*.v1`) | `Modules\Core\Contracts\OutboxBusInterface` | `OutboxBusService::record()` setelah `DB::afterCommit` |
| Mesin persetujuan 4-eyes / SoD untuk ROI `J3` | `Modules\Core\Contracts\ApprovalEngineInterface` | `ApprovalEngineService::submit()` & `approve()` |
| Tick handler simulasi sandbox P3 | `Modules\Core\Contracts\SimTickHandlerInterface` | `RoboticsSimulationTickHandler` |
| Eskalasi tugas manual ke kotak masuk operator | `Modules\Core\Application\Actions\TaskInbox\TaskInboxService` | Dipanggil oleh `RobotMissionTransitionService`, `RobotTelemetryIngestor`, & `EscalateOverdueMissionsAction` |
| Adapter telemetri perangkat AMR/AGV | `Modules\Wms\Contracts\SimulatedDeviceAdapterInterface` | `SimulatedAmrDeviceAdapter` (terikat di `WmsServiceProvider`) |

## 2. Ketelusuran MATRIKS (`M-2..M-7`)
1. **`M-2` (Rasio Kelas Domain/Application Teruji & Terpakai >= 60%)**:
   Seluruh 14 kelas algoritma/domain service, 6 kelas sertifikat, 10 action, dan 11 service di `modules/Wms/` dipakai langsung oleh controller (`WmsRoboticsOperationsController`), command (`robotics:*`), atau diuji pada `RoboticsAlgorithmsVectorTest` & `WarehouseRoboticsFullAcceptanceTest`.
2. **`M-3` (Duplikasi & Boilerplate <= 15%)**:
   Logika matematika murni (`ZoneReservationScheduler`, `WaveBinPacker`, `AutomationNpvCalculator`, `TrafficDeadlockDetector`, `RobotEnergyConsumptionEstimator`, `RoboticsZoneInterlockEvaluator`) dipusatkan pada Domain layer sehingga Action hanya mengatur kunci transaksi DB dan event outbox.
3. **`M-4` (Nol `float` pada Uang/Rate = 0)**:
   Seluruh CAPEX/NPV memakai `int` minor IDR, seluruh rate memakai `RationalRate`, dan seluruh rasio memakai permil/bps integer (`arch:scan` A3 = 0).
4. **`M-5` (Nol Pelanggaran Arsitektur Baru = 0)**:
   `arch:scan` mencatat `Baru: 0` di seluruh aturan `A1`–`A13`.
5. **`M-7` (Kepatuhan UI `x-ds.*` >= 90%)**:
   Seluruh 8 tampilan Blade di `modules/Wms/resources/views/wms/robotics/` menggunakan komponen Design System (`x-ds.*`) dengan `ui:lint` = `0` pelanggaran.

## 3. Matriks Ketelusuran Spesifikasi (`D279-1..9` & `X279-1..4`)
| Token Spek | Implementasi Utama | Bukti Uji |
|---|---|---|
| `D279-1` | `Robot`, `RobotHealthScorer`, `RobotTelemetryIngestor`, `IngestSimulatedTelemetryBatchAction` | `WarehouseRoboticsFullAcceptanceTest` #5 |
| `D279-2` | `AllocateRobotMissionsAction`, `RobotFleetDispatchOptimizer`, `HungarianSolver` | `WarehouseRoboticsFullAcceptanceTest` #2 |
| `D279-3` | `ZoneReservationScheduler` (`J1`), `ReserveZoneForMissionAction`, `TrafficDeadlockDetector` | `RoboticsAlgorithmsVectorTest`, `WarehouseRoboticsFullAcceptanceTest` #2 |
| `D279-4` | `WaveBinPacker` (`J2`), `PlanRoboticsWaveAction`, `RoboticsWaveReconciliationService` | `RoboticsAlgorithmsVectorTest`, `WarehouseRoboticsFullAcceptanceTest` #3 |
| `D279-5` | `AutomationNpvCalculator` (`J3`), `EvaluateRoboticsRoiProposalAction`, `RoboticsRoiSensitivityMatrixBuilder` | `RoboticsAlgorithmsVectorTest`, `WarehouseRoboticsFullAcceptanceTest` #3 |
| `D279-6` | `RoboticsSimulateCommand`, `RoboticsSimulationScenarioRunner`, `RoboticsT1SimulationReportService` | `WarehouseRoboticsFullAcceptanceTest` #4, #5 |
| `D279-7` | `ChargingSchedulePlanner`, `ChargingBayContentionResolver`, `SafetyIncidentLog`, `RobotMissionEvent` | `RoboticsAlgorithmsVectorTest`, `WarehouseRoboticsFullAcceptanceTest` #4 |
| `D279-8` | `RobotMissionPolicy`, `route-roles.php`, `RoboticsCsvExportService` | `WarehouseRoboticsFullAcceptanceTest` #1, #4 |
| `D279-9` | `RoboticsTwoSourceAuditService`, `RoboticsTwoSourceAuditCommand`, `RoboticsAuditCorruptionFixture` | `WarehouseRoboticsFullAcceptanceTest` #5, `AuditCommandContractTest` |
| `X279-1..4` | `declare(strict_types=1)`, integer arithmetic, deterministic idempotency keys, `x-ds.*` UI | `arch:scan` (`Baru: 0`), `ui:lint` (`0`) |
