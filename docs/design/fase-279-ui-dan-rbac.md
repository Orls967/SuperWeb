# Desain UI Control Tower & Matriks RBAC — Fase 279

- **Fase**: 279 (W8-P6.3)
- **Controller**: `WmsRoboticsOperationsController` (dan `WmsRoboticsController` dari Fase 41/awal)
- **Policy**: `RobotMissionPolicy`
- **Views**: `modules/Wms/resources/views/wms/robotics/*.blade.php`

## 1. Prinsip Desain Antarmuka (`x-ds.*` & `ui:lint = 0`)
Seluruh halaman Control Tower Robotika dibangun menggunakan komponen Design System standar (`<x-ds.card>`, `<x-ds.table>`, `<x-ds.badge>`, `<x-ds.button>`, `<x-ds.alert>`, `<x-ds.page-header>`) agar konsisten dengan modul WMS lainnya serta lulus `php artisan ui:lint` dengan 0 pelanggaran:
- Tidak ada inline style atau warna ad-hoc di luar token Design System.
- Seluruh angka uang IDR diformat oleh `RoboticsRoiPortfolioViewModel::formatMinorIdr()` menggunakan pembagian integer (`intdiv` & `% 100`) tanpa operasi `float`.
- Seluruh status misi (`8` status) dipetakan ke badge warna deterministik oleh `RoboticsMissionBoardViewModel::badgeForStatus()`.

## 2. Inventaris Halaman Control Tower (8 Rute GET + 3 Aksi POST/Export)
| Halaman / View | URI | Controller Method | Data Utama yang Ditampilkan |
|---|---|---|---|
| `fleet.blade.php` | `/wms/robotics/fleet` | `fleet()` | Daftar unit armada (`wms_robots`), status online, `battery_pct`, `health_score`, koordinat |
| `missions.blade.php` | `/wms/robotics/missions` | `missions()` | Papan 8 status misi (`RoboticsMissionBoardViewModel`), `retry_count`, flag `fallback_manual` |
| `zones.blade.php` | `/wms/robotics/zones` | `zones()` | Peta zona (`capacity_k`, `staff_blocked`, `block_reason`) & daftar `ZoneReservation` aktif |
| `waves.blade.php` | `/wms/robotics/waves` | `waves()` | Daftar `RobotWave`, carrier, cutoff, utilisasi bin, & form `POST /wms/robotics/waves/plan` |
| `roi.blade.php` | `/wms/robotics/roi` | `roi()` | Portofolio proposal ROI (`RoboticsRoiPortfolioViewModel`), indikator NPV negatif, form approve |
| `simulations.blade.php` | `/wms/robotics/simulations` | `simulations()` | Snapshot simulasi T1, `RoboticsFleetKpiAggregator`, `RoboticsT1SimulationReportService` |
| `charging.blade.php` | `/wms/robotics/charging` | `charging()` | Jadwal pengisian daya (`wms_robot_charge_schedules`), `charger_bay`, jendela menit |
| `incidents.blade.php` | `/wms/robotics/incidents` | `incidents()` | Log insiden keselamatan (`severity`, `status`) & form review `POST /incidents/{id}/review` |

## 3. Penegakan RBAC & Anti-IDOR (`RobotMissionPolicy`)
1. **Level Rute (`routes/web.php` & `tests/Architecture/route-roles.php`)**:
   - Setiap rute dilindungi middleware `auth`, `verified`, dan `role:...`.
   - `auditor` memiliki akses baca ke `fleet`, `missions`, `roi`, `simulations`, `incidents`, dan `export`, tetapi **ditolak 403** pada `zones`.
   - `logistics_admin` memiliki akses ke `fleet`, `missions`, dan `waves`, tetapi **ditolak 403** pada `zones` dan `roi`.
   - `guest` / user tanpa peran ditolak 403 pada seluruh rute robotika.
2. **Level Entitas (`RobotMissionPolicy`)**:
   - `view(User $user, RobotMission $mission)`: `admin`, `auditor`, dan `procurement` dapat melihat seluruh misi; `hub_operator` hanya dapat melihat misi tanpa operator atau misi miliknya sendiri.
   - `fallback(User $user, RobotMission $mission)`: hanya `admin` atau `hub_operator` yang tertaut pada `assigned_operator_user_id` (atau belum bertuan) yang boleh memicu fallback manual. Percobaan lintas operator diblokir (IDOR prevention).

## 4. Validasi Masukan (Form Requests)
- `PlanRoboticsWaveRequest`: memvalidasi `warehouse_id` (UUID/string), `capacity` (integer `>= 10`), dan `batch_code` (string unik/non-kosong).
- `ApproveRoboticsRoiRequest`: memvalidasi `justification` (string opsional pada tingkat HTTP, lalu ditegakkan wajib oleh `EvaluateRoboticsRoiProposalAction` apabila `npv_minor < 0`).
- `ReviewSafetyIncidentRequest`: memvalidasi `decision_status` (`reviewed` / `resolved` / `escalated`).
