# Algoritma J3 — `AutomationNpvCalculator`

- **Kelas**: `Modules\Wms\Domain\Algorithms\AutomationNpvCalculator`
- **Sertifikat**: `Modules\Wms\Domain\Certificates\AutomationNpvCertificate`
- **Value Object**: `Modules\Wms\Domain\ValueObjects\RationalRate`

## 1. Masalah & Formulasi Matematis
Evaluasi kelayakan finansial proposal otomasi robotika (`CAPEX` vs arus kas tahunan `CF_1..CF_T`) dilarang menggunakan `float` (Aturan `arch:scan` A3) agar tidak terjadi galat presisi IEEE-754 lintas lingkungan.

Dengan tingkat diskonto rasional `r = num / den` (`RationalRate`), faktor pertumbuhan per tahun adalah:
`1 + r = (den + num) / den`

Untuk periode `t = 1..T`, nilai kini (Present Value) arus kas `CF_t` dihitung secara eksak dengan aritmetika integer (`GMP`/integer skala atau pembulatan half-up deterministik per periode):
`PV_t = round_half_up(CF_t * den^t / (den + num)^t)`
`NPV = -CAPEX + sum_{t=1..T} PV_t`

Selain NPV, kalkulator juga menghitung:
- **IRR (`irr_bps`)**: dalam basis points integer (`100 bps = 1%`) menggunakan pencarian biner integer pada rentang `[0..10000] bps`.
- **Payback Period (`payback_months`)**: jumlah bulan bulat kumulatif hingga arus kas kumulatif `>= CAPEX`.

## 2. Tata Kelola & Aturan Persetujuan (SoD)
- **Pembuat != Penyetuju**: `EvaluateRoboticsRoiProposalAction::approveProposal()` menolak persetujuan oleh `created_by_user_id`.
- **NPV Negatif**: Apabila `npv_minor < 0`, persetujuan wajib menyertakan `justification` non-kosong untuk audit strategis.
- **Sensitivitas**: `RoboticsRoiSensitivityMatrixBuilder::buildGrid()` menguji grid 3x3 pergeseran rate `[-500, 0, +500] bps` vs pengali CAPEX `[90%, 100%, 115%]`.

## 3. Vektor Uji
- CAPEX `1_000_000_000` (Rp 10 jt minor), 4 tahun arus kas masing-masing `400_000_000`, rate `1/4` (`25%`): `npv_minor == -55_360_000` (eksak).
- Pemalsuan `npv` pada `AutomationNpvCertificate` wajib melempar `InvalidArgumentException`.

