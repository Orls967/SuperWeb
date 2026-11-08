# Formulasi Matematis & Landasan Teoretis: Optimasi Alokasi Kursi EMSR-b (Fare Bucket Yield Optimizer)

## 1. Notasi & Definisi Variabel Matematis

Dalam model pendapatan maskapai multi-tarif:
- $K$: Jumlah kelas tarif terurut ($k = 1, 2, \dots, K$).
- $r_k$: Nilai tarif bersih per kursi untuk kelas $k$, dengan $r_1 > r_2 > \dots > r_K > 0$.
- $D_k$: Variabel acak permintaan calon penumpang untuk kelas $k$.
- $F_k(x) = P(D_k \le x)$: Fungsi distribusi kumulatif (*cumulative distribution function* / CDF) permintaan kelas $k$.
- $f_k(x)$: Fungsi kepadatan probabilitas (*probability density function* / PDF) permintaan kelas $k$.
- $\mu_k = \mathbb{E}[D_k]$: Ekspektasi permintaan kelas $k$.
- $\sigma_k^2 = \text{Var}(D_k)$: Variansi permintaan kelas $k$.
- $C$: Kapasitas kursi total kabin pesawat yang tersedia.
- $S_k$: Tingkat proteksi (*protection level*) untuk kelas tarif agregat $\{1, 2, \dots, k\}$ terhadap kelas tarif yang lebih rendah $\{k+1, \dots, K\}$.
- $BL_k$: Batas pemesanan bersarang (*nested booking limit*) untuk kelas $k$.

---

## 2. Derivasi Teorema Littlewood (Kasus Dua Kelas)

### 2.1. Pernyataan Teorema
Misalkan terdapat dua kelas tarif: Kelas 1 (tarif $r_1$, permintaan $D_1$) dan Kelas 2 (tarif $r_2$, permintaan $D_2$), dengan $r_1 > r_2$. Tiket Kelas 2 dijual terlebih dahulu sebelum Kelas 1.
Tingkat proteksi optimal $s^*$ yang dicadangkan untuk Kelas 1 memenuhi kondisi:
$$P(D_1 > s^*) = \frac{r_2}{r_1}$$
Atau secara ekuivalen:
$$F_1(s^*) = 1 - \frac{r_2}{r_1}$$

### 2.2. Bukti Matematis
Misalkan sistem mencadangkan $s$ kursi untuk Kelas 1. Nilai ekspektasi pendapatan total $R(s)$ dari kapasitas $C$ adalah:
$$R(s) = r_2 \cdot \min(D_2, C - s) + r_1 \cdot \min(D_1, s)$$

Untuk mengevaluasi perubahan marjinal ketika proteksi dinaikkan dari $s$ ke $s+1$:
Kursi ke-$(s+1)$ yang dialihkan dari Kelas 2 ke perlindungan Kelas 1 akan menghasilkan tambahan pendapatan $r_1$ jika dan hanya jika permintaan aktual Kelas 1 memenuhi $D_1 \ge s+1$. Jika $D_1 \le s$, kursi tersebut tidak terjual pada Kelas 1 sehingga menghasilkan pendapatan 0.
Sebaliknya, pengalihan kursi tersebut mengorbankan pendapatan pasti $r_2$ dari calon pembeli Kelas 2 (dengan asumsi permintaan Kelas 2 cukup untuk menyerap kapasitas).

Ekspektasi keuntungan marjinal adalah:
$$\Delta R(s) = r_1 \cdot P(D_1 \ge s+1) - r_2$$

Karena $P(D_1 \ge s+1)$ monoton turun terhadap $s$, nilai optimal tercapai pada titik pergantian tanda di mana $\Delta R(s^*) \approx 0$:
$$r_1 \cdot P(D_1 > s^*) = r_2 \implies P(D_1 > s^*) = \frac{r_2}{r_1}$$
Q.E.D.

---

## 3. Generalisasi EMSR-b Multi-Kelas (Peter Belobaba, 1987)

Pada masalah $K$ kelas tarif, EMSR-b menyederhanakan perhitungan dengan mengagregasikan permintaan kelas $1$ hingga $j$ menjadi satu variabel acak gabungan:
$$S_{1..j} = \sum_{i=1}^j D_i$$

### 3.1. Estimasi Rata-Rata dan Deviasi Standar Agregat
Dengan asumsi independensi stokastik antar-kelas tarif:
$$\hat{\mu}_j = \sum_{i=1}^j \mu_i$$
$$\hat{\sigma}_j = \sqrt{\sum_{i=1}^j \sigma_i^2}$$

### 3.2. Tarif Rata-Rata Tertimbang (Weighted Revenue)
Tarif per kursi rata-rata untuk kelompok agregat $1 \dots j$ dihitung berdasarkan proporsi ekspektasi permintaan masing-masing:
$$\bar{r}_j = \frac{\sum_{i=1}^j r_i \cdot \mu_i}{\hat{\mu}_j}$$

### 3.3. Penentuan Tingkat Proteksi Kumulatif
Menggunakan rasio kritis Littlewood terhadap tarif kelas berikutnya $r_{j+1}$:
$$\alpha_j = \frac{r_{j+1}}{\bar{r}_j}$$

Jika $\alpha_j \ge 1$ (yang mengindikasikan struktur tarif tidak menurun secara monoton), sistem menetapkan $\alpha_j = 0.9999$ untuk menghindari anomali matematis.
Tingkat proteksi dihitung dengan inversi distribusi normal standar:
$$z_j = \Phi^{-1}(1 - \alpha_j)$$
$$S_j = \max\left(0, \left\lceil \hat{\mu}_j + z_j \cdot \hat{\sigma}_j \right\rceil\right)$$

### 3.4. Aturan Nested Booking Limit
Batas pemesanan bersarang menjamin bahwa ketersediaan kursi kelas lebih tinggi selalu mencakup ketersediaan kursi kelas lebih rendah:
$$BL_1 = C$$
$$BL_{j+1} = \max\left(0, C - S_j\right), \quad \forall j \in \{1, 2, \dots, K-1\}$$

Secara hierarkis, berlaku ketidaksamaan:
$$BL_1 \ge BL_2 \ge \dots \ge BL_K$$

---

## 4. Evaluasi Kompleksitas Komputasi & Stabilitas Numerik

- Kompleksitas Waktu: $\mathcal{O}(K)$ di mana $K$ adalah jumlah kelas tarif (umumnya $K \le 20$). Algoritma mengeksekusi dalam sub-milidetik per penerbangan.
- Kompleksitas Ruang: $\mathcal{O}(K)$ untuk menyimpan array batas pemesanan dan tingkat proteksi.
- Penanganan Nilai Ekstrem (*Numerical Stability*):
  Implementasi menggunakan aproksimasi numerik rasional Beasley-Springer-Moro untuk inversi fungsi kumulatif distribusi normal $\Phi^{-1}(p)$ dengan presisi kesalahan relatif $\le 10^{-7}$.

