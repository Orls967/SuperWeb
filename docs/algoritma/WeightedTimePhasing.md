# Dokumentasi Algoritma: WeightedTimePhasing (M1)

## 1. Intuisi Bisnis
Dalam operasi tambang terbuka, target produksi bulanan tidak dapat dibagi rata begitu saja ke 30 hari karena kondisi operasional bervariasi:
- Hari kerja dengan curah hujan tinggi memiliki jam operasi efektif lebih rendah.
- Hari yang dijadwalkan perawatan rutin pabrik atau alat memiliki ketersediaan jam terbatas.
- Hari libur atau perawatan terjadwal memiliki bobot kerja rendah.

Algoritma `WeightedTimePhasing` memecah kuota produksi bulanan (misal 100.003 ton) ke dalam hari-hari kerja berdasarkan bobot jam efektif harian, memastikan bahwa seluruh kuota terdistribusi tanpa pembulatan desimal yang hilang, menggunakan metode proporsional Hamilton (Largest Remainder).

## 2. Rumus Matematis
Diberikan:
- Target kuota total $Q \in \mathbb{N}$ (dalam kg atau ton).
- Vektor bobot harian $w = [w_1, w_2, \dots, w_n]$ dengan $w_i \in \mathbb{R}^+$.
- Total bobot $W = \sum_{i=1}^n w_i$.

Langkah perhitungan:
1. Hitung kuota eksak untuk setiap hari $i$:
   $$q_i = Q \times \frac{w_i}{W}$$
2. Hitung alokasi dasar integer:
   $$a_i = \lfloor q_i \rfloor$$
3. Hitung sisa residu desimal untuk setiap hari:
   $$r_i = q_i - a_i$$
4. Hitung kekurangan kuota yang belum teralokasi:
   $$k = Q - \sum_{i=1}^n a_i$$
5. Urutkan hari berdasarkan nilai residu $r_i$ secara menurun (tie-break menggunakan indeks $i$ terkecil).
6. Tambahkan 1 unit kuota kepada $k$ hari dengan residu terbesar:
   $$y_i = \begin{cases} a_i + 1, & \text{jika } i \in \text{Top } k \\ a_i, & \text{lainnya} \end{cases}$$
7. Hitung kurva kumulatif $C_m = \sum_{i=1}^m y_i$.

## 3. Vektor Uji Prompt & Contoh Langkah Demi Langkah
- **Input Vektor**:
  - Kuota $Q = 100.003$ ton.
  - Vektor bobot $w = [5, 5, 5, 5, 4, 4, 2]$ (total $W = 30$).
- **Langkah 1 (Kuota Eksak)**:
  - Hari 1–4 ($w_i = 5$): $100.003 \times (5/30) = 16.667,166\dots$ $\to$ dasar $16.667$, residu $0,1667$.
  - Hari 5–6 ($w_i = 4$): $100.003 \times (4/30) = 13.333,733\dots$ $\to$ dasar $13.333$, residu $0,7333$.
  - Hari 7 ($w_i = 2$): $100.003 \times (2/30) = 6.666,866\dots$ $\to$ dasar $6.666$, residu $0,8667$.
- **Langkah 2 (Total Dasar & Selisih)**:
  - $\sum a_i = 4 \times 16.667 + 2 \times 13.333 + 6.666 = 66.668 + 26.666 + 6.666 = 100.000$.
  - Sisa yang harus dibagikan: $k = 100.003 - 100.000 = 3$.
- **Langkah 3 (Peringkat Residu Terbesar)**:
  - Peringkat 1: Hari 7 (residu $0,8667$) $\to +1 \implies 6.667$.
  - Peringkat 2: Hari 5 (residu $0,7333$) $\to +1 \implies 13.334$.
  - Peringkat 3: Hari 6 (residu $0,7333$, tie-break) $\to +1 \implies 13.334$.
  - Hari 1–4 tetap $16.667$.
- **Hasil Akhir Alokasi**:
  $$[16.667, 16.667, 16.667, 16.667, 13.334, 13.334, 6.667]$$
- **Kurva Kumulatif**:
  $$[16.667, 33.334, 50.001, 66.668, 80.002, 93.336, 100.003]$$

## 4. Sertifikat & Verifikasi
Sertifikat diverifikasi oleh `WeightedTimePhasingCertificateVerifier`:
- Invarian 1: $\sum_{i=1}^n y_i = Q$ (konservasi total tonase).
- Invarian 2: $|y_i - q_i| < 1$ untuk setiap hari $i$ (properti Hamilton).
- Invarian 3: $C_n = Q$ dan $C_i \le C_{i+1}$ (monotonik naik).
- Hash SHA-256 membungkus kuota, alokasi, kurva kumulatif, dan residu.

## 5. Kompleksitas & Batas Eksekusi
- Kompleksitas Waktu: $O(n \log n)$ untuk sorting residu hari.
- Kompleksitas Ruang: $O(n)$ untuk menyimpan array alokasi dan kurva kumulatif.
- Lokasi Kode:
  - Algoritma: `Modules\Shared\Domain\Algorithms\Scheduling\WeightedTimePhasing`
  - Verifier: `Modules\Shared\Domain\Algorithms\Scheduling\WeightedTimePhasingCertificateVerifier`
  - Unit Test: `tests/Unit/Algorithms/WeightedTimePhasingTest.php`

