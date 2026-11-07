# Bukti Kondisi Lama (L.0) — Fase 93 Mine Planning, Fleet Dispatch & Fuel Management

Tanggal: 2026-11-07
Branch: feature/g7-p4-pertambangan
Basis: master (abf89eba)

## 1. Inventaris Komponen Eksisting

| Komponen | Status | Keterangan |
|---|---|---|
| `min_sites` | Ada / Perlu Penyesuaian | Sudah ada di migrasi 2026_10_07_930100 & 2026_10_30_140100, perlu dipastikan kolom `timezone` dan `status`. |
| `min_pits` | Ada / Perlu Penyesuaian | Ada di migrasi 930100, perlu integrasi dengan perencanaan tambang (`min_mine_plans`). |
| `min_equipment` | Ada / Perlu Penyesuaian | Ada di migrasi 930100 & 140100; belum memiliki 8-state machine enum dan lookup Asset via AssetRegistryLookupInterface. |
| `min_dispatch_runs` | Ada / Parsial | Ada skema MVP dengan float/decimal di migrasi lama; perlu digantikan/dilengkapi dengan `min_haul_cycles` yang berstatus 8-state machine. |
| `min_dispatch_plans` & `min_dispatch_assignments` | Ada / Perlu Penyesuaian | Ada di 140100; belum menggunakan algoritma M2 `NeedTimeTruckDispatcher`. |
| `min_mine_plans` & `min_mine_plan_shifts` | Tidak Ada | Perlu dibuat untuk bulanan pit cut & fill, time-phasing harian/shift (M1). |
| `min_haul_cycles` | Tidak Ada | State machine 8 status siklus angkut (`queued` .. `aborted`). |
| `min_fuel_anomaly_cases` | Tidak Ada | State machine 8 status penanganan anomali BBM kontraktor. |
| `min_equipment_telemetries` | Tidak Ada | Ingest idempoten titik telemetri alat berat (fuel, payload, vibration, engine hours). |
| `min_external_factors` | Tidak Ada | Tabel parameter berversi faktor eksternal (curah hujan, HBA, kurs, DMO). |
| `min_contractor_payment_holds` | Tidak Ada | Pencatatan hold bayaran kontraktor terkait kasus anomali BBM. |
| `min_maintenance_sagas` | Tidak Ada | Tabel saga untuk koordinasi WO Asset & MRP Requisition. |
| Action `GenerateDispatchPlanAction` | Ada / Parsial | Menggunakan Hungarian solver sederhana, belum mendukung M2 `NeedTimeTruckDispatcher` & match factor (MF). |
| Action `PlanMineBlockExtractionAndStrippingRatioAction` | Ada / Parsial | Belum menerapkan M1 `WeightedTimePhasing` dengan jam efektif hujan & perawatan. |
| Action `DispatchHaulTruckAndDetectFuelAnomalyAction` | Ada / Parsial | Belum menerapkan M3 `LinearBaselineRegression` & hold bayaran kontraktor dengan ledger. |
| Service `MiningFleetDispatchService` | Ada / Parsial | Monolitik MVP, perlu delegasi ke Action dan State Machine modern. |
| Route `/mining/plans` | Tidak Ada | Route untuk `mine_planner`. |
| Route `/mining/dispatch` | Ada (Blade statis) | Belum memenuhi papan Gantt shift & dispatch override dengan alasan. |
| Route `/mining/equipment` | Tidak Ada | Route untuk `mine_manager` (OEE ranking). |
| Route `/mining/fuel` | Tidak Ada | Route untuk `finance_officer` & `hse_officer`. |
| Route `/contractor/mining` | Tidak Ada | Portal kontraktor terisolasi (anti-IDOR). |
| Command `mining:audit` | Ada / Salah Parsial | Belum mengaudit saldo `min:contractor_payment_hold`, dispatch <= unit tersedia, payload variance, dan WO idempoten. |
| Command `mining:phase-plan` | Tidak Ada | Perlu dibuat untuk time-phasing bulanan rencana tambang. |
| Command `mining:ingest-telemetry` | Tidak Ada | Perlu dibuat untuk simulasi telemetri alat berat. |
| Command `mining:shift-close` | Tidak Ada | Perlu dibuat untuk penutupan shift dan posting tagihan kontraktor. |
| Command `mining:fuel-anomaly-scan` | Tidak Ada | Perlu dibuat untuk pemindaian anomali konsumsi BBM berbasis M3. |
| Test `tests/Acceptance/MiningOps/` | Tidak Ada | Belum ada suite acceptance test untuk operasi penambangan. |

## 2. Keputusan Tindakan

1. **Pakai Ulang & Perluas**:
   - `MiningSite`, `MiningPit`, `MiningEquipment` dengan penambahan relasi, cast integer, dan state machine.
   - `AuditMiningCommand` diperluas dengan verifikasi saldo hold, payload variance, dan batasan dispatch.
2. **Tambah Baru (Migrasi & Model)**:
   - `min_mine_plans`, `min_mine_plan_shifts`
   - `min_haul_cycles`, `min_haul_cycle_histories`
   - `min_fuel_anomaly_cases`, `min_fuel_anomaly_histories`
   - `min_equipment_telemetries`
   - `min_external_factors`
   - `min_contractor_payment_holds`
   - `min_mining_sagas`
3. **Algoritma Baru**:
   - M1: `Shared\Scheduling\WeightedTimePhasing`
   - M2: `Mining\NeedTimeTruckDispatcher`
   - M3: `Shared\Stats\LinearBaselineRegression`
4. **State Machine Baru**:
   - `MinePlanStateMachine` (8 status)
   - `HaulCycleStateMachine` (8 status)
   - `EquipmentStateMachine` (8 status)
   - `FuelAnomalyCaseStateMachine` (8 status)
5. **Ledger Accounts**:
   - Menambahkan dan merapikan akun-akun Mining di `MiningAccounts` tanpa pemanggilan `firstOrCreate` runtime.

