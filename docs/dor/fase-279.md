# DoR — Fase 279: Gudang, Control Tower & Robotika Otomasi

- **Fase**: 279 (W8-P6.3)
- **Modul**: `Wms` (`Modules\Wms\*`)
- **Status Target**: 🔵 Siap Uji & Audit
- **Prompt Type**: Non-PU (Fokus Operasional Armada, Penjadwalan Zona, Wave, Simulasi Sandbox, dan Kelayakan ROI)

## 1. Alasan RAB Posting Ledger = 0 (Aturan Keuangan)
Fase 279 **tidak memiliki kejadian uang/jurnal akuntansi langsung** (`posting ledger = 0`). Seluruh nilai uang pada fase ini berupa **proyeksi investasi CAPEX & arus kas tahunan** (`wms_robotics_roi_proposals.capex_minor`, `npv_minor`) dalam satuan minor integer IDR (sen) untuk evaluasi kelayakan otomasi (`J3`), bukan transaksi kas, faktur, atau mutasi persediaan finansial. Mutasi nilai persediaan fisik gudang ke buku besar sudah ditangani penuh oleh Fase 41 (`wms_stock_moves` & `InventoryLedger`).

## 2. Tabel Rencana Anggaran & Bobot (RAB — T-2)
| Komponen | Bobot Target | Realisasi & Bukti | Posting Ledger |
|---|---|---|---|
| Skema & Model (`wms_robots`, `wms_traffic_zones`, `wms_zone_reservations`, `wms_robot_waves`, `wms_robotics_roi_proposals`, dll.) | 15% | Migrasi `2026_11_13_000004` + 9 model + append-only guard | 0 (Non-monetary) |
| Algoritma & Sertifikat (`J1` ZoneReservationScheduler, `J2` WaveBinPacker, `J3` AutomationNpvCalculator, `HungarianSolver`, `TrafficDeadlockDetector`, `RobotEnergyConsumptionEstimator`) | 25% | 6 kelas algoritma + 5 kelas sertifikat `verifyOrFail()` | 0 (Pure domain math) |
| State Machine 8 Status & Saga (`RobotMissionStateMachine`, `RoboticsMissionLifecycleSaga`, `EnforceZoneSafetyInterlockAction`) | 20% | 8 status, max retry 2, interlock zona staf, kompensasi reservasi | 0 (Operational flow) |
| Integrasi Lintas Modul & Adapter (`CarrierCutoffProvider`, `SimTickHandlerInterface`, `SimulatedAmrDeviceAdapter`, `WarehouseTaskCompletionBridge`) | 15% | Tidak ada import Domain lintas modul (A1 = 0) | 0 (Contract-based) |
| Command CLI (`8 robotics:*`), Audit 2 Sumber (`robotics:audit`), & Seeder T1 (`RoboticsT1Seeder`) | 15% | 8 command + `RoboticsAuditCorruptionFixture` + 20 robot / 120 misi | 0 (CLI & Audit) |
| Control Tower UI (`x-ds.*`), ViewModels, RBAC & Dokumentasi T-7 | 10% | 8 route + 5 Blade baru + `RobotMissionPolicy` + 12 dokumen T-7 | 0 (UI & Docs) |
| **Total** | **100%** | **B1 >= 8.000, B3 >= 1.800, arch:scan Baru = 0** | **0** |

## 3. Daftar Kebutuhan Fungsional & Teknis (`D279-1..9`, `X279-1..4`)
- **D279-1**: Manajemen armada robot (`wms_robots`) dengan status online/offline, skor kesehatan (`RobotHealthScorer`), koordinat grid, dan batas cadangan baterai.
- **D279-2**: Alokasi misi optimal (`AllocateRobotMissionsAction` & `RobotFleetDispatchOptimizer`) menggunakan `HungarianSolver` dengan penalti prioritas, jarak zona, baterai, serta penolakan zona diblokir staf.
- **D279-3**: Penjadwalan reservasi zona lalu lintas `J1` (`ZoneReservationScheduler`) pada interval setengah-terbuka `[start, end)` dengan kapasitas `k` dan deteksi deadlock (`TrafficDeadlockDetector`).
- **D279-4**: Penyusunan wave pemenuhan order `J2` (`WaveBinPacker` FFD) menghormati cutoff carrier (`CarrierCutoffProvider`) tanpa order hilang atau ganda saat fallback manual.
- **D279-5**: Evaluasi kelayakan investasi otomasi `J3` (`AutomationNpvCalculator` + `RoboticsRoiSensitivityMatrixBuilder`) berbasis integer minor IDR & `RationalRate`; pembuat proposal dilarang menyetujui proposalnya sendiri (`ApprovalEngineService`), dan NPV negatif wajib menyertakan alasan tertulis.
- **D279-6**: Simulasi kepadatan & skenario kejutan (`robotics:simulate`, `RoboticsSimulationScenarioRunner`, `RoboticsSimulationTickHandler`) berjalan dalam mode sandbox P3 tanpa mengubah hash tabel produksi.
- **D279-7**: Penjadwalan pengisian daya (`ChargingSchedulePlanner`, `robotics:charge-plan`) dan pencatatan insiden keselamatan (`SafetyIncidentLog`) beserta audit log append-only (`RobotMissionEvent`, `RobotTelemetry`, `RobotSafetyIncident`).
- **D279-8**: Keterbukaan & keamanan akses via `RobotMissionPolicy` (anti-IDOR operator gudang), `route-roles.php`, serta ekspor CSV (`RoboticsCsvExportService`).
- **D279-9**: Audit dua sumber deterministik (`RoboticsTwoSourceAuditService`, `robotics:audit`) dengan `RoboticsAuditCorruptionFixture` yang gagal deterministik (exit non-0) saat data dirusak.
- **X279-1..4**: Tidak ada `float` pada perhitungan uang/rate (A3 = 0), seluruh key idempotensi berbasis entitas/tick deterministik (A4 = 0), `declare(strict_types=1)` di semua file PHP modul (A11 = 0), dan tidak ada pelanggaran UI lint (`x-ds.*`).

## 4. Kriteria Penerimaan (Acceptance Criteria)
1. Seluruh vektor uji `J1`, `J2`, `J3`, `HungarianSolver`, `TrafficDeadlockDetector`, dan `RobotEnergyConsumptionEstimator` lulus tepat beserta penolakan sertifikat rusak (`InvalidArgumentException`).
2. `WarehouseRoboticsFullAcceptanceTest` memverifikasi 5 skenario end-to-end: RBAC seluruh grup rute robotika, alokasi Hungarian + interlock zona staf, wave FFD + fallback manual + ROI approval SoD, state machine retry maks 2 + saga kompensasi + sandbox hash identik + IDOR policy, serta `robotics:audit` bersih vs fixture korupsi.
3. `arch:scan` mencatat `Baru = 0`, `ui:lint` = `0`, dan `docs:check` lulus penuh.
