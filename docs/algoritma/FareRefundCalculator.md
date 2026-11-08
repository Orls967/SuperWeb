# Algoritma: FareRefundCalculator (A4)

## 1. Lokasi Kode & Arsitektur
- **Kelas Algoritma:** `Modules\Shared\Domain\Algorithms\Aviation\FareRefundCalculator`
- **Sertifikat:** `Modules\Shared\Domain\Algorithms\Aviation\FareRefundCertificate`
- **Verifier:** `Modules\Shared\Domain\Algorithms\Aviation\FareRefundCertificateVerifier`
- **Hasil:** `Modules\Shared\Domain\Algorithms\Aviation\FareRefundResult`
- **Pillar/Lini:** Lini 26 (Aviasi, Fase 177)
- **Kompleksitas Waktu:** $O(1)$ pencarian tabel tarif berdimensi tetap.
- **Kompleksitas Ruang:** $O(1)$ integer aritmetika satuan minor.

## 2. Intuisi Bisnis & Aturan Pengembalian Dana
Pengembalian dana tiket penerbangan komersial mengikuti Peraturan Menteri Perhubungan No. PM 185 Tahun 2015 dan standar komersial industri:
1. **Pita Waktu Pembatalan (*Time Windows*):** Persentase potongan pengembalian dana bergantung pada jarak waktu antara pengajuan pembatalan dengan jadwal keberangkatan penerbangan:
   - Jauh hari: $\ge 72$ jam sebelum berangkat.
   - Menengah: $24 \le T < 72$ jam sebelum berangkat.
   - Mepet: $< 24$ jam sebelum berangkat.
   - Tidak Hadir (*No-Show*): Penumpang membatalkan setelah pesawat berangkat atau tidak hadir di pintu keberangkatan.
2. **Kelas Tarif (*Fare Buckets*):**
   - **FLEX:** Tiket fleksibel bisnis/ekonomi premium dengan hak pengembalian dana tertinggi, termasuk saat no-show.
   - **STD (Standard):** Tiket ekonomi reguler dengan hak refund moderat, namun hangus (0 bps) saat no-show.
   - **PROMO:** Tiket bertarif promosi super hemat tanpa hak refund jika dibatalkan dalam waktu $< 72$ jam.
3. **Biaya Administrasi Maskapai (*Admin Fee*):** Ditetapkan flat sebesar Rp100.000 ($100.000\text{ IDR}$) dipotong dari komponen tarif dasar yang berhak dikembalikan (tidak boleh membuat saldo dasar menjadi negatif).
4. **Pajak Bandara & Pemerintah (PSC/Tax):** Komponen pajak dikembalikan 100% penuh kepada penumpang pada setiap skenario pembatalan.
5. **Pembatalan Sepihak oleh Maskapai:** Jika penerbangan dibatalkan oleh pihak maskapai (faktor teknis/operasional), seluruh komponen tarif dasar dan pajak dikembalikan 100% tanpa potongan biaya administrasi apapun.

## 3. Matriks Persentase Refund Tarif Dasar (Basis Poin)
| Kelas Tarif | $\ge 72$ Jam | 24–72 Jam | $< 24$ Jam | No-Show |
|---|---|---|---|---|
| **FLEX** | 10.000 bps (100%) | 9.000 bps (90%) | 7.000 bps (70%) | 5.000 bps (50%) |
| **STD** | 8.000 bps (80%) | 6.000 bps (60%) | 3.000 bps (30%) | 0 bps (0%) |
| **PROMO** | 5.000 bps (50%) | 0 bps (0%) | 0 bps (0%) | 0 bps (0%) |

## 4. Contoh Hitung Langkah Demi Langkah (Vektor Prompt)
1. **STD, Tarif Dasar Rp1.500.000, Pajak Rp250.000, Waktu 100 Jam:**
   - Waktu 100 jam $\ge 72$ jam $\implies$ Tarif dasar STD $= 80\%$ (8.000 bps).
   - Refund kotor dasar $= 1.500.000 \times 80\% = 1.200.000$.
   - Potongan admin $= 100.000 \implies$ Refund bersih dasar $= 1.100.000$.
   - Pengembalian pajak $= 250.000$.
   - Total refund $= 1.100.000 + 250.000 = 1.350.000\text{ IDR}$.
2. **STD, Waktu 30 Jam:**
   - Waktu 30 jam $\in [24, 72)$ jam $\implies$ Tarif dasar STD $= 60\%$ (6.000 bps).
   - Refund kotor dasar $= 1.500.000 \times 60\% = 900.000$.
   - Potongan admin $= 100.000 \implies$ Refund bersih dasar $= 800.000$.
   - Total refund $= 800.000 + 250.000 = 1.050.000\text{ IDR}$.
3. **PROMO, Waktu 10 Jam:**
   - Waktu 10 jam $< 24$ jam $\implies$ Tarif dasar PROMO $= 0\%$ (0 bps).
   - Refund kotor dasar $= 0 \implies$ Potongan admin $= 0$.
   - Pengembalian pajak $= 250.000$.
   - Total refund $= 0 + 250.000 = 250.000\text{ IDR}$.
4. **FLEX No-Show, Tarif Dasar Rp2.000.000, Pajak Rp300.000:**
   - Kondisi No-Show $\implies$ Tarif dasar FLEX $= 50\%$ (5.000 bps).
   - Refund kotor dasar $= 2.000.000 \times 50\% = 1.000.000$.
   - Potongan admin $= 100.000 \implies$ Refund bersih dasar $= 900.000$.
   - Pengembalian pajak $= 300.000$.
   - Total refund $= 900.000 + 300.000 = 1.200.000\text{ IDR}$.
5. **Dibatalkan Maskapai, Tarif Dasar Rp900.000, Pajak Rp100.000:**
   - Pembatalan oleh maskapai $\implies$ Refund dasar 100% tanpa biaya admin.
   - Refund dasar $= 900.000$, Pajak $= 100.000$.
   - Total refund $= 900.000 + 100.000 = 1.000.000\text{ IDR}$.

## 5. Sertifikat & Verifikasi
Hasil perhitungan diikat dalam `FareRefundCertificate` dan diverifikasi oleh `FareRefundCertificateVerifier` untuk memastikan bahwa total pengembalian sama dengan dasar ditambah pajak, refund dasar tidak melebihi harga tiket awal, dan tidak ada komponen finansial yang bernilai negatif.

