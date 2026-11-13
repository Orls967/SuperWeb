# Panduan Pengguna (`logistics_admin`) — Fase 279: Perencanaan Wave & Sinkronisasi Cutoff Kurir

## 1. Peran & Akses Logistics Admin
Sebagai **Logistics Admin**, fokus Anda pada Fase 279 adalah memastikan sinkronisasi antara jadwal penjemputan kurir (`CarrierCutoffProvider` di modul `Logistics`) dan eksekusi pemenuhan order oleh armada robot di lantai gudang (`Wms`):
- `/wms/robotics/fleet` — Memantau kapasitas unit robot yang tersedia untuk shift picking.
- `/wms/robotics/missions` — Memantau antrean misi picking dan status `fallback_manual`.
- `/wms/robotics/waves` — Menyusun dan meninjau batch wave (`POST /wms/robotics/waves/plan`).

*Catatan*: Logistics Admin tidak memiliki akses ke `/wms/robotics/zones` (interlock lantai gudang) maupun `/wms/robotics/roi` (persetujuan CAPEX).

## 2. Menyusun Wave FFD (`J2`) & Menjaga Cutoff Kurir
1. Buka `/wms/robotics/waves` dan masukkan `warehouse_id`, `capacity` bin maksimum, serta `batch_code` unik.
2. Sistem memanggil `PlanRoboticsWaveAction`, yang menggunakan `WaveBinPacker` (First-Fit Decreasing) untuk mengelompokkan order berdasarkan pasangan `(cutoff_hhmm, carrier_code)`.
3. **Jaminan Zero-Loss / Zero-Duplicate**:
   - Seluruh order yang masuk ke `PlanRoboticsWaveAction` diverifikasi oleh `WaveBinPackerCertificate`.
   - `RoboticsWaveReconciliationService::reconcileBatch()` dapat digunakan untuk memverifikasi bahwa `missing_order_ids` dan `duplicate_order_ids` keduanya kosong (`conserved = true`).

## 3. Memantau Urgensi Slack Cutoff (`WaveCutoffSlackCalculator`)
Saat beban picking meningkat, pantau klasifikasi urgensi wave terhadap jam cutoff kurir:
- **`CRITICAL` (`slack <= 30` menit)**: Wave berisiko terlambat; `RoboticsMissionPriorityPolicy` menaikkan skor prioritas misi terkait hingga `+15` poin (`carrierAtRisk = true`).
- **`TIGHT` (`31..90` menit)**: Wave perlu dipantau agar tidak terhambat zona macet (`MEDIUM`/`HIGH`).
- **`NORMAL` (`> 90` menit)**: Wave berjalan sesuai jadwal standar.

## 4. Koordinasi Saat Terjadi `fallback_manual`
Apabila salah satu misi robot pada wave Anda gagal melewati batas retry (`retry_count >= 2`), sistem mengalihkan misi tersebut ke `fallback_manual` dan menyusun ulang wave sisa secara otomatis tanpa menghilangkan satu pun order dari batch pengiriman kurir.
