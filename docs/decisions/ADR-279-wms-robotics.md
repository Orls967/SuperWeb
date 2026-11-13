# ADR-279: Migrasi Robotika Gudang ke Vertical Slice `Wms\Robotics`

- **Status**: Diterima (2026-11-13)
- **Konteks**: Implementasi awal Fase 279 di `modules/Integration/Application/Services/WarehouseRoboticsAutomationService.php` hanya berupa service mentah tanpa rute, UI, command, atau algoritma alokasi optimal.
- **Keputusan**:
  1. Membangun vertical slice lengkap di `modules/Wms/` (`wms_robotics_*` tables, J1–J3, `HungarianSolver`, `RobotMissionStateMachine` 8 status, UI `x-ds.*`, dan CLI commands).
  2. Menandai `Integration\WarehouseRoboticsAutomationService` dengan `@deprecated` tanpa menghapusnya agar kompatibilitas mundur tetap terjaga.
  3. Menambahkan pemeriksaan integritas `wms_robot_zone_locks` ke `wms:audit` dan `wms:robotics-audit`.

