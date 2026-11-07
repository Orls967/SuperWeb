# Dokumentasi Algoritma: LinearBaselineRegression (M3)

## 1. Intuisi Bisnis
Dalam operasi hauling pertambangan, konsumsi bahan bakar solar (liter) berkorelasi kuat dengan kerja mekanis yang dilakukan armada, diukur dalam satuan **Ton-Kilometer** ($TK = \text{Muatan (Ton)} \times \text{Jarak Angkut (km)}$).

Jika sebuah unit truk menghabiskan solar jauh melebihi baseline model linier tanpa alasan geoteknik yang jelas (misal rute berlumpur ekstrem), deviasi tersebut mengindikasikan:
- Penyelewengan/pencurian bahan bakar (fuel theft / siphoning).
- Kebocoran injektor atau penurunan kompresi mesin alat berat.
- Manipulasi data ritase hauling oleh kontraktor.

Algoritma `LinearBaselineRegression` membangun fungsi baseline konsumsi bahan bakar menggunakan regresi kuadrat terkecil linier (Ordinary Least Squares) yang presisi, menghitung deviasi aktual terhadap baseline dalam satuan basis points (bps), dan memicu penahanan pembayaran kontraktor jika deviasi $> 1.500\text{ bps}$ ($> 15\%$).

## 2. Rumus Matematis
Diberikan $n$ titik data riwayat $(x_i, y_i)$, di mana:
- $x_i$: Ribuan Ton-Kilometer ($10^3 \text{ ton-km}$).
- $y_i$: Konsumsi BBM Solar aktual (Liter).

Model regresi:
$$\hat{y} = a + b \cdot x$$

Koefisien kemiringan $b$ dan intersep $a$:
$$b = \frac{n \sum (x_i y_i) - (\sum x_i)(\sum y_i)}{n \sum x_i^2 - (\sum x_i)^2}$$
$$a = \frac{\sum y_i - b \sum x_i}{n} = \bar{y} - b \bar{x}$$

Residu untuk setiap titik riwayat:
$$e_i = y_i - \hat{y}_i$$

Untuk titik evaluasi baru $(x_{eval}, y_{eval})$:
- Prediksi baseline: $\hat{y}_{eval} = a + b \cdot x_{eval}$
- Kelebihan konsumsi: $\Delta y = y_{eval} - \hat{y}_{eval}$
- Deviasi dalam basis points (bps):
  $$\text{Deviasi Bps} = \left\lfloor \frac{\Delta y}{\hat{y}_{eval}} \times 10.000 \right\rfloor$$
- Status Anomali:
  $$\text{Is Anomaly} = \begin{cases} \text{true}, & \text{jika Deviasi Bps} > \text{Threshold Bps} \\ \text{false}, & \text{lainnya} \end{cases}$$

## 3. Vektor Uji Prompt & Contoh Langkah Demi Langkah
- **Data Historis**:
  - $x$ (ribu ton-km): $[10, 20, 30, 40, 50]$ ($n = 5$)
  - $y$ (liter): $[230, 410, 620, 800, 1.010]$

- **Langkah 1 (Agregasi Statistik)**:
  - $\sum x = 10 + 20 + 30 + 40 + 50 = 150 \implies \bar{x} = 30$.
  - $\sum y = 230 + 410 + 620 + 800 + 1.010 = 3.070 \implies \bar{y} = 614$.
  - $\sum x^2 = 100 + 400 + 900 + 1.600 + 2.500 = 5.500$.
  - $\sum (x y) = (10 \times 230) + (20 \times 410) + (30 \times 620) + (40 \times 800) + (50 \times 1.010)$
    $$= 2.300 + 8.200 + 18.600 + 32.000 + 50.500 = 111.600$$

- **Langkah 2 (Hitung $b$ dan $a$)**:
  - Pembilang $b$: $5 \times 111.600 - (150 \times 3.070) = 558.000 - 460.500 = 97.500$.
  - Penyebut $b$: $5 \times 5.500 - (150)^2 = 27.500 - 22.500 = 5.000$.
  - $b = 97.500 / 5.000 = 39/2 = 19,5$.
  - $a = \bar{y} - b \bar{x} = 614 - (19,5 \times 30) = 614 - 585 = 29$.
  - Persamaan Baseline: $\hat{y} = 29 + 19,5 \cdot x$.

- **Langkah 3 (Residu Data Historis)**:
  - $x = 10 \implies \hat{y} = 224 \implies e_1 = 230 - 224 = +6$.
  - $x = 20 \implies \hat{y} = 419 \implies e_2 = 410 - 419 = -9$.
  - $x = 30 \implies \hat{y} = 614 \implies e_3 = 620 - 614 = +6$.
  - $x = 40 \implies \hat{y} = 809 \implies e_4 = 800 - 809 = -9$.
  - $x = 50 \implies \hat{y} = 1.004 \implies e_5 = 1.010 - 1.004 = +6$.
  - Residu: $[6, -9, 6, -9, 6]$ (rata-rata residu tepat 0).

- **Langkah 4 (Evaluasi Titik Baru)**:
  - Titik baru: $x_{eval} = 35$ (ribu ton-km), $y_{eval} = 860$ liter.
  - Prediksi baseline: $\hat{y}_{eval} = 29 + 19,5 \times 35 = 29 + 682,5 = 711,5$ liter.
  - Kelebihan aktual: $\Delta y = 860 - 711,5 = 148,5$ liter.
  - Deviasi: $(148,5 / 711,5) \times 10.000 = 2.087,14 \implies 2.087\text{ bps}$ ($+20,87\%$).
  - Ambang batas anomali: $1.500\text{ bps}$.
  - Karena $2.087 > 1.500 \implies$ **ANOMALI TERKONFIRMASI**.

## 4. Sertifikat & Verifikasi
`LinearBaselineRegressionCertificateVerifier`:
- Invarian 1: $\sum e_i = 0$ (jumlah residu OLS tepat nol).
- Invarian 2: Deviasi bps dan status anomali konsisten dengan formula matematis.
- Hash sertifikat mengikat $(a, b)$, residu historis, dan hasil evaluasi titik baru.
- Lokasi Kode:
  - Algoritma: `Modules\Shared\Domain\Algorithms\Stats\LinearBaselineRegression`
  - Verifier: `Modules\Shared\Domain\Algorithms\Stats\LinearBaselineRegressionCertificateVerifier`
  - Unit Test: `tests/Unit/Algorithms/LinearBaselineRegressionTest.php`
