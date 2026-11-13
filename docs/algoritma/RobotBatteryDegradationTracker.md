# Algoritma — `RobotBatteryDegradationTracker`

- **Kelas**: `Modules\Wms\Domain\Algorithms\RobotBatteryDegradationTracker`
- **Sertifikat**: `Modules\Wms\Domain\Certificates\BatteryDegradationCertificate`
- **Dipanggil oleh**: `RoboticsFleetReadinessService`

## 1. Model Degradasi Kapasitas (Integer Permil)
Setiap unit robot memulai masa pak dengan kapasitas nominal `INITIAL_HEALTH_PERMIL = 1000` (100,0%). Penurunan kapasitas dihitung deterministik:
- `cycleLoss = intdiv(cycle_count, 10)` (setiap 10 siklus pengisian menurunkan 1‰).
- `deepLoss = deep_discharge_events * 3` (setiap kejadian baterai di bawah 15% menurunkan 3‰).
- `health_permil = max(0, 1000 - cycleLoss - deepLoss)`.

## 2. Ambang Penggantian & Sertifikat
- Unit dengan `health_permil <= 750` (`REPLACEMENT_THRESHOLD_PERMIL`) ditandai `replace_recommended = true`.
- `BatteryDegradationCertificate::verify()` memverifikasi `totalRobots`, `replacementCount`, `fleetAvgHealthPermil`, dan `signatureHash` SHA-256 kanonik.
- Vektor uji: robot 1 (`1200` siklus, `5` deep-discharge -> `865‰`), robot 2 (`2600` siklus, `2` deep-discharge -> `734‰`, rekomendasi ganti) -> rata-rata `799‰`, `replacementCount = 1`.
