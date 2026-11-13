# Algoritma — `WaveCutoffSlackCalculator`

- **Kelas**: `Modules\Wms\Domain\Algorithms\WaveCutoffSlackCalculator`
- **Uji Vektor**: `tests/Acceptance/WarehouseRobotics/RoboticsAlgorithmsVectorTest.php`

## 1. Formulasi Slack Menit
Untuk setiap wave dengan `cutoff_hhmm` dan estimasi durasi picking `estimated_pick_min`, serta waktu saat ini `currentHhmm`:
1. Konversi `HHMM` ke menit sejak tengah malam (`hhmmToMinutes`: `intdiv(hhmm, 100) * 60 + (hhmm % 100)`), dengan validasi jam `0..23` dan menit `0..59`.
2. Hitung `slack = cutoffMinutes - currentMinutes - estimated_pick_min`.
3. Klasifikasi urgensi:
   - `CRITICAL` jika `slack <= 30` menit.
   - `TIGHT` jika `31 <= slack <= 90` menit.
   - `NORMAL` jika `slack > 90` menit.
   - `at_risk = true` jika `slack < 0`.

## 2. Pengurutan Deterministik
Keluaran diurutkan berdasarkan `slack_minutes` menaik sehingga wave yang paling mendesak selalu berada di puncak daftar prioritas dispatcher dan `RoboticsMissionPriorityPolicy`.
