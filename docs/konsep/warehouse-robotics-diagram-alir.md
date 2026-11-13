# Diagram Alir End-to-End Robotika Gudang — Fase 279

- **Fase**: 279 (W8-P6.3)
- **Tujuan**: Visualisasi alur operasional dari penerimaan order hingga penyelesaian misi, fallback manual, pengisian daya, dan audit dua sumber.

## 1. Alur Perencanaan Wave Hingga Eksekusi Misi
```mermaid
flowchart TD
    A["Order Pelanggan & CarrierCutoffProvider"] --> B["WaveBinPacker (J2 FFD)"]
    B --> C{"WaveBinPackerCertificate Valid?"}
    C -- Tidak --> ERR1["InvalidArgumentException"]
    C -- Ya --> D["Simpan RobotWave & Outbox: robotics.wave.released.v1"]
    D --> E["Buat Antrean RobotMission (status: queued)"]
    E --> F["AllocateRobotMissionsAction + RobotFleetDispatchOptimizer (Hungarian)"]
    F --> G{"Robot Online & Battery >= 20%?"}
    G -- Tidak --> H["Masuk unassigned_mission_ids"]
    G -- Ya --> I["Transisi: assigned"]
    I --> J["ReserveZoneForMissionAction (J1 + Interlock Staf)"]
    J --> K{"Zona Bebas Blokir Staf & Okupansi < k?"}
    K -- Tidak --> L["STAFF_ZONE_BLOCKED / ZONE_CAPACITY_FULL"]
    L --> M["RoboticsMissionLifecycleSaga: Lepas Reservasi"]
    K -- Ya --> N["Transisi: moving -> picking -> delivering"]
    N --> O{"Berhasil di Stasiun Tujuan?"}
    O -- Ya --> P["CompleteRobotMissionAction: completed + Confirm WarehouseTask"]
    O -- Tidak --> Q["Transisi: failed"]
    Q --> R{"retry_count < 2?"}
    R -- Ya --> S["RetryOrFallbackRobotMissionAction::retry -> queued"]
    R -- Tidak --> T["fallback_manual + Buat Task Manual + Replan Wave J2"]
```

## 2. Alur Telemetri, Energi, Charging & Maintenance
```mermaid
flowchart TD
    T1["SimulatedDeviceAdapterInterface::pollTelemetry"] --> T2["IngestSimulatedTelemetryBatchAction"]
    T2 --> T3["RobotTelemetryIngestor (idempoten: tel:{id}:{tick})"]
    T3 --> T4["RobotHealthScorer (0..1000)"]
    T4 --> T5{"health_score < 400 atau battery < 15%?"}
    T5 -- Ya --> T6["SafetyIncidentLog::record + TaskInbox"]
    T5 -- Tidak --> T7["Perbarui Robot.health_score & battery_pct"]
    T7 --> T8["ChargingSchedulePlanner + ChargingBayContentionResolver (BAY-01..04)"]
    T7 --> T9["RobotBatteryDegradationTracker -> RobotMaintenanceWindowPlanner (<= 15%)"]
```

## 3. Alur Tata Kelola ROI (`J3`) & Audit Dua Sumber
```mermaid
flowchart TD
    R1["Proposal CAPEX & Arus Kas (minor IDR)"] --> R2["AutomationNpvCalculator (RationalRate)"]
    R2 --> R3["RoboticsRoiSensitivityMatrixBuilder (3x3 Grid)"]
    R3 --> R4["EvaluateRoboticsRoiProposalAction::approveProposal"]
    R4 --> R5{"creator != approver (SoD)?"}
    R5 -- Tidak --> R6["Tolak: InvalidArgumentException"]
    R5 -- Ya --> R7{"npv_minor < 0 & justification kosong?"}
    R7 -- Ya --> R6
    R7 -- Tidak --> R8["ApprovalEngineService::submit + approve -> approved"]
    R8 --> R9["RoboticsTwoSourceAuditService (robotics:audit)"]
```
