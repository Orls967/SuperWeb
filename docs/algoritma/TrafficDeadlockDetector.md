# Algoritma — `TrafficDeadlockDetector`

- **Kelas**: `Modules\Wms\Domain\Algorithms\TrafficDeadlockDetector`
- **Sertifikat**: `Modules\Wms\Domain\Certificates\TrafficDeadlockCertificate`
- **Action**: `Modules\Wms\Application\Actions\ResolveTrafficDeadlockAction`

## 1. Masalah & Formulasi Graf Tunggu (Wait-For Graph)
Di lorong sempit (`capacity_k = 1`), dua atau lebih misi robot dapat saling menunggu pelepasan zona (`M_a -> M_b -> ... -> M_a`), membentuk siklus berarah pada graf tunggu `G = (V, E)`.

Tujuan:
1. Mendeteksi keberadaan siklus berarah secara deterministik (DFS 3-warna: `UNVISITED`, `VISITING`, `VISITED` dengan urutan node terurut leksikografis).
2. Jika siklus ditemukan, memilih tepat satu **victim node** dengan aturan deterministik:
   - Prioritas numerik terendah (`priority` paling kecil).
   - Jika prioritas seri, pilih `mission_code` terkecil secara leksikografis agar deterministik lintas eksekusi.

## 2. Resolusi Operasional (`ResolveTrafficDeadlockAction`)
1. Bangun graf tunggu dari `ZoneReservation` aktif dan antrean zona.
2. Jalankan `TrafficDeadlockDetector::detectAndResolve()`.
3. Jika `has_deadlock == true`, transisikan misi `victim` ke `failed` dengan kode alasan `DEADLOCK_VICTIM_PREEMPTED` melalui `RobotMissionTransitionService`, lepaskan reservasi zona miliknya, dan catat insiden keselamatan bila berulang.

## 3. Vektor Uji
- Siklus `M-01 -> M-02 -> M-03 -> M-01` dengan prioritas `['M-01' => 90, 'M-02' => 20, 'M-03' => 50]` -> `has_deadlock = true`, `victim_node = 'M-02'`.
- Pemalsuan `has_deadlock` pada `TrafficDeadlockCertificate` wajib melempar `InvalidArgumentException`.

