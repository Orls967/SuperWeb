# Definition of Ready (DoR) — Fase 279: Warehouse Automation & Robotics Simulation

- **Fase:** 279 (`modules/Wms/`, sub-domain `Robotics` dengan prefiks tabel `wms_robotics_*` / `wms_`)
- **Gelombang / Paket:** W8-P6.3
- **Tanggal:** 2026-11-13
- **Status Target:** `🔵 DIIMPLEMENTASIKAN` (non-PU)

## 1. Justifikasi RAB Posting Ledger = 0 (Aturan D98)

**RAB Posting Ledger = 0.**
**Alasan tertulis (D98):**
Fase 279 berfokus pada perencanaan automasi DC (pemilihan metode picking: manual vs AMR vs conveyor, kapasitas, ROI simulasi), manajemen armada robot simulasi (alokasi misi, reservasi zona anti-tabrakan, deteksi deadlock, jadwal pengisian baterai, safety interlock manusia+robot), dan re-plan gelombang picking saat terjadi kegagalan robot (*fallback manual*). Seluruh aktivitas Fase 279 adalah **simulasi operasional, orkestrasi perangkat keras, dan perencanaan kapasitas** yang tidak menghasilkan kejadian uang (*money event*) langsung ke buku besar. Posting persediaan riil tetap terjadi pada Fase 41 (`WmsLedgerPoster`), sedangkan pembelian aset capex tetap terjadi di modul `Asset` / `Purchasing`. Oleh karena itu, Fase 279 tidak menambah akun ledger maupun tipe transaksi baru.

## 2. Daftar Keputusan Terkunci

1. **Pemilik Modul & Deprekasi Legacy (X2/X21)**:
   - Pemilik resmi adalah modul `Wms` (`Modules\Wms\...`).
   - `Modules\Integration\Application\Services\WarehouseRoboticsAutomationService` diberi anotasi `@deprecated` yang mengarahkan pemanggil ke `Modules\Wms\Application\Services\RoboticsFleetOrchestratorService` (tanpa menyalin kode mentah `DB::table()` dari `Integration`).
2. **Reuse `HungarianSolver` + Algoritma J1–J3 & Deadlock**:
   - **J1 (`AutomationRoiPlanner` + `AutomationPlanCertificate`)**: Evaluasi metode pick (`manual`, `amr`, `conveyor`), kapasitas throughput/jam, biaya capex/opex integer IDR, payback & ROI bps.
   - **J2 (`RobotTaskAllocator` + `MissionAssignmentCertificate`)**: Mengonversi matriks jarak/baterai robot × misi menjadi cost matrix integer dan memanggil `Modules\Shared\Domain\Algorithms\Assignment\HungarianSolver` (dengan sertifikat dual `AssignmentCertificateVerifier`).
   - **J3 (`WaveAutomationDispatcher` + `WaveFallbackPlanCertificate`)**: Perencanaan wave berbasis cutoff & prioritas dengan integrasi `PickPathPlanner` (Fase 41.3) serta re-plan otomatis + *fallback manual* ketika robot gagal di tengah wave.
   - **`TrafficDeadlockDetector`**: Deteksi siklus tunggu (*wait-for graph*) antar robot di koridor/zona dan resolusi prioritas deterministik.
3. **State Machine `RobotMissionStateMachine` (8 Status)**:
   - `queued` -> `assigned` -> `en_route` -> `picking` -> `delivering` -> `completed`, dengan jalur `interlocked` (human-in-zone safety halt) dan `failed_fallback` (manual takeover).
4. **Safety Interlock & Audit (`wms:audit`)**:
   - Kehadiran manusia di zona otomatis membatasi kecepatan (`max_speed_mm_s <= 300`) atau memicu `emergency_stop` bila jarak aman dilanggar.
   - `wms:audit` diperluas memeriksa konflik reservasi zona ganda (dua robot aktif di zona eksklusif yang sama) & misi robot yatim, disertai `RoboticsAuditCorruptionFixture`.
5. **Integritas & Larangan**:
   - Zero `float` pada biaya/ROI/kapasitas; zero `now()`/`Carbon::now()` (`ClockInterface`); `declare(strict_types=1);`; seluruh FK `*_id` cocok MySQL 8.4 (`string('warehouse_id', 64)` ke `wms_warehouses.id`, `unsignedBigInteger` ke `users.id` / `wms_robot_units.id`); UI `x-ds.*` dengan `ui:lint` = 0.

