# Konsep Audit Dua Sumber Deterministik — Fase 279

- **Fase**: 279 (W8-P6.3)
- **Service**: `Modules\Wms\Application\Services\RoboticsTwoSourceAuditService`
- **Command**: `php artisan robotics:audit` (`RoboticsTwoSourceAuditCommand`)
- **Fixture Korupsi**: `App\Quality\Audit\Fixtures\RoboticsAuditCorruptionFixture`

## 1. Mengapa Audit Dua Sumber Diperlukan?
Dalam operasi harian, constraint foreign key saja tidak cukup menjamin kebenaran invarian bisnis lintas-entitas. Contohnya:
- Robot bisa berstatus `is_online = false` sementara misi aktif masih tertaut padanya.
- Dua `ZoneReservation` `active` bisa bertumpuk pada interval `[start_min, end_min)` melebihi `capacity_k` jika terjadi modifikasi manual di luar scheduler `J1`.
- Proposal ROI berstatus `approved` bisa melanggar Pemisahan Tugas (`created_by_user_id === approved_by_user_id`) atau tidak memiliki `justification` saat `npv_minor < 0`.

## 2. Enam Pemeriksaan Deterministik (`runAudit()`)
1. **Offline Robot with Active Mission**: Memastikan tidak ada `RobotMission` berstatus `assigned|moving|picking|delivering` yang menunjuk ke `Robot` dengan `is_online = false`.
2. **Orphaned Robot/Zone Reference**: Memastikan setiap misi non-`queued` memiliki `robot_id` dan `traffic_zone_id` yang valid dan eksis di tabel induknya.
3. **Zone Capacity Overbooking (`J1`)**: Melakukan sweep-line interval pada seluruh `ZoneReservation` `active` per zona untuk memastikan maksimum konkurensi `<= capacity_k`.
4. **Staff Block Violation**: Memastikan tidak ada `ZoneReservation` `active` yang bertumpuk dengan jendela blokir staf pada `TrafficZone` bertanda `staff_blocked = true`.
5. **State Machine Retry & Fallback Integrity**: Memastikan tidak ada misi `queued` dengan `retry_count > 2` dan seluruh misi `fallback_manual` memiliki flag `fallback_manual = true`.
6. **ROI SoD & Negative-NPV Justification (`J3`)**: Memastikan setiap proposal `approved` memiliki `created_by_user_id !== approved_by_user_id` dan alasan tertulis jika `npv_minor < 0`.

## 3. Verifikasi `computeProductionStateHash()` & Fixture Korupsi
- `computeProductionStateHash()` menghasilkan SHA-256 deterministik atas keadaan seluruh tabel inti robotika. Dua pemanggilan berurutan tanpa mutasi dijamin identik.
- `RoboticsAuditCorruptionFixture::corrupt()` menyisipkan misi yatim (`MIS-CORRUPT-01`) untuk membuktikan di `AuditCommandContractTest` dan `WarehouseRoboticsFullAcceptanceTest` bahwa `robotics:audit` langsung mendeteksi kerusakan dan keluar dengan kode non-0.
