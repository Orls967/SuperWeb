# ADR-279: Arsitektur Vertical Slice Robotika Gudang (`Wms\Robotics`) & Aritmetika Deterministik

- **Status**: Diterima (2026-11-13)
- **Lingkup**: Modul `Wms` (`modules/Wms/`), `EventCatalog` (`modules/Core/`), `AuditCommandRegistry` (`app/Quality/`).

## 1. Konteks
Implementasi awal Fase 279 di `modules/Integration/Application/Services/WarehouseRoboticsAutomationService.php` hanya menyediakan stub layanan mentah tanpa tabel armada, tanpa pemecah alokasi optimal, tanpa interlock keselamatan zona staf, tanpa kontrol transaksi state machine, serta tanpa antarmuka Control Tower atau audit dua sumber.

## 2. Penggerak Keputusan
1. **Isolasi Batas Modul (Aturan A1)**: `Wms` tidak boleh mengimpor `Domain\*` dari modul lain (`Logistics`, `Core`, `Shared`). Integrasi cutoff kurir dan jam simulasi harus lewat kontrak (`CarrierCutoffProvider`, `ClockInterface`, `OutboxBusInterface`, `ApprovalEngineInterface`, `SimTickHandlerInterface`).
2. **Determinisme & Anti-Float (Aturan A3 & Sertifikat)**: Penjadwalan interval zona (`J1`), bin-packing wave (`J2`), evaluasi NPV/IRR (`J3`), dan estimasi energi baterai (`RobotEnergyConsumptionEstimator`) harus menghasilkan keluaran identik pada mesin manapun tanpa galat pembulatan floating-point.
3. **Keselamatan Operasional (Safety Interlock & Saga)**: Misi robot tidak boleh memasuki zona yang diblokir staf; kegagalan di tengah misi harus melepas reservasi zona aktif dan mengalihkan tugas ke operator manusia (`WarehouseTaskCompletionBridge` + `TaskInboxService`).

## 3. Keputusan Arsitektur
1. **Vertical Slice Lengkap di `modules/Wms/`**:
   - Menambahkan 9 tabel baru + perluasan `wms_robot_missions` melalui migrasi `2026_11_13_000004_create_wms_robotics_full_tables.php`.
   - Menandai `Integration\WarehouseRoboticsAutomationService` sebagai `@deprecated` tanpa memutus kompatibilitas lama.
2. **Aritmetika Integer & Bilangan Rasional (`RationalRate`)**:
   - Seluruh nilai CAPEX/arus kas/NPV disimpan dalam minor integer IDR (sen), sedangkan tingkat diskonto direpresentasikan oleh `RationalRate(numerator, denominator)`.
   - Setiap algoritma inti mengeluarkan objek `*Certificate` dengan hash SHA-256 kanonik dan metode `verifyOrFail()` / `verify()`.
3. **Append-Only Event & Telemetry Ledger**:
   - Model `RobotMissionEvent`, `RobotTelemetry`, dan `RobotSafetyIncident` menolak operasi `updating` dan `deleting` pada level Eloquent event hook.
4. **Mode Sandbox Simulasi P3**:
   - Command `robotics:simulate` dan `RoboticsSimulationScenarioRunner` memverifikasi `computeProductionStateHash()` sebelum dan sesudah simulasi untuk menjamin nol mutasi data produksi.

## 4. Konsekuensi
- **Positif**: Seluruh aturan `arch:scan` (`A1`–`A13`) tercatat `Baru = 0`; seluruh logika matematika teruji deterministik lewat vektor unit + sertifikat rusak; UI Control Tower mematuhi `x-ds.*` (`ui:lint = 0`).
- **Negatif / Trade-off**: Penyusunan matriks biaya Hungarian dan sertifikat energi menambah overhead kecil per batch alokasi, namun tetap sub-milidetik untuk ukuran armada puluhan robot per gudang (`O(N^3)` untuk `N <= 100`).
