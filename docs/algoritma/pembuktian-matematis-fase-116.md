# Pembuktian Matematis & Teorema Kepatuhan Algoritma Fase 116

## 1. Pendahuluan
Dokumen ini menyajikan pembuktian matematis formal terhadap empat algoritma inti yang diperkenalkan pada Fase 116 (Pasar Sekunder Tiket & Dynamic Bundling):
1. **Teorema 1:** Ketunggalan dan Pembatasan Plafon Anti-Scalping ($P_{\text{proposed}} \le 1.2 \times F$).
2. **Teorema 2:** Batas Bawah Perlindungan Marjin Bundling ($P_{\text{final}} \ge P_{\text{floor}}$).
3. **Teorema 3:** Kemonotonan dan Keterbatasan Skor Prioritas Daftar Tunggu ($0 \le S_{\text{total}} \le 1000$).
4. **Teorema 4:** Konservasi Konservatif Tertutup Distribusi Pajak & Royalti ($\sum A_i \equiv G$).

---

## 2. Teorema 1: Pembatasan Plafon Anti-Scalping
### Pernyataan Teorema
Misalkan $F \in \mathbb{N}^+$ adalah nilai nominal (*face value*) tiket dan $P \in \mathbb{N}^+$ adalah harga penawaran penjualan kembali. Untuk rasio batas legal $R = 120\%$, plafon harga $P_{\max} = \lfloor \frac{120 F}{100} \rfloor$. Transaksi diizinkan jika dan hanya jika $P \le P_{\max}$ dan jumlah pemindahan $T \le 1$.

### Pembuktian
1. Misalkan $P_{\max} = \lfloor \frac{6 F}{5} \rfloor$.
2. Berdasarkan sifat fungsi lantai (*floor function*):
   $$\frac{6 F}{5} - 1 < P_{\max} \le \frac{6 F}{5}$$
3. Karena $F, P \in \mathbb{N}^+$, jika $P \le P_{\max}$, maka:
   $$P \le \frac{6 F}{5} = 1.2 \times F$$
   Ini membuktikan bahwa harga penawaran tidak akan pernah melampaui $120\%$ dari nilai nominal tiket asli.
4. Karena aturan $T \le 1$ membatasi bahwa tiket yang telah berstatus `SETTLED` memiliki $T = 1$, maka upaya penjualan berikutnya akan memiliki masukan $T = 2 > 1$, sehingga fungsi kelayakan $E(F, P, 2) = 0$. Ini membuktikan bahwa tiket sekunder mustahil dijual berulang kali (*single transfer guarantee*). Q.E.D.

---

## 3. Teorema 2: Batas Bawah Perlindungan Marjin Bundling
### Pernyataan Teorema
Diberikan total harga eceran $B_{\text{base}} \in \mathbb{N}^+$, tingkat diskon $D \in [0, 5000]$ basis point, dan batas bawah pengaman harga pokok $P_{\text{floor}} \in \mathbb{N}^+$. Didefinisikan harga sementara $P_{\text{raw}} = B_{\text{base}} - \lfloor \frac{B_{\text{base}} \times D}{10000} \rfloor$ dan harga akhir $P_{\text{final}} = \max(P_{\text{raw}}, P_{\text{floor}})$. Maka untuk setiap kombinasi masukan berlaku:
$$P_{\text{final}} \ge P_{\text{floor}}$$

### Pembuktian
1. Berdasarkan definisi fungsi maksimum $\max(x, y)$:
   $$\max(x, y) \ge y, \quad \forall x, y \in \mathbb{R}$$
2. Substitusikan $x = P_{\text{raw}}$ dan $y = P_{\text{floor}}$:
   $$P_{\text{final}} = \max(P_{\text{raw}}, P_{\text{floor}}) \ge P_{\text{floor}}$$
3. Jika diskon nominal $\Delta_{\text{raw}} > B_{\text{base}} - P_{\text{floor}}$, maka $P_{\text{raw}} < P_{\text{floor}}$, yang mengakibatkan $\max(P_{\text{raw}}, P_{\text{floor}}) = P_{\text{floor}}$.
4. Dengan demikian, harga paket bundling final $P_{\text{final}}$ terbukti secara ketat selalu lebih besar atau sama dengan batas bawah pengaman laba $P_{\text{floor}}$. Q.E.D.

---

## 4. Teorema 3: Kemonotonan dan Batas Skor Prioritas Antrean
### Pernyataan Teorema
Diberikan masukan non-negatif $L \ge 0, \Delta t \ge 0, M \in \{0, 1\}, H \ge 0$. Skor total didefinisikan:
$$S_{\text{total}} = \min\left(1000, \min\left(400, \left\lfloor \frac{L}{10} \right\rfloor \right) + \min\left(250, \left\lfloor \frac{\Delta t}{60} \right\rfloor \times 2 \right) + 200M + \min(150, 25H) \right)$$
Maka berlaku:
1. $0 \le S_{\text{total}} \le 1000$.
2. Fungsi $S_{\text{total}}$ monoton tidak turun (*monotonically non-decreasing*) terhadap setiap variabel $L, \Delta t, M, H$.

### Pembuktian
1. Nilai minimum tercapai saat $L = 0, \Delta t = 0, M = 0, H = 0$:
   $$S_{\text{total}} = \min(1000, 0 + 0 + 0 + 0) = 0 \ge 0$$
2. Nilai jumlahan maksimum teoritis sebelum pembatas luar:
   $$\Sigma_{\max} = 400 + 250 + 200(1) + 150 = 1000$$
3. Karena pembatas luar adalah $\min(1000, \Sigma)$, dan $\Sigma \le 1000$, maka $S_{\text{total}} \le 1000$.
4. Karena fungsi lantai $\lfloor x \rfloor$, perkalian skalar positif, dan fungsi minimum $\min(c, x)$ adalah fungsi monoton tidak turun untuk $x \ge 0$, maka turunan parsial diskrit terhadap setiap variabel memenuhi $\frac{\Delta S}{\Delta x_i} \ge 0$. Terbukti antrean bersikap adil dan konsisten. Q.E.D.

---

## 5. Teorema 4: Konservasi Moneter Tertutup Tanpa Kebocoran
### Pernyataan Teorema
Untuk setiap nilai bruto transaksi sekunder $G \in \mathbb{N}^+$ dan basis points $R_1, R_2, R_3 \ge 0$, jika alokasi komponen didefinisikan sebagai:
$$A_1 = \left\lfloor \frac{G \times R_1}{10000} \right\rfloor, \quad A_2 = \left\lfloor \frac{G \times R_2}{10000} \right\rfloor, \quad A_3 = \left\lfloor \frac{G \times R_3}{10000} \right\rfloor$$
$$A_4 = G - (A_1 + A_2 + A_3)$$
Maka jumlah seluruh alokasi moneter selalu identik dengan nilai bruto transaksi tanpa residu pembulatan:
$$\sum_{i=1}^{4} A_i \equiv G$$

### Pembuktian
$$\sum_{i=1}^{4} A_i = A_1 + A_2 + A_3 + A_4 = (A_1 + A_2 + A_3) + [G - (A_1 + A_2 + A_3)] = G$$
Pembuktian ini berlaku secara umum untuk setiap bilangan bulat positif $G$, menjamin integritas buku besar akuntansi ganda (*double-entry secondary ledger*). Q.E.D.

