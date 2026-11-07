# Dokumentasi Algoritma: NeedTimeTruckDispatcher (M2)

## 1. Intuisi Bisnis
Dalam armada angkut tambang terbuka, produktivitas shovel (loader) dan dump truck bergantung pada penugasan dinamis yang meminimalkan total waktu tunggu.
- Shovel yang menganggur (idle) membuang kapasitas alat bernilai investasi tinggi.
- Truk yang mengantre di shovel membakar solar tanpa memindahkan tonase (overtrucked).

Algoritma `NeedTimeTruckDispatcher` menugaskan truk yang siap di dump hopper / titik awal ke shovel yang dapat **memulai pemuatan paling awal** ($\min T_{start}$). Bila terjadi nilai waktu mulai yang sama (tie-break), sistem memprioritaskan shovel yang sudah menganggur paling lama, kemudian ID shovel terkecil.

## 2. Rumus Matematis
Diberikan:
- Truk $k$ siap berangkat dari dump pada waktu $t_k$.
- Shovel $s$ memiliki waktu bebas saat ini $T_{free}(s)$, waktu muat $t_{load}(s)$, dan waktu tempuh kosong $t_{travel}(k, s)$.
- Waktu kedatangan truk di shovel:
  $$T_{arrive}(k, s) = t_k + t_{travel}(k, s)$$
- Waktu mulai pemuatan:
  $$T_{start}(k, s) = \max(T_{arrive}(k, s), T_{free}(s))$$
- Waktu tunggu truk:
  $$W_{truck}(k, s) = \max(0, T_{free}(s) - T_{arrive}(k, s))$$
- Waktu tunggu shovel (idle):
  $$W_{shovel}(k, s) = \max(0, T_{arrive}(k, s) - T_{free}(s))$$
- Kriteria Optimasi:
  $$s^* = \arg\min_s \left( T_{start}(k, s), -W_{shovel}(k, s), \text{id}(s) \right)$$
- Pembaruan status shovel terpilih $s^*$:
  $$T_{free}(s^*) \leftarrow T_{start}(k, s^*) + t_{load}(s^*)$$

### Match Factor (MF)
$$MF = \frac{N_{truck} \times t_{load}}{N_{shovel} \times t_{cycle}}$$
- $MF < 1.0$: Undertrucked (kapasitas shovel lebih besar dari armada truk, shovel menganggur).
- $MF > 1.0$: Overtrucked (kapasitas truk berlebih, truk mengantre di pit).
- $MF = 1.0$: Match factor seimbang.

## 3. Vektor Uji Prompt & Simulasi Langkah Demi Langkah
- **Spesifikasi Shovel**:
  - $S_1$: waktu muat 4 menit, waktu tempuh 10 menit, bebas pada $t = 3$.
  - $S_2$: waktu muat 5 menit, waktu tempuh 6 menit, bebas pada $t = 8$.
- **Waktu Kedatangan Truk di Titik Awal (Dump)**:
  - $T_1$ tiba $t = 0$.
  - $T_2$ tiba $t = 1$.
  - $T_3$ tiba $t = 2$.
  - $T_4$ tiba $t = 4$.

- **Langkah 1 ($T_1$, $t=0$)**:
  - Ke $S_1$: tiba $0 + 10 = 10$, $S_1$ bebas pada $3 \implies T_{start} = \max(10, 3) = 10$.
  - Ke $S_2$: tiba $0 + 6 = 6$, $S_2$ bebas pada $8 \implies T_{start} = \max(6, 8) = 8$.
  - Keputusan: **$T_1 \to S_2$** ($T_{start} = 8$, truk tunggu 2 menit).
  - Update: $S_2$ bebas pada $8 + 5 = 13$.

- **Langkah 2 ($T_2$, $t=1$)**:
  - Ke $S_1$: tiba $1 + 10 = 11$, $S_1$ bebas pada $3 \implies T_{start} = \max(11, 3) = 11$. Shovel idle $11 - 3 = 8$ menit.
  - Ke $S_2$: tiba $1 + 6 = 7$, $S_2$ bebas pada $13 \implies T_{start} = \max(7, 13) = 13$.
  - Keputusan: **$T_2 \to S_1$** ($T_{start} = 11$, shovel $S_1$ idle 8 menit).
  - Update: $S_1$ bebas pada $11 + 4 = 15$.

- **Langkah 3 ($T_3$, $t=2$)**:
  - Ke $S_1$: tiba $2 + 10 = 12$, $S_1$ bebas pada $15 \implies T_{start} = 15$.
  - Ke $S_2$: tiba $2 + 6 = 8$, $S_2$ bebas pada $13 \implies T_{start} = 13$.
  - Keputusan: **$T_3 \to S_2$** ($T_{start} = 13$, truk tunggu 5 menit).
  - Update: $S_2$ bebas pada $13 + 5 = 18$.

- **Langkah 4 ($T_4$, $t=4$)**:
  - Ke $S_1$: tiba $4 + 10 = 14$, $S_1$ bebas pada $15 \implies T_{start} = 15$.
  - Ke $S_2$: tiba $4 + 6 = 10$, $S_2$ bebas pada $18 \implies T_{start} = 18$.
  - Keputusan: **$T_4 \to S_1$** ($T_{start} = 15$, truk tunggu 1 menit).
  - Update: $S_1$ bebas pada $15 + 4 = 19$.

- **Ringkasan Total**:
  - Total waktu tunggu truk: $2 + 0 + 5 + 1 = 8$ menit.
  - Total waktu tunggu shovel: $0 + 8 + 0 + 0 = 8$ menit.
- **Vektor Match Factor**:
  - $N_{truck} = 12$, $N_{shovel} = 3$, $t_{load} = 4$, $t_{cycle} = 36$.
  - $MF = (12 \times 4) / (3 \times 36) = 48 / 108 = 4/9 \approx 0,444$.
  - Status: *Undertrucked* $\to$ Rekomendasi: tambah armada truk.

## 4. Sertifikat & Verifikasi
`NeedTimeTruckDispatcherCertificateVerifier`:
- Invarian 1: Untuk setiap penugasan $(k, s^*)$, tidak ada shovel alternatif $s'$ yang menghasilkan $T_{start}(k, s') < T_{start}(k, s^*)$.
- Invarian 2: Jumlah truk yang ditugaskan $\le$ jumlah armada yang tersedia.
- Invarian 3: Konsistensi total waktu tunggu non-negatif.
- Lokasi Kode:
  - Algoritma: `Modules\Mining\Domain\Algorithms\NeedTimeTruckDispatcher`
  - Verifier: `Modules\Mining\Domain\Algorithms\NeedTimeTruckDispatcherCertificateVerifier`
  - Unit Test: `tests/Unit/Algorithms/NeedTimeTruckDispatcherTest.php`

