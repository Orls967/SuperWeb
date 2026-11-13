# Algoritma — `RoboticsRoiSensitivityMatrixBuilder`

- **Kelas**: `Modules\Wms\Domain\Algorithms\RoboticsRoiSensitivityMatrixBuilder`
- **Dependensi**: `AutomationNpvCalculator`, `RationalRate`
- **Dipanggil oleh**: `RoboticsT1SimulationReportService`

## 1. Tujuan & Matriks 3x3
`RoboticsRoiSensitivityMatrixBuilder::buildGrid()` menguji ketahanan proposal investasi otomasi robotika (`J3`) terhadap perubahan asumsi suku bunga diskonto dan pembengkakan biaya CAPEX tanpa menggunakan `float`.

Sumbu matriks:
- **Pergeseran Laju Diskonto**: `[-500 bps, 0 bps, +500 bps]` diterapkan lewat `RationalRate::shiftByBasisPoints($shift)`.
- **Pengali CAPEX**: `[90%, 100%, 115%]` dihitung dengan `intdiv($investmentMinor * $multPct, 100)`.

## 2. Keluaran & Kegunaan Audit
Untuk setiap dari 9 sel `(rate_shift_bps, capex_multiplier_pct)`, builder memanggil `AutomationNpvCalculator::evaluate()` dan mengembalikan `npv_minor`, `irr_bps`, serta `payback_months`. Auditor dapat langsung melihat apakah proposal ber-NPV positif tetap layak jika CAPEX naik 15% atau suku bunga naik 5%.

