# Panduan Penyetelan Kapasitas & Parameter Armada — Fase 279

- **Fase**: 279 (W8-P6.3)
- **Audiens**: `admin`, `logistics_admin`, Warehouse Engineer

## 1. Parameter Alokasi Hungarian (`AllocateRobotMissionsAction` & `RobotFleetDispatchOptimizer`)
| Parameter | Nilai Bawaan | Dampak Jika Dinaikkan | Dampak Jika Diturunkan |
|---|---|---|---|
| `MIN_BATTERY_PCT` | `20%` | Robot lebih aman dari deep-discharge, tetapi utilisasi armada aktif turun | Utilisasi naik, tetapi risiko `LOW_BATTERY_INTERLOCK` di lorong meningkat |
| `WEIGHT_BATTERY_PENALTY` | `15` | Sistem lebih agresif memilih robot berbaterai penuh | Robot berbaterai sedang lebih sering dipilih jika jaraknya dekat |
| `WEIGHT_DISTANCE` | `10` | Minimasi jarak tempuh & konsumsi mWh lebih dominan | Faktor baterai & prioritas lebih dominan |
| `DUMMY_PAD_COST` | `500_000_000` | Wajib strictly `< HungarianSolver::INF (1_000_000_000)` | Tidak boleh sama dengan biaya riil agar misi tidak tertunda |

## 2. Parameter Zona & Kemacetan (`ZoneReservationScheduler` & `ZoneCongestionHeatmapCalculator`)
1. **Lorong Sempit (`capacity_k = 1`)**: Wajib untuk lorong satu arah atau zona dengan risiko tabrakan tinggi. Jika utilisasi mencapai `>= 800‰` (`HIGH`), pecah zona menjadi dua segmen atau naikkan frekuensi `robotics:deadlock-scan`.
2. **Zona Koeksistensi (`capacity_k >= 2`)**: Kecepatan otomatis dibatasi ke `800 mm/s` (`MAX_COEXISTENCE_SPEED_MM_S`) saat lebih dari satu unit aktif bersamaan.
3. **Bucket Heatmap**: `bucketSizeMin = 15` menit pada horizon `60` menit memberikan keseimbangan antara resolusi puncak antrean dan kebisingan data sesaat.

## 3. Parameter Wave, Charger & Pemeliharaan
1. **Kapasitas Wave (`WaveBinPacker`)**: Nilai standar `70` (satuan beban order). Jika terlalu kecil, jumlah wave membengkak; jika terlalu besar, waktu tunggu konsolidasi kurir meningkat (`WaveCutoffSlackCalculator` menjadi `CRITICAL`).
2. **Bay Charger (`ChargingBayContentionResolver`)**: `DEFAULT_MAX_BAYS = 4` (`BAY-01..BAY-04`). Sesuaikan `maxBays` dengan jumlah fisik stasiun dok di gudang.
3. **Konkurensi Pemeliharaan (`RobotMaintenanceWindowPlanner`)**: `MAX_CONCURRENT_MAINTENANCE_PCT = 15%`. Menjaga agar minimal `85%` armada tetap tersedia untuk memenuhi target kesiapan pra-shift (`MIN_ONLINE_RATIO_PERMIL = 700‰`).

## 4. Kalibrasi Energi (`RobotEnergyConsumptionEstimator`)
- Pak standar `480 Wh` (`4_800 mWh` per `1%`), `120 mWh/m` dasar, `+4 mWh/(m*kg)` beban, dan `1_500 mWh` overhead aktuator.
- Selalu uji perubahan parameter menggunakan `php artisan robotics:simulate traffic_density` sebelum diterapkan ke konfigurasi shift.

