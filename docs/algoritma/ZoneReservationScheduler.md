# Algoritma J1 — `ZoneReservationScheduler`

- **Kelas**: `Modules\Wms\Domain\Algorithms\ZoneReservationScheduler`
- **Sertifikat**: `Modules\Wms\Domain\Certificates\ZoneReservationCertificate`
- **Uji Vektor**: `tests/Acceptance/WarehouseRobotics/RoboticsAlgorithmsVectorTest.php`

## 1. Masalah & Formulasi Matematis
Diberikan sebuah zona lalu lintas dengan kapasitas konkuren `k >= 1`, himpunan reservasi aktif berbentuk interval setengah-terbuka `R = {[s_i, e_i)}`, jendela blokir staf `B = {[bs_j, be_j)}`, durasi misi `d > 0` menit, dan waktu mulai paling awal `earliest >= 0`.

Tujuan algoritma adalah menemukan bilangan bulat `t >= earliest` **paling kecil** sedemikian rupa sehingga:
1. Interval `[t, t + d)` tidak bertumpuk dengan satu pun jendela blokir staf `b in B` (overlap setengah-terbuka: `t < be_j && (t + d) > bs_j`).
2. Pada setiap titik `x in [t, t + d)`, jumlah reservasi aktif `r in R` yang mencakup `x` tidak pernah mencapai atau melampaui `k` (`count({r in R : s_r <= x < e_r}) < k`).

## 2. Langkah Algoritma (Sweep-Line Titik Kritis)
1. Validasi `k >= 1`, `d >= 1`, `earliest >= 0`.
2. Kumpulkan himpunan kandidat titik mulai `C`: mulai dengan `earliest`, lalu tambahkan seluruh titik akhir `e_i` dari `R` dan `be_j` dari `B` yang bernilai `>= earliest`.
3. Urutkan `C` secara menaik dan hilangkan duplikat.
4. Untuk setiap kandidat `t in C`:
   - Uji apakah `[t, t + d)` bertumpuk dengan blokir staf `B`. Jika ya, lanjut ke kandidat berikutnya.
   - Hitung maksimum okupansi konkuren pada `[t, t + d)` menggunakan event `+1` di `max(t, s_i)` dan `-1` di `min(t + d, e_i)` bagi setiap reservasi yang bertumpuk.
   - Jika maksimum okupansi `< k`, maka `t` adalah solusi optimal!
5. Bangun `ZoneReservationCertificate` berisi `canonicalInput`, `steps`, `result = ['start' => t, 'end' => t + d]`, dan `sha256`.

## 3. Kompleksitas & Vektor Uji
- **Kompleksitas**: `O((|R| + |B|)^2 log(|R| + |B|))` dengan aritmetika integer penuh (nol floating-point).
- **Vektor Uji**:
  - `R = [[0,5), [7,10)]`, `k=1`, `d=3`, `earliest=4` -> hasil `[10, 13)`.
  - `R = [[0,5), [7,10)]`, `k=1`, `d=2`, `earliest=4` -> hasil `[5, 7)` (mengisi celah sempit).
  - Pemalsuan `result` pada `ZoneReservationCertificate` wajib melempar `InvalidArgumentException` saat `verifyOrFail()`.

