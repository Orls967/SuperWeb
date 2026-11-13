# Algoritma — `ChargingBayContentionResolver`

- **Kelas**: `Modules\Wms\Domain\Algorithms\ChargingBayContentionResolver`
- **Dipanggil oleh**: `RoboticsShiftHandoverReportService`

## 1. Pewarnaan Interval Bay Fisik
Stasiun pengisian daya memiliki jumlah bay fisik terbatas (`DEFAULT_MAX_BAYS = 4`). Jika beberapa jadwal charging bertumpukan pada interval `[window_start_min, window_end_min)`, resolver melakukan pewarnaan interval deterministik:
1. Urutkan permintaan berdasarkan `window_start_min` menaik, lalu `robot_id` menaik.
2. Untuk setiap permintaan, cari bay dengan nomor indeks terkecil `1..maxBays` yang `bayFreeAt[b] <= window_start_min`.
3. Jika ditemukan, tetapkan `BAY-01..BAY-XX` dan perbarui `bayFreeAt[b] = window_end_min`.
4. Jika seluruh bay penuh, masukkan `robot_id` ke `deferred_robot_ids` untuk dijadwalkan pada siklus berikutnya.

## 2. Vektor Uji
3 permintaan bertumpuk (`[0,20)`, `[5,25)`, `[10,30)`) pada `maxBays = 2` -> 2 dialokasikan (`BAY-01`, `BAY-02`), robot ke-3 masuk `deferred_robot_ids`.
