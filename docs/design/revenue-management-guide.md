# Panduan Teknis Mendalam: Manajemen Hasil & Optimalisasi Pendapatan Penerbangan (Yield & Revenue Management)

## 1. Pendahuluan & Filosofi Bisnis

Manajemen hasil (*Yield Management*) dalam aviasi modern merupakan disiplin ilmu peramalan permintaan dan alokasi inventaris kapasitas tetap yang memiliki karakteristik *perishable* (kursi pesawat yang tidak terjual pada saat pintu pesawat ditutup kehilangan seluruh nilai ekonominya seketika). Sasaran utamanya bukan sekadar memaksimalkan faktor keterisian kabin (*seat load factor*), melainkan memaksimalkan pendapatan total per kilometer kursi yang tersedia (*Revenue per Available Seat Kilometer* atau RASK).

Untuk mencapai tujuan tersebut, pendekatan statis (di mana harga ditetapkan tetap sepanjang masa penjualan) telah ditinggalkan dan digantikan oleh manajemen hasil dinamis multi-kelas (*dynamic multi-fare class revenue management*).

---

## 2. Struktur Kelas Tarif (Fare Hierarchy & RBD)

Dalam sistem reservasi penerbangan berbasis standar IATA, kabin fisik dibagi menjadi beberapa kelompok layanan, yang kemudian dipecah lagi menjadi *Reservation Booking Designators* (RBD) atau *fare buckets*.

### 2.1. Hirarki Kabin & Sub-Kelas
1. **First Class (F, A)**:
   - F: Tarif penuh tanpa syarat pembatasan, pengembalian dana 100%, akses lounge eksekutif, bagasi 40 kg.
   - A: Tarif diskon first class atau penawaran korporat tingkat tinggi.
2. **Business Class (J, C, D, I, Z)**:
   - J: Full-fare business class, fleksibilitas penuh untuk perubahan jadwal tanpa denda.
   - C: Standar business class komersial.
   - D: Promosi diskon business class dengan penalti pembatalan moderat.
   - I: Kuota kursi penukaran miles loyalitas (*award ticket*).
   - Z: Tiket promosi business class *non-refundable*.
3. **Premium Economy Class (W, S, Y+)**:
   - Ruang kaki ekstra (*extra legroom*), prioritas *boarding*, layanan makanan ditingkatkan.
4. **Economy Class (Y, B, M, H, Q, V, K, L, T, N, E, G)**:
   - Y: Full-fare unrestricted economy (tolok ukur perhitungan tarif prorata IATA).
   - B, M: Tarif ekonomi fleksibel tinggi.
   - H, Q: Tarif ekonomi komersial menengah.
   - V, K: Tarif ekonomi promosi terbatas.
   - L, T: Tarif diskon tinggi, tidak dapat di-refund, denda reschedule tinggi.
   - N, E: Tarif promosi flash sale khusus online.
   - G: Tiket pemesanan rombongan (*group reservation*).

---

## 3. Formulasi Matematis Algoritma A6 (FareBucketYieldOptimizer)

### 3.1. Model Stokastik Permintaan
Permintaan untuk masing-masing kelas tarif $i \in \{1, 2, \dots, n\}$ dimodelkan sebagai variabel acak independen $X_i$ yang terdistribusi normal dengan rata-rata $\mu_i$ dan deviasi standar $\sigma_i$:
$$X_i \sim \mathcal{N}(\mu_i, \sigma_i^2)$$

Dengan tarif bersih setelah dikurangi pajak dan biaya bandara yang dinyatakan sebagai $r_1 > r_2 > \dots > r_n$.

### 3.2. Formulasi Littlewood untuk Dua Kelas Tarif
Pada kasus dasar dua kelas tarif (Kelas 1 berharga tinggi $r_1$ dan Kelas 2 berharga rendah $r_2$), kursi dialokasikan kepada calon penumpang Kelas 2 hanya jika pendapatan pasti dari Kelas 2 lebih tinggi atau sama dengan ekspektasi pendapatan marjinal dari kursi tersebut apabila disimpan untuk penumpang Kelas 1 di masa mendatang.

Ekspektasi nilai marjinal kursi ke-$s$ untuk Kelas 1 adalah:
$$\text{EMSR}(s) = r_1 \cdot P(X_1 \ge s)$$

Kondisi optimal tercapai ketika:
$$r_1 \cdot P(X_1 \ge s^*) = r_2$$
$$P(X_1 \ge s^*) = \frac{r_2}{r_1}$$
$$P(X_1 < s^*) = 1 - \frac{r_2}{r_1}$$

Dengan fungsi kumulatif distribusi normal standar $\Phi$, tingkat proteksi optimal untuk Kelas 1 adalah:
$$s^* = \mu_1 + \Phi^{-1}\left(1 - \frac{r_2}{r_1}\right) \cdot \sigma_1$$

### 3.3. Generalisasi EMSR-b Multi-Kelas
Untuk kasus $n$ kelas tarif, EMSR-b mengagregasikan seluruh permintaan kelas yang lebih tinggi menjadi satu kelas gabungan fiktif. Ketika menentukan batas perlindungan untuk kelas $j$ terhadap kelas yang lebih rendah $j+1$:

1. Agregasi Rata-Rata:
   $$\hat{\mu}_j = \sum_{k=1}^j \mu_k$$
2. Agregasi Variansi:
   $$\hat{\sigma}_j = \sqrt{\sum_{k=1}^j \sigma_k^2}$$
3. Tarif Rata-Rata Tertimbang:
   $$\hat{r}_j = \frac{\sum_{k=1}^j r_k \cdot \mu_k}{\hat{\mu}_j}$$
4. Rasio Kritis Littlewood:
   $$\alpha_j = \frac{r_{j+1}}{\hat{r}_j}$$
5. Perlindungan Kursi Kumulatif ($S_j$):
   $$S_j = \hat{\mu}_j + \Phi^{-1}(1 - \alpha_j) \cdot \hat{\sigma}_j$$
   Bila $\alpha_j \ge 1$ (tidak realistis dalam hirarki yang valid), $S_j = \hat{\mu}_j$.
6. Batas Pemesanan Bersarang (*Nested Booking Limit* $BL_{j+1}$):
   Dengan sisa kapasitas fisik kabin $C_{rem}$:
   $$BL_{j+1} = \max\left(0, \left\lfloor C_{rem} - S_j \right\rfloor\right)$$

---

## 4. Mekanisme Dynamic Overbooking & No-Show Compensation

Dalam kenyataan operasional, persentase tertentu dari penumpang yang telah memegang tiket terkonfirmasi tidak hadir di gerbang keberangkatan (*no-show*), baik karena pembatalan di menit-menit akhir, kemacetan perjalanan darat, atau kegagalan penerbangan lanjutan.

### 4.1. Model Probabilitas Kehadiran Binomial
Jika terdapat $B$ tiket yang dijual untuk kabin berkapasitas $C$, dan setiap penumpang memiliki probabilitas hadir independen $p$ (di mana $q = 1 - p$ adalah probabilitas *no-show*), jumlah penumpang yang hadir $Y$ mengikuti distribusi binomial:
$$P(Y = k) = \binom{B}{k} p^k (1-p)^{B-k}$$

### 4.2. Penentuan Rasio Overbooking Optimal
Jika biaya penolakan terbang akibat kursi tidak cukup (*denied boarding cost*) per penumpang adalah $D$ (termasuk denda kompensasi tunai PM 89/2015, akomodasi hotel, dan hilangnya reputasi), dan keuntungan marjinal dari penjualan tiket tambahan adalah $M$, maka jumlah tiket optimal $B^*$ memenuhi kondisi:
$$P(Y > C) \le \frac{M}{M + D}$$

Algoritma A6 menghitung batas aman overbooking maksimum (umumnya dibatasi pada plafon 103%–108% dari kapasitas fisik pesawat) dengan evaluasi harian deviasi historis rute terkait.

---

## 5. Integrasi Database & Audit Trail Transaksi Yield

Setiap kalkulasi hasil disimpan pada tabel `avi_yield_buckets` dengan metadata lengkap:
- `flight_id`: Referensi identitas penerbangan fisik.
- `bucket_code`: Kode RBD (e.g. 'Y', 'B', 'M', 'H', 'Q', 'V').
- `base_price_minor_unit`: Harga dasar tarif dalam satuan terkecil (sen/rupiah).
- `allocated_capacity`: Jumlah kursi maksimum yang diizinkan untuk bucket ini.
- `sold_count`: Jumlah tiket yang telah diterbitkan pada bucket ini.
- `protection_level`: Batas proteksi kursi yang dihitung oleh EMSR-b.
- `calculated_at`: Stempel waktu komputasi.
- `algorithm_hash`: Sertifikat hash SHA-256 yang memvalidasi integritas eksekusi algoritma.

---

## 6. Protokol Rekonsiliasi & Anti-Korupsi Data
Buku besar yield mencatat setiap perubahan status penjualan tiket secara riil. Command `avi:yield-rebalance` dijalankan secara terjadwal untuk memeriksa anomali:
1. Terjualnya tiket di bawah batas proteksi (*protection leakage*).
2. Inkonsistensi nested availability ($BL_{j+1} > BL_j$).
3. Penjualan melebihi batas overbooking yang telah disertifikasi.
Bila anomali terdeteksi, event peringatan diterbitkan dan bucket terkait dibekukan sementara (*hard lock*) hingga verifikasi manual atau rebalancing selesai.

