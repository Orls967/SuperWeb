# Algoritma — `ZoneCongestionHeatmapCalculator`

- **Kelas**: `Modules\Wms\Domain\Algorithms\ZoneCongestionHeatmapCalculator`
- **Dipanggil oleh**: `RoboticsFleetKpiAggregator`, `RoboticsT1SimulationReportService`

## 1. Tujuan & Metode Bucket Sweep
`ZoneCongestionHeatmapCalculator::analyze()` mengukur tingkat kepadatan penggunaan zona lalu lintas pada horizon waktu `[horizonStartMin, horizonEndMin)` yang dibagi menjadi bucket berdurasi `bucketSizeMin` (bawaan `15` menit pada horizon `0..60`).

Untuk setiap bucket `[b_start, b_end)`:
1. Hitung jumlah reservasi aktif `ZoneReservation` (`IntervalWindow`) yang bertumpukan dengan bucket tersebut.
2. Hitung utilisasi dalam satuan **permil (`‰`)**: `intdiv(peakConcurrent * 1000, capacityK)`.
3. Kembalikan `peak_utilization_permil` serta rincian per bucket untuk visualisasi Control Tower (`/wms/robotics/simulations`).

## 2. Klasifikasi Level Kemacetan
| `peak_utilization_permil` | Level Kemacetan | Rekomendasi Control Tower |
|---|---|---|
| `< 400‰` | `LOW` | Operasi normal (`1800 mm/s`) |
| `400‰ .. 799‰` | `MEDIUM` | Pantau antrean; aktifkan koeksistensi `800 mm/s` bila berbagi ruang |
| `>= 800‰` | `HIGH` | Alihkan misi baru ke zona alternatif atau jalankan `robotics:deadlock-scan` |

## 3. Validasi Parameter
`capacityK <= 0`, `bucketSizeMin <= 0`, atau `horizonEndMin <= horizonStartMin` ditolak dengan `InvalidArgumentException`.

