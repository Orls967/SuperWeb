# Dokumentasi Algoritma: OrdinaryKriging3D (Geostatistical Kriging 3D)

## 1. Intuisi Geostatistik & Spasial
Ordinary Kriging (OK) adalah metode estimasi spasial linear tak bias terbaik (*Best Linear Unbiased Estimator* / BLUE) dalam geostatistika pertambangan. Berbeda dengan IDW yang hanya mempertimbangkan jarak titik ke target, Kriging juga memperhitungkan struktur spasial autokorelasi (spatial autocorrelation) antar titik sampel itu sendiri melalui fungsi **Semi-variogram**.

Kelebihan utama Ordinary Kriging:
1. **Memperhitungkan Klaster Sampel (De-clustering)**: Titik bor yang berkerumun di satu area tidak akan mendominasi bobot estimasi secara tidak proporsional.
2. **Memberikan Varians Estimasi**: Memberikan ukuran tingkat ketidakpastian (kriging estimation variance) yang berguna untuk mengklasifikasikan cadangan menjadi *Measured*, *Indicated*, atau *Inferred*.

## 2. Formulasi Matematis
### 2.1 Model Semi-Variogram Bola (Spherical Variogram)
Fungsi semi-variogram $\gamma(h)$ memodelkan variabilitas kadar bijih sebagai fungsi dari jarak pemisahan $h$ (dalam meter):
$$\gamma(h) = \begin{cases} 0, & h = 0 \\ c_0 + c \left( 1,5 \frac{h}{a} - 0,5 \left( \frac{h}{a} \right)^3 \right), & 0 < h \le a \\ c_0 + c, & h > a \end{cases}$$
Di mana:
- $c_0$: Efek Nugget (*nugget effect*, variabilitas acak skala mikro dan error sampling lab).
- $c$: Varians struktur spasial (*sill minus nugget*).
- $c_0 + c$: Total Sill (varians populasi data sampel).
- $a$: Jarak pengaruh (*range of influence*, jarak di mana sampel tidak lagi saling berkorelasi).

### 2.2 Sistem Matriks Persamaan Kriging
Untuk menjamin estimator tidak bias ($\sum_{i=1}^n \lambda_i = 1$) dan memiliki varians error minimum, dibentuk sistem persamaan linear berukuran $(n+1) \times (n+1)$ menggunakan pengali Lagrange (*Lagrange multiplier*) $\mu$:
$$\begin{bmatrix}
\gamma(h_{11}) & \gamma(h_{12}) & \dots & \gamma(h_{1n}) & 1 \\
\gamma(h_{21}) & \gamma(h_{22}) & \dots & \gamma(h_{2n}) & 1 \\
\vdots & \vdots & \ddots & \vdots & \vdots \\
\gamma(h_{n1}) & \gamma(h_{n2}) & \dots & \gamma(h_{nn}) & 1 \\
1 & 1 & \dots & 1 & 0
\end{bmatrix}
\begin{bmatrix}
\lambda_1 \\ \lambda_2 \\ \vdots \\ \lambda_n \\ \mu
\end{bmatrix}
=
\begin{bmatrix}
\gamma(h_{10}) \\ \gamma(h_{20}) \\ \vdots \\ \gamma(h_{n0}) \\ 1
\end{bmatrix}$$
Di mana:
- $h_{ij}$: Jarak Euclidean antara sampel $i$ dan sampel $j$.
- $h_{i0}$: Jarak Euclidean antara sampel $i$ dan titik target $P_0$.
- $\lambda_i$: Bobot Kriging untuk sampel $i$.
- $\mu$: Pengali Lagrange yang menegakkan batasan tak bias.

### 2.3 Kadar Estimasi & Varians Kriging
$$Z^*(P_0) = \sum_{i=1}^n \lambda_i Z(P_i)$$
$$\sigma_{OK}^2 = \sum_{i=1}^n \lambda_i \gamma(h_{i0}) + \mu$$

## 3. Vektor Uji & Penyelesaian Numerik
- **Data Sampel**:
  - $S_1 = (0, 0, 0)$, kadar $14.000\text{ ppm}$.
  - $S_2 = (100, 0, 0)$, kadar $18.000\text{ ppm}$.
  - $S_3 = (0, 100, 0)$, kadar $16.000\text{ ppm}$.
  - $S_4 = (100, 100, 0)$, kadar $20.000\text{ ppm}$.
- **Titik Target**: $P_0 = (50, 50, 0)$.
- **Parameter Variogram**:
  - $c_0 = 0,05$, $c = 0,95$, $a = 150\text{ m}$.
- **Penyelesaian Matriks**:
  - Mengingat posisi target simetris terhadap 4 sampel, matriks persamaan menghasilkan solusi bobot identik:
    $$\lambda_1 = \lambda_2 = \lambda_3 = \lambda_4 = 0,25$$
  - Jumlah bobot: $\sum \lambda_i = 1,00$ ($10.000\text{ bps}$).
  - Estimasi kadar:
    $$Z^*(P_0) = 0,25 \times (14.000 + 18.000 + 16.000 + 20.000) = 17.000\text{ ppm}$$
    ($1,70\%$ Ni).
  - Varians Kriging $\sigma_{OK}^2 \ge 0$.

## 4. Sertifikat & Verifikasi
`OrdinaryKriging3DCertificateVerifier`:
- Invarian 1 (Kondisi Tak Bias): $\sum_{i=1}^n \text{WeightBps}_i = 10.000 \pm 2\text{ bps}$.
- Invarian 2: Varians Kriging $\sigma_{OK}^2 \ge 0$.
- Invarian 3: Hash SHA-256 membungkus target, kadar terestimasi, varians Kriging, vektor bobot, dan pengali Lagrange $\mu$.

## 5. Kompleksitas
- Waktu: $O(n^3)$ untuk eliminasi Gauss dengan partial pivoting pada matriks sistem Kriging.
- Ruang: $O(n^2)$ untuk matriks kovarians semi-variogram.
- Lokasi Kode:
  - Algoritma: `Modules\Shared\Domain\Algorithms\Geo\OrdinaryKriging3D`
  - Verifier: `Modules\Shared\Domain\Algorithms\Geo\OrdinaryKriging3DCertificateVerifier`
  - Unit Test: `tests/Unit/Algorithms/OrdinaryKriging3DTest.php`

