# Integrasi `HungarianSolver` untuk Alokasi Armada — Fase 279

- **Solver Inti**: `Modules\Shared\Domain\Algorithms\Assignment\HungarianSolver`
- **Pemanggil**: `AllocateRobotMissionsAction`, `RobotFleetDispatchOptimizer`

## 1. Formulasi Matriks Biaya `dim x dim`
`HungarianSolver` mensyaratkan matriks persegi `n <= m` dan melarang penugasan dengan biaya `>= HungarianSolver::INF (1_000_000_000)`.
- `dim = max(count(missions), count(onlineRobots))`.
- Sel dummy (ketika jumlah misi != jumlah robot) diisi `DUMMY_PAD_COST = 500_000_000` (`< INF`).
- Sel riil `(m_i, r_j)` menjumlahkan komponen integer berikut:
  1. `zoneDist * 10`: jarak hash zona + koordinat `(position_x, position_y)` robot.
  2. `(100 - battery_pct) * 15`: penalti baterai rendah.
  3. `max(0, 100 - priority) * 50`: penalti prioritas rendah agar misi berprioritas tinggi menang deterministik.
  4. `STAFF_BLOCKED_PENALTY = 100_000_000` jika zona sedang diblokir staf (`RobotFleetDispatchOptimizer`).

## 2. Verifikasi Dual `(u, v)` & Tie-Breaking
`HungarianSolver` mengembalikan `AssignmentResult` berisi vektor `assignment`, potensi dual `(u, v)`, dan total biaya optimal dengan tie-breaking indeks kolom terkecil.
- Vektor uji pada `RoboticsAlgorithmsVectorTest` memverifikasi penugasan optimal identik dan sertifikat dual memenuhi `u[i] + v[j] <= cost[i][j]`.

