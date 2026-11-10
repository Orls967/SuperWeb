# Dokumentasi Algoritma: `SeasonalCashForecaster`

**Namespace**: `Modules\Treasury\Domain\Algorithms\SeasonalCashForecaster`  
**Fase Terkait**: Fase 48 — Multi-Currency & Treasury  
**Sertifikat Terkait**: `SeasonalCashForecastCertificate`, `SeasonalCashForecastCertificateVerifier`  

---

## 1. Intuisi Bisnis & Prinsip Kerja
Likuiditas kas perusahaan perbankan korporat sangat dipengaruhi oleh siklus musiman bisnis berulang (seperti siklus penggajian bulanan, pelunasan faktur vendor mingguan, dan setoran pajak rutin). Model dekomposisi klasik memecah deret waktu arus kas historis menjadi komponen tren jangka panjang (*trend*) dan faktor musiman (*seasonal index*).

Algoritma ini menggunakan pendekatan multiplikatif rasional dengan periode musiman $p = 4$ minggu (mewakili 1 bulan operasional). Data historis dinormalisasi melalui proses deseasonalisasi, kemudian diestimasi garis tren liniernya menggunakan Ordinary Least Squares (OLS) rasional murni. Ramalan 13 minggu ke depan (setara 1 kuartal) dihitung dengan mengalikan proyeksi tren linier terhadap faktor musiman periode terkait, dengan pembulatan berlantai tunggal $\lfloor \cdot \rfloor$ pada hasil akhir.

---

## 2. Formulasi Matematis

Diberikan riwayat arus kas mingguan $y_1, y_2, \dots, y_n$ sebanyak $n$ minggu.
- **Validasi Prasyarat**:
  - $n \ge 2p \implies n \ge 8$. Jika $n < 8 \implies$ Exception `INSUFFICIENT_HISTORY`.
  - $n \bmod p = 0$. Jika $n$ bukan kelipatan $p \implies$ Exception `HISTORY_NOT_FULL_CYCLES`.

### A. Perhitungan Rata-Rata & Indeks Musiman
1. Rata-rata keseluruhan:
   $$\bar{y} = \frac{1}{n} \sum_{t=1}^n y_t$$
2. Rata-rata untuk masing-masing posisi siklus $k \in \{0, 1, 2, 3\}$:
   $$m_k = \frac{1}{n/p} \sum_{j=0}^{n/p - 1} y_{j \cdot p + k + 1}$$
3. Indeks musiman rasional:
   $$S_k = \frac{m_k}{\bar{y}}$$
   Indeks musiman dilaporkan dalam bilangan bulat permil lantai:
   $$\operatorname{IndexPermille}(k) = \left\lfloor 1000 \times \frac{m_k}{\bar{y}} \right\rfloor$$

### B. Deseasonalisasi & Regresi Tren OLS Rasional
Data historis dideseasonalisasi:
$$d_t = \frac{y_t}{S_{(t-1) \bmod p}} = \frac{y_t \times \bar{y}}{m_{(t-1) \bmod p}}$$
Regresi linier atas titik $(t, d_t)$ untuk $t = 1, \dots, n$:
$$\bar{t} = \frac{n+1}{2}, \quad \bar{d} = \frac{1}{n} \sum_{t=1}^n d_t$$
Kemiringan tren ($b$) dan intersep ($a$):
$$b = \frac{\sum_{t=1}^n (t - \bar{t})(d_t - \bar{d})}{\sum_{t=1}^n (t - \bar{t})^2}, \quad a = \bar{d} - b \cdot \bar{t}$$
Seluruh variabel $a$ dan $b$ disimpan dalam bentuk fraksi pecahan bilangan bulat rasional $\frac{\text{num}}{\text{den}}$ tanpa konversi ke float.

### C. Ramalan 13 Minggu
Untuk minggu $t = n+1, n+2, \dots, n+13$:
$$\hat{y}_t = \left\lfloor (a + b \cdot t) \times S_{(t-1) \bmod p} \right\rfloor$$

---

## 3. Contoh Perhitungan Langkah Demi Langkah (Vektor Uji)

### Riwayat 12 Minggu ($n = 12, p = 4$):
$$y = [100, 80, 60, 140, 110, 90, 70, 150, 120, 100, 80, 160]$$

1. **Rata-Rata**:
   - Total $\sum y = 1260$.
   - $\bar{y} = \frac{1260}{12} = 105$.
2. **Rata-Rata Posisi Siklus ($m$)**:
   - Posisi 0: $\frac{100 + 110 + 120}{3} = \frac{330}{3} = 110$.
   - Posisi 1: $\frac{80 + 90 + 100}{3} = \frac{270}{3} = 90$.
   - Posisi 2: $\frac{60 + 70 + 80}{3} = \frac{210}{3} = 70$.
   - Posisi 3: $\frac{140 + 150 + 160}{3} = \frac{450}{3} = 150$.
   - $m = [110, 90, 70, 150]$.
3. **Indeks Musiman Permil**:
   - $S_0 = \frac{110}{105} \implies \lfloor 1000 \times \frac{110}{105} \rfloor = 1047$ permil.
   - $S_1 = \frac{90}{105} \implies \lfloor 1000 \times \frac{90}{105} \rfloor = 857$ permil.
   - $S_2 = \frac{70}{105} \implies \lfloor 1000 \times \frac{70}{105} \rfloor = 666$ permil.
   - $S_3 = \frac{150}{105} \implies \lfloor 1000 \times \frac{150}{105} \rfloor = 1428$ permil.
4. **OLS Rasional**:
   - Intersep $a = \frac{32411}{363}$.
   - Kemiringan $b = \frac{11408}{4719}$.
5. **Hasil Ramalan Minggu 13 s/d 25**:
   - $t=13$: 126
   - $t=14$: 105
   - $t=15$: 83
   - $t=16$: 182
   - $t=17$: 136
   - $t=18$: 113
   - $t=19$: 90
   - $t=20$: 196
   - $t=21$: 146
   - $t=22$: 122
   - $t=23$: 96
   - $t=24$: 210
   - $t=25$: 156
   - **Total 13 Minggu Proyeksi**: $126 + 105 + 83 + 182 + 136 + 113 + 90 + 196 + 146 + 122 + 96 + 210 + 156 = 1.761$.

### Kasus Validasi & Pengecualian:
- Riwayat 10 minggu ($10 \bmod 4 \ne 0$): Memicu exception `HISTORY_NOT_FULL_CYCLES`.
- Riwayat 4 minggu ($4 < 8$): Memicu exception `INSUFFICIENT_HISTORY`.

---

## 4. Kompleksitas & Kasus Tepi
- **Kompleksitas Waktu**: $O(n)$ untuk $n$ minggu historis.
- **Kompleksitas Ruang**: $O(n)$ untuk penyimpanan vektor deseasonalisasi.
- **Kasus Tepi**: Arus kas konstan ($b = 0$) menghasilkan ramalan konstan berpola musiman sempurna.
- **Lokasi Berkas Kode**:
  - `modules/Treasury/Domain/Algorithms/SeasonalCashForecaster.php`
  - `modules/Treasury/Domain/Algorithms/Certificates/SeasonalCashForecastCertificate.php`
  - `modules/Treasury/Domain/Algorithms/Certificates/SeasonalCashForecastCertificateVerifier.php`
