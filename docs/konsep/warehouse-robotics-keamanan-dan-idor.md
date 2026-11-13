# Model Keamanan, RBAC & Anti-IDOR — Robotika Gudang (Fase 279)

- **Fase**: 279 (W8-P6.3)
- **Komponen Utama**: `RobotMissionPolicy`, `WmsRoboticsOperationsController`, `tests/Architecture/route-roles.php`

## 1. Pertahanan Berlapis (Defense-in-Depth)
Keamanan akses pada Control Tower Robotika ditegakkan pada tiga lapisan berurutan:
1. **Lapisan Autentikasi & Verifikasi Email (`auth`, `verified`)**: Seluruh rute `/wms/robotics/*` menolak pengguna anonim maupun akun yang belum terverifikasi.
2. **Lapisan Matriks Peran (`role:...` Middleware)**:
   - Setiap endpoint memiliki daftar peran eksplisit di `modules/Wms/routes/web.php` dan dipetakan pada `tests/Architecture/route-roles.php` (diverifikasi oleh `RouteAuthorizationMatrixTest`).
   - `admin`: akses penuh seluruh rute operasional, ROI approval, simulasi, dan ekspor.
   - `hub_operator`: akses lantai gudang (`fleet`, `missions`, `zones`, `waves`, `charging`, `incidents`), ditolak `403` pada `roi` & `simulations`.
   - `logistics_admin`: akses perencanaan wave & pemantauan misi (`fleet`, `missions`, `waves`), ditolak `403` pada `zones` & `roi`.
   - `auditor`: akses baca & ekspor (`fleet`, `missions`, `roi`, `simulations`, `incidents`, `export`), ditolak `403` pada `zones` & mutasi wave/ROI.
   - `procurement`: akses baca `roi` untuk evaluasi CAPEX.
3. **Lapisan Kebijakan Entitas Anti-IDOR (`RobotMissionPolicy`)**:
   - `RobotMissionPolicy::view()` dan `RobotMissionPolicy::fallback()` memeriksa kepemilikan penugasan `assigned_operator_user_id` untuk peran `hub_operator`.
   - Seorang operator A tidak dapat memicu `fallback_manual` pada misi yang sedang ditugaskan kepada operator B (`HTTP 403 Forbidden`).

## 2. Validasi Input & Pencegahan Mass-Assignment (`A10 = 0`)
- Seluruh 10 model robotika (`Robot`, `TrafficZone`, `ZoneReservation`, `RobotMission`, `RobotMissionEvent`, `RobotWave`, `RoboticsRoiProposal`, `RobotChargeSchedule`, `RobotTelemetry`, `RobotSafetyIncident`) mendefinisikan `$fillable` eksplisit (tidak ada `$guarded = []`, `A10 = 0`).
- Seluruh endpoint mutasi (`planWave`, `approveRoi`, `reviewIncident`) menggunakan FormRequest khusus (`PlanRoboticsWaveRequest`, `ApproveRoboticsRoiRequest`, `ReviewSafetyIncidentRequest`) yang memvalidasi tipe dan batas nilai sebelum masuk ke Application Action.

## 3. Tata Kelola Persetujuan Finansial (`J3` SoD)
- `EvaluateRoboticsRoiProposalAction::approveProposal()` menegakkan Pemisahan Tugas (`created_by_user_id !== approverUserId`) di sisi server meskipun pengguna memiliki peran `admin`.
- Setiap persetujuan tercatat di `ApprovalEngineService` (`approval_requests`) sehingga tidak ada persetujuan diam-diam tanpa jejak audit.
