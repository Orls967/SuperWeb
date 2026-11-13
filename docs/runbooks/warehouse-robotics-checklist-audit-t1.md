# Daftar Periksa Audit T1 & Verifikasi Pra-Merge — Fase 279

- **Fase**: 279 (W8-P6.3)
- **Target**: Memastikan seluruh gerbang kualitas lokal & CI terpenuhi sebelum PR #120 di-merge.

## 1. Verifikasi Seeder Deterministik (`RoboticsT1Seeder`)
Jalankan perintah berikut pada lingkungan bersih:
```bash
php artisan migrate --force
php artisan db:seed --class="Modules\\Wms\\database\\seeders\\RoboticsT1Seeder"
```
Pastikan jumlah data minimal terpenuhi:
- `wms_robots`: `>= 20` baris (`>= 3` baris dengan `is_online = false`).
- `wms_traffic_zones`: `>= 6` baris (`>= 2` dengan `capacity_k = 1`, `>= 1` dengan `staff_blocked = true`).
- `wms_robot_missions`: `>= 120` baris tersebar di seluruh `8` status SM (`>= 10` berstatus `fallback_manual`).
- `wms_robot_waves`: mencakup `>= 60` order lintas `>= 5` carrier berbeda cutoff.
- `wms_robotics_roi_proposals`: `>= 4` proposal (`>= 1` memiliki `npv_minor < 0`).

## 2. Verifikasi Simulasi Sandbox L.4 & Sabotase L.5
1. **Simulasi Normal & Kejutan**:
   ```bash
   php artisan robotics:simulate baseline
   php artisan robotics:simulate two_robots_offline_one_zone_blocked
   ```
   Verifikasi keluaran mencantumkan `hash_identik=true` dan `Audit=PASS`.
2. **Audit Dua Sumber Deterministik**:
   ```bash
   php artisan robotics:audit
   ```
   Verifikasi exit code `0` dan `issues=0`.
3. **Uji Sabotase & Korupsi (L.5)**:
   - `RoboticsAlgorithmsVectorTest` memverifikasi bahwa pemalsuan satu field pada `ZoneReservationCertificate`, `WaveBinPackerCertificate`, `AutomationNpvCertificate`, `TrafficDeadlockCertificate`, `RobotEnergyCertificate`, dan `BatteryDegradationCertificate` selalu melempar `InvalidArgumentException`.
   - `WarehouseRoboticsFullAcceptanceTest` memverifikasi bahwa `RoboticsAuditCorruptionFixture::corrupt()` menyebabkan `robotics:audit` keluar dengan kode non-0 dan menyebut `MIS-CORRUPT-01`.

## 3. Verifikasi Gerbang Arsitektur, UI, Pint & Dokumentasi
| Pemeriksaan | Perintah | Syarat Lulus |
|---|---|---|
| Architecture & Contracts | `./vendor/bin/pest tests/Architecture` | 100% lulus, `SpecsAndDiagramsCompletenessTest` sinkron |
| Modul WMS & Robotics | `./vendor/bin/pest modules/Wms/tests tests/Acceptance/WarehouseRobotics` | 100% lulus tanpa skip |
| Static Rules (`A1`–`A13`) | `php artisan arch:scan` | `Baru: 0`, `Basi: 0` |
| UI Design System Lint | `php artisan ui:lint` | `0` pelanggaran pada 37 file Blade |
| Code Formatting | `./vendor/bin/pint --test modules/Wms/ tests/Acceptance/WarehouseRobotics/` | `"result":"passed"` |
| Docs Link & Mermaid | `php artisan docs:check` | Nihil tautan atau diagram basi |
| Volume T-1 | `bash /tmp/gerbang.sh origin/master 8000 1800` | `B1 >= 8000`, `B3 >= 1800` |

