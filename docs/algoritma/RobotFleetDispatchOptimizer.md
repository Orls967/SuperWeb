# Algoritma — `RobotFleetDispatchOptimizer`

- **Kelas**: `Modules\Wms\Domain\Algorithms\RobotFleetDispatchOptimizer`
- **Dependensi**: `HungarianSolver`, `RobotEnergyConsumptionEstimator`, `RoboticsZoneInterlockEvaluator`
- **Uji Vektor**: `tests/Acceptance/WarehouseRobotics/RoboticsAlgorithmsVectorTest.php`

## 1. Tujuan & Struktur Matriks Biaya
`RobotFleetDispatchOptimizer` menggabungkan optimasi penugasan global Kuhn-Munkres (`HungarianSolver`) dengan dua penjaga domain keselamatan:
1. **Penyaringan Robot Layak**: Hanya robot dengan `is_online = true` dan `battery_pct >= 20%` yang diikutsertakan dalam kolom matriks.
2. **Konstruksi Matriks Persegi `dim x dim`** (`dim = max(N_missions, N_robots)`):
   - Sel padding dummy diberi biaya `DUMMY_PAD_COST = 500_000_000` (wajib `< HungarianSolver::INF = 1_000_000_000` agar solver tetap layak).
   - Sel riil `(m_i, r_j)` dihitung dari:
     - `zoneDist = abs(crc32(zone_code) % 50) + position_x + position_y`
     - `batteryPenalty = (100 - battery_pct) * 15`
     - `priorityPenalty = max(0, 100 - priority) * 50`
     - `interlockPenalty = 100_000_000` bila `RoboticsZoneInterlockEvaluator::evaluateEntry()` mengembalikan `allowed = false` (`STAFF_ZONE_BLOCKED` atau `ZONE_CAPACITY_FULL`).
     - `energyPenalty = 25_000_000` bila `RobotEnergyConsumptionEstimator::estimateBatch()` mengembalikan `allFeasible = false`.

## 2. Penyaringan Pasca-Solusi
Setelah `HungarianSolver::solve($costMatrix)` mengembalikan penugasan optimal beserta sertifikat dual `(u, v)`:
- Setiap pasangan `(m_i, r_j)` diperiksa ulang terhadap `feasibilityGrid[$i][$j]`.
- Apabila `interlock_allowed === false` atau `energy_feasible === false`, misi tersebut **tidak dialokasikan** dan dimasukkan ke `unassigned_mission_ids` sehingga misi berisiko tidak pernah masuk ke zona diblokir staf.

## 3. Kompleksitas & Vektor Uji
- **Kompleksitas**: `O(dim^3)` untuk pemecahan Hungarian ditambah `O(N_missions * N_robots)` evaluasi interlock & energi.
- **Vektor Uji**: 2 misi (`MIS-1` di `Z-OK` prioritas 90, `MIS-2` di `Z-BLK` `staff_blocked=true` prioritas 50) dan 1 robot online -> `MIS-1` terpilih, `MIS-2` masuk `unassigned_mission_ids`.
