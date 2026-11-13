# Panduan Pengguna (`admin`) — Fase 279: Tata Kelola ROI, Simulasi & Kendali Penuh Robotika

## 1. Kewenangan Peran Administrator
Peran `admin` memiliki akses penuh terhadap seluruh grup rute `/wms/robotics/*`, termasuk dua kewenangan kritis yang tidak diberikan kepada operator gudang maupun auditor:
1. **Persetujuan Proposal ROI Otomasi (`POST /wms/robotics/roi/{proposal}/approve`)**: Menyetujui investasi CAPEX robotika melalui `ApprovalEngineService` dengan penegakan Pemisahan Tugas (SoD).
2. **Eksekusi & Evaluasi Simulasi Sandbox (`/wms/robotics/simulations`)**: Memantau hasil simulasi T1, matriks sensitivitas NPV, serta kesiapan armada pra-shift.

## 2. Alur Kerja Persetujuan Proposal ROI (`J3`)
1. Buka `/wms/robotics/roi` untuk melihat daftar seluruh proposal (`pending` dan `approved`) yang telah diformat oleh `RoboticsRoiPortfolioViewModel`.
2. Periksa metrik deterministik tiap proposal:
   - `capex_idr` & `npv_idr` (dihitung dalam minor integer IDR oleh `AutomationNpvCalculator`).
   - `irr_bps` (Internal Rate of Return dalam basis points; `1000 bps = 10,00%`).
   - `payback_months` (jumlah bulan pengembalian modal).
3. **Aturan Wajib Sebelum Menyetujui**:
   - **Pembuat != Penyetuju (SoD)**: Jika Anda adalah `created_by_user_id` dari proposal tersebut, sistem menolak persetujuan Anda (`InvalidArgumentException`). Minta akun `admin` lain untuk meninjau dan menyetujui.
   - **Justifikasi Wajib untuk NPV Negatif**: Jika `npv_minor < 0` (ditandai `requires_justification = true`), Anda wajib mengisi kolom `justification` dengan alasan strategis tertulis (misalnya keselamatan kerja atau kepatuhan regulasi shift malam). Tanpa alasan tertulis, `EvaluateRoboticsRoiProposalAction` menolak persetujuan.
4. Setelah disetujui, permintaan dan persetujuan tercatat permanen di `ApprovalEngine` (`approval_requests` & `approval_steps`).

## 3. Evaluasi Kesiapan Armada & Serah-Terima Shift
Administrator dapat menjalankan pemeriksaan kesiapan (`RoboticsFleetReadinessService::evaluateReadiness()`) dan laporan serah-terima (`RoboticsShiftHandoverReportService::buildHandover()`) untuk memastikan:
- Rasio armada online memenuhi ambang minimum (`>= 700‰`).
- Tidak ada isu pada `robotics:audit` (`TWO_SOURCE_AUDIT_CLEAN`).
- Tidak ada insiden keselamatan berstatus `reported` yang belum ditinjau.
- Paket baterai dengan `health_permil <= 750‰` (`RobotBatteryDegradationTracker`) telah dijadwalkan untuk penggantian/maintenance (`RobotMaintenanceWindowPlanner`).

## 4. Ekspor Data & Audit Kepatuhan
Dari panel atau URL langsung `/wms/robotics/export/{entity}`, administrator dapat mengunduh CSV untuk `robots`, `missions`, `waves`, `roi`, dan `incidents` guna kebutuhan pelaporan manajemen.
