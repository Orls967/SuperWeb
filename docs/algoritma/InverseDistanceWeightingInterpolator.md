# Dokumentasi Algoritma: InverseDistanceWeightingInterpolator (3D IDW)

## 1. Intuisi Geologis & Pertambangan
Dalam estimasi sumber daya nikel laterit dan batubara, kadar bijih pada blok voxel yang belum ditambang diestimasi dari titik-titik lubang bor eksplorasi (borehole assays). Berdasarkan Hukum Pertama Geografi Tobler (*Everything is related to everything else, but near things are more related than distant things*), titik sampel yang lebih dekat ke pusat voxel blok tambang memiliki pengaruh yang jauh lebih besar terhadap kadar estimasi dibandingkan titik yang jauh.

Algoritma `InverseDistanceWeightingInterpolator` menghitung rata-rata tertimbang kadar nikel (% Ni) dan unsur pengotor (Fe, Co) pada koordinat 3D $(x, y, z)$ di mana bobot setiap sampel berbanding terbalik dengan kuadrat jaraknya ke titik target voxel.

## 2. Formulasi Matematis
Diberikan:
- Titik target voxel: $P = (x, y, z) \in \mathbb{R}^3$ (disimpan dalam milimeter integer).
- Himpunan $n$ titik sampel borehole: $S = \{ (x_i, y_i, z_i, g_i) \}_{i=1}^n$, dengan kadar $g_i \in \mathbb{N}$ (dalam ppm).
- Parameter daya (power exponent): $p = 2$ (kuadrat terbalik).

Langkah-langkah komputasi:
1. **Pemeriksaan Titik Berimpit (Singularitas Jarak Nol)**:
   Hitung jarak Euclidean 3D:
   $$d_i = \sqrt{(x - x_i)^2 + (y - y_i)^2 + (z - z_i)^2}$$
   Jika terdapat sampel $j$ dengan $d_j = 0$, maka kadar estimasi $Z(P) = g_j$ secara eksak dengan bobot $10.000\text{ bps}$ ($100\%$).

2. **Perhitungan Bobot Invers Jarak**:
   Untuk setiap sampel $i$ dengan $d_i > 0$:
   $$w_i' = \frac{1}{(d_i / 1.000)^p}$$
   Jumlah bobot tak ternormalisasi:
   $$W_{sum} = \sum_{i=1}^n w_i'$$

3. **Normalisasi Bobot ke Satuan Basis Points (bps)**:
   $$w_i = \frac{w_i'}{W_{sum}}$$
   $$\text{WeightBps}_i = \left\lfloor w_i \times 10.000 + 0,5 \right\rfloor$$
   Selisih pembulatan $\Delta = 10.000 - \sum \text{WeightBps}_i$ dialokasikan ke sampel dengan bobot terbesar.

4. **Kadar Terinterpolasi**:
   $$Z(P) = \left\lfloor \sum_{i=1}^n (w_i \times g_i) + 0,5 \right\rfloor$$

## 3. Vektor Uji & Contoh Hitung Langkah Demi Langkah
- **Empat Sampel Simetris Mengelilingi Target**:
  - $S_1 = (0, 0, 0)$ dengan $g_1 = 12.000\text{ ppm}$ ($1,2\%$ Ni).
  - $S_2 = (100, 0, 0)$ dengan $g_2 = 18.000\text{ ppm}$ ($1,8\%$ Ni).
  - $S_3 = (0, 100, 0)$ dengan $g_3 = 15.000\text{ ppm}$ ($1,5\%$ Ni).
  - $S_4 = (100, 100, 0)$ dengan $g_4 = 21.000\text{ ppm}$ ($2,1\%$ Ni).
  (Koordinat dalam meter: $100.000\text{ mm}$).
- **Titik Target**: $P = (50, 50, 0)$ (tepat di tengah bujur sangkar).
- **Jarak ke Setiap Sampel**:
  - $d_1 = \sqrt{50^2 + 50^2} = \sqrt{5.000} \approx 70,71\text{ m}$.
  - $d_2 = \sqrt{50^2 + 50^2} \approx 70,71\text{ m}$.
  - $d_3 = \sqrt{50^2 + 50^2} \approx 70,71\text{ m}$.
  - $d_4 = \sqrt{50^2 + 50^2} \approx 70,71\text{ m}$.
- **Bobot Invers Kuadrat**:
  - Karena jarak ke seluruh 4 sampel identik, maka $w_1' = w_2' = w_3' = w_4'$.
  - Bobot ternormalisasi: $w_1 = w_2 = w_3 = w_4 = 0,25$ ($2.500\text{ bps}$).
- **Kadar Terinterpolasi**:
  $$Z(P) = 0,25 \times (12.000 + 18.000 + 15.000 + 21.000) = 0,25 \times 66.000 = 16.500\text{ ppm}$$
  ($1,65\%$ Ni).

## 4. Sertifikat & Verifikasi
Verifier `InverseDistanceWeightingCertificateVerifier` memvalidasi tiga kondisi matematis:
1. **Batas Convex Hull**: Kadar hasil estimasi wajib berada di dalam rentang sampel:
   $$\min(g_1, \dots, g_n) \le Z(P) \le \max(g_1, \dots, g_n)$$
2. **Konservasi Bobot Total**: $\sum_{i=1}^n \text{WeightBps}_i = 10.000 \pm 2\text{ bps}$.
3. **Integritas Kriptografis Hash**: Hash SHA-256 membungkus koordinat target, kadar terinterpolasi, dan rincian bobot per sampel.

## 5. Kompleksitas & Karakteristik Kinerja
- Kompleksitas Waktu: $O(n)$ terhadap jumlah sampel borehole.
- Kompleksitas Ruang: $O(n)$ untuk tabel bobot.
- Lokasi Kode:
  - Algoritma: `Modules\Mining\Domain\Algorithms\InverseDistanceWeightingInterpolator`
  - Verifier: `Modules\Mining\Domain\Algorithms\InverseDistanceWeightingCertificateVerifier`
  - Unit Test: `tests/Unit/Algorithms/InverseDistanceWeightingTest.php`
