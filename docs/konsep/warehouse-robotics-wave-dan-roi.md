# Perencanaan Wave FFD, Cutoff Kurir & Tata Kelola ROI — Fase 279

- **Fase**: 279 (W8-P6.3)
- **Komponen Utama**: `WaveBinPacker`, `WaveCutoffSlackCalculator`, `PlanRoboticsWaveAction`, `RoboticsWaveReconciliationService`, `AutomationNpvCalculator`, `RoboticsRoiSensitivityMatrixBuilder`, `EvaluateRoboticsRoiProposalAction`

## 1. Perencanaan Wave Order (`WaveBinPacker` — Algoritma `J2`)
Dalam pemenuhan order gudang, setiap pesanan memiliki ukuran beban (`size`), kode kurir (`carrier`), dan batas waktu penjemputan (`cutoff_hhmm`, diperoleh melalui kontrak `CarrierCutoffProvider` dari modul `Logistics`).

### Jaminan Konservasi Order
1. **Pengelompokan Ketat `(cutoff, carrier)`**: Tidak ada wave yang mencampur order dari dua kurir atau dua jam cutoff berbeda.
2. **First-Fit Decreasing (FFD)**: Order diurutkan menurun berdasarkan `size` (tie-breaker `id` menaik) lalu dimasukkan ke wave pertama yang sisa kapasitasnya mencukupi.
3. **Sertifikat `WaveBinPackerCertificate`**: Memverifikasi bahwa himpunan ID order pada seluruh wave keluaran identik 100% dengan himpunan masukan (`zero lost, zero duplicate`).
4. **Rekonsiliasi Pasca-Fallback (`RoboticsWaveReconciliationService`)**: Saat misi robot gagal dan beralih ke `fallback_manual`, `reconcileBatch()` memverifikasi bahwa `missing_order_ids` dan `duplicate_order_ids` tetap kosong.

## 2. Evaluasi Kelonggaran Waktu Cutoff (`WaveCutoffSlackCalculator`)
Untuk mencegah keterlambatan truk kurir, setiap wave dievaluasi terhadap jam saat ini (`currentHhmm`):
- `slack_minutes = hhmmToMinutes(cutoff_hhmm) - hhmmToMinutes(currentHhmm) - estimated_pick_min`.
- **`CRITICAL`** (`slack <= 30`): `RoboticsMissionPriorityPolicy` menambahkan bonus prioritas `+15` (`carrierAtRisk = true`) agar misi di wave tersebut diprioritaskan oleh `HungarianSolver`.
- **`TIGHT`** (`31..90`): Dipantau oleh Control Tower `/wms/robotics/waves`.
- **`NORMAL`** (`> 90`): Dieksekusi sesuai urutan standar.

## 3. Evaluasi Kelayakan Investasi Otomasi (`AutomationNpvCalculator` — Algoritma `J3`)
Setiap proposal penambahan robot atau infrastruktur zona dievaluasi menggunakan aritmetika integer penuh:
- **CAPEX & Arus Kas**: Disimpan dalam satuan minor integer IDR (`capex_minor`, `annual_cashflows_minor`).
- **Laju Diskonto Rasional**: Direpresentasikan oleh `RationalRate(numerator, denominator)` (misalnya `1/4` untuk 25%, `1/10` untuk 10%).
- **Keluaran Deterministik**:
  - `npv_minor`: Nilai kini bersih dalam minor IDR.
  - `irr_bps`: Internal Rate of Return dalam basis points integer.
  - `payback_months`: Jumlah bulan bulat hingga modal kembali.
- **Matriks Sensitivitas (`RoboticsRoiSensitivityMatrixBuilder`)**: Menghasilkan grid 3x3 untuk pergeseran suku bunga `[-500, 0, +500] bps` dan pengali CAPEX `[90%, 100%, 115%]`.

## 4. Pemisahan Tugas (SoD) & Justifikasi NPV Negatif
`EvaluateRoboticsRoiProposalAction::approveProposal()` menegakkan dua kontrol tata kelola keras:
1. **Anti-Self-Approval (SoD)**: Jika `created_by_user_id === approverUserId`, persetujuan ditolak (`InvalidArgumentException`).
2. **NPV Negatif Wajib Alasan**: Jika `npv_minor < 0` dan `trim($justification) === ''`, persetujuan ditolak (`InvalidArgumentException`).
3. **Integrasi `ApprovalEngineService`**: Setiap persetujuan sah diteruskan ke `ApprovalEngineService::submit()` dan `approve()` agar tercatat pada jejak persetujuan lintas modul.
