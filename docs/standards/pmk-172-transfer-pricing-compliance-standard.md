# Standar Kepatuhan Penetapan Harga Transfer — PMK No. 172/2023 & Pedoman OECD

- **Nomor Standar**: `STD-FIN-TP-0172`
- **Klasifikasi**: Standar Kepatuhan Perpajakan & Tata Kelola Korporasi
- **Otoritas Penerbit**: Direktorat Perpajakan Grup & Kantor Holding CFO
- **Tanggal Efektif**: 2026-11-12
- **Dasar Hukum**:
  1. Peraturan Menteri Keuangan RI No. 172/2023 tentang Penerapan Prinsip Kewajaran dan Kelaziman Usaha dalam Transaksi yang Dipengaruhi Hubungan Istimewa.
  2. Undang-Undang No. 7 Tahun 2021 tentang Harmonisasi Peraturan Perpajakan (UU HPP).
  3. Peraturan Pemerintah No. 55 Tahun 2022 tentang Penyesuaian Pengaturan di Bidang Pajak Penghasilan.
  4. OECD Transfer Pricing Guidelines for Multinational Enterprises and Tax Administrations (Edisi 2022).

---

## 1. Tujuan & Ruang Lingkup Standar

Standar ini menetapkan pedoman operasional dan formulasi matematis baku bagi seluruh entitas anak perusahaan dan afiliasi di bawah naungan grup konglomerasi dalam:
1. Mengidentifikasi seluruh bentuk transaksi afiliasi yang dipengaruhi hubungan istimewa.
2. Melakukan analisis fungsi, aset, dan risiko (FAR Analysis) secara kuantitatif.
3. Memilih metode penentuan harga transfer terbaik (*The Most Appropriate Method*).
4. Melakukan pencarian data pembanding independen (*comparability search*) dan eliminasi data pencilan.
5. Menghitung rentang kewajaran harga transfer (*Arm's Length Range*) menggunakan metode interkuartil.
6. Menghitung dan membukukan penyesuaian laba akhir tahun (*Transfer Pricing True-Up Adjustment*).
7. Menyusun dan mengarsipkan Dokumen Induk (*Master File*) serta Dokumen Lokal (*Local File*).

---

## 2. Definisi Hubungan Istimewa & Transaksi Afiliasi

Sesuai Pasal 18 ayat (4) UU PPh dan PMK 172/2023, hubungan istimewa dianggap ada apabila:
- **Penyertaan Modal**: Wajib Pajak mempunyai penyertaan modal langsung atau tidak langsung paling rendah $25,00\%$ ($2.500\text{ bps}$) pada Wajib Pajak lain.
- **Penguasaan**: Satu atau lebih pihak berada di bawah penguasaan manajemen, teknologi, atau kendali finansial yang sama.
- **Keluarga**: Terdapat hubungan keluarga baik sedarah maupun semenda dalam garis keturunan lurus dan/atau ke samping satu derajat.

### Kategori Transaksi yang Dipengaruhi Hubungan Istimewa
1. **Penjualan / Pembelian Barang Berwujud**: Bahan baku, suku cadang, barang dalam proses, produk jadi, dan barang dagangan.
2. **Penyediaan / Pemanfaatan Jasa Intra-Grup**: Jasa manajemen, jasa teknologi informasi, jasa keuangan, jasa rekayasa teknik, dan jasa pemasaran.
3. **Pemanfaatan Barang Takberwujud (Royalti)**: Lisensi merek dagang, paten teknologi, hak cipta desain, formula rahasia, dan hak waralaba.
4. **Transaksi Finansial Intra-Grup**: Pinjaman antar-perusahaan, perjanjian manajemen kas terpusat (*cash pooling*), jaminan perusahaan (*corporate guarantee*), dan fasilitas lindung nilai (*hedging*).
5. **Pengalihan Harta Tetap**: Pengalihan mesin, peralatan pabrik, gedung, tanah, dan kendaraan operasional.

---

## 3. Metodologi Penetapan Harga Transfer Resmi

PMK 172/2023 mengakui 5 metode penetapan harga transfer:

### 3.1 Metode Perbandingan Harga Antara Pihak yang Independen (CUP)
- **Definisi**: Membandingkan harga transaksi afiliasi dengan harga transaksi barang/jasa identik antara pihak independen dalam kondisi sebanding.
- **Syarat Penerapan**: Tersedia data harga pasar terbuka (*quoted commodity prices*) atau transaksi internal dengan pihak ketiga tanpa perbedaan material.

### 3.2 Metode Harga Penjualan Kembali (Resale Price Method / RPM)
- **Definisi**: Mengurangkan laba kotor wajar (*gross margin*) distributor independen dari harga jual kembali barang ke pihak ketiga.
- **Syarat Penerapan**: Pihak teruji adalah distributor murni (*routine distributor*) yang tidak melakukan pengubahan fisik barang bernilai tinggi.

### 3.3 Metode Biaya-Plus (Cost Plus Method / CPM)
- **Definisi**: Menambahkan persentase mark-up wajar atas beban pokok produksi barang/jasa.
- **Syarat Penerapan**: Pihak teruji adalah manufaktur kontrak (*contract manufacturer*) atau penyedia jasa rutin intra-grup.

### 3.4 Metode Pembagian Laba (Profit Split Method / PSM)
- **Definisi**: Membagi laba gabungan dari transaksi terintegrasi berdasarkan kontribusi nilai relatif masing-masing pihak.
- **Syarat Penerapan**: Transaksi sangat terintegrasi di mana kedua belah pihak sama-sama mengontribusikan aset takberwujud unik bernilai tinggi (*unique and valuable intangibles*).

### 3.5 Metode Laba Bersih Transaksional (Transactional Net Margin Method / TNMM)
- **Definisi**: Menguji marjin laba bersih operasi (*net operating margin*) pihak teruji terhadap basis yang sesuai (pendapatan, biaya, atau aset) relatif terhadap pembanding independen.
- **Syarat Penerapan**: Metode paling fleksibel dan diutamakan bila terdapat variasi klasifikasi beban pokok dan beban usaha antar-pembanding independen.

---

## 4. Formulasi Matematis Rentang Interkuartil & True-Up

### 4.1 Perhitungan Posisi Kuartil Diskrit
Diberikan himpunan marjin operasi pembanding independen terurut $M = [m_1, m_2, \dots, m_n]$ di mana $m_1 \le m_2 \le \dots \le m_n$.
Perhitungan posisi persentil dilakukan secara diskrit terbobot integer:
$$\text{Rank}(P) = \frac{P \times (n - 1)}{100}$$
$$\text{Index} = \lfloor\text{Rank}(P)\rfloor, \quad \text{Remainder} = (P \times (n - 1)) \pmod{100}$$
$$\text{Value}(P) = M[\text{Index}] + \left\lfloor\frac{(M[\text{Index}+1] - M[\text{Index}]) \times \text{Remainder}}{100}\right\rfloor$$
- **Kuartil Pertama ($Q_1$)**: $\text{Value}(25)$
- **Median ($Q_2$)**: $\text{Value}(50)$
- **Kuartil Ketiga ($Q_3$)**: $\text{Value}(75)$
- **Rentang Interkuartil ($\text{IQR}$)**: $Q_3 - Q_1$

### 4.2 Eliminasi Pencilan (Tukey's Fences)
Untuk mencegah distorsi akibat data ekstrem:
$$\text{Pagar Bawah} = Q_1 - \left\lfloor\frac{15 \times \text{IQR}}{10}\right\rfloor$$
$$\text{Pagar Atas} = Q_3 + \left\lfloor\frac{15 \times \text{IQR}}{10}\right\rfloor$$
Data pembanding dengan marjin $m_i < \text{Pagar Bawah}$ atau $m_i > \text{Pagar Atas}$ ditandai sebagai pencilan (*outlier*) dan dikeluarkan dari sampel final.

### 4.3 Penyesuaian Modal Kerja (Working Capital Adjustment / WCA)
Perbedaan saldo modal kerja antara pembanding ($C$) dan pihak teruji ($T$) diselaraskan terhadap marjin operasi menggunakan suku bunga acuan pasar ($i_{\text{prime}}$):
$$\text{WCA}_{\text{AR}} = \left(\frac{\text{AR}_C}{\text{Rev}_C} - \frac{\text{AR}_T}{\text{Rev}_T}\right) \times i_{\text{prime}}$$
$$\text{WCA}_{\text{Inv}} = \left(\frac{\text{Inv}_C}{\text{Rev}_C} - \frac{\text{Inv}_T}{\text{Rev}_T}\right) \times i_{\text{prime}}$$
$$\text{WCA}_{\text{AP}} = \left(\frac{\text{AP}_C}{\text{Rev}_C} - \frac{\text{AP}_T}{\text{Rev}_T}\right) \times i_{\text{prime}}$$
$$\text{Total WCA} = \text{WCA}_{\text{AR}} + \text{WCA}_{\text{Inv}} - \text{WCA}_{\text{AP}}$$
$$\text{Marjin Disesuaikan}_C = \text{Marjin Mentah}_C + \text{Total WCA}$$

### 4.4 Formulasi Penyesuaian Moneter Akhir Tahun (TP True-Up)
Jika marjin operasi aktual pihak teruji $M_T \notin [Q_1, Q_3]$, maka sesuai Pasal 37 PMK 172/2023, penyesuaian dilakukan tepat ke titik **Median ($Q_2$)**:
$$\Delta\text{Margin Bps} = Q_2 - M_T$$
$$\text{Nominal True-Up Minor} = \left\lfloor\frac{\text{Pendapatan Usaha Minor} \times |\Delta\text{Margin Bps}|}{10.000}\right\rfloor$$
- Bila $M_T < Q_1$: Penyesuaian Positif (Menambah Penghasilan Kena Pajak).
- Bila $M_T > Q_3$: Penyesuaian Negatif (Mengurangi Penghasilan Kena Pajak komersial).

---

## 5. Matriks Kewajiban Dokumentasi & Batas Waktu

| Jenis Dokumen | Ketentuan Wajib Penyusunan | Batas Waktu Ketersediaan |
|:---|:---|:---|
| **Dokumen Induk (Master File)** | Peredaran bruto konsolidasi $> \text{Rp } 50\text{ Miliar}$ | 4 bulan setelah akhir tahun pajak |
| **Dokumen Lokal (Local File)** | Peredaran bruto entitas $> \text{Rp } 20\text{ Miliar}$ ATAU transaksi afiliasi barang $> \text{Rp } 5\text{ Miliar}$ | 4 bulan setelah akhir tahun pajak |
| **Laporan per Negara (CbCR)** | Induk konglomerasi dengan omzet konsolidasi $\ge \text{Rp } 11\text{ Triliun}$ | 12 bulan setelah akhir tahun pajak |

---

## 6. Analisis Fungsi DEMPE atas Harta Takberwujud (Intangibles)

Sesuai Bab VI OECD TP Guidelines dan PMK 172/2023 Pasal 14, kepemilikan legal semata atas aset takberwujud (paten, merek dagang, perangkat lunak) tidak memberikan hak eksklusif atas seluruh laba royalti. Alokasi laba wajar wajib ditentukan berdasarkan pelaksanaan fungsi **DEMPE**:
1. **Pengembangan (Development)**: Pihak yang merancang, melakukan penelitian, dan membiayai pembuatan aset takberwujud.
2. **Peningkatan (Enhancement)**: Pihak yang melakukan perbaikan kualitas, modifikasi fitur, dan lokalisasi pasar.
3. **Pemeliharaan (Maintenance)**: Pihak yang melakukan pengujian mutu berkala, registrasi hak kekayaan intelektual, dan pembaruan lisensi.
4. **Perlindungan (Protection)**: Pihak yang menanggung biaya litigasi hukum dan penegakan hukum terhadap pelanggaran paten/merek.
5. **Eksploitasi (Exploitation)**: Pihak yang mengomersialisasikan aset dalam aktivitas manufaktur dan distribusi produk ke pasar.

Entitas anak perusahaan yang hanya bertindak sebagai pemegang lisensi pasif tanpa menjalankan fungsi DEMPE hanya berhak atas marjin rutin dan tidak berhak membebankan royalti berlebih.

---

## 7. Standar Uji Manfaat Jasa Intra-Grup (Intra-Group Services Benefit Test)

Pembebanan biaya jasa intra-grup (*management fees, IT shared services, legal advisory*) wajib lolos dua pengujian kumulatif:
1. **Uji Keberadaan Layanan (Existence Test)**:
   - Harus terdapat bukti konkret bahwa aktivitas jasa benar-benar telah diberikan (laporan kerja, korespondensi email, deliverable proyek, lembar pencatatan waktu kerja / *time-sheets*).
2. **Uji Manfaat Ekonomi (Economic Benefit Test)**:
   - Aktivitas jasa harus memberikan nilai ekonomis nyata atau nilai komersial yang meningkatkan posisi bisnis penerima jasa, atau mengurangi potensi biaya yang seandainya tidak disediakan pihak afiliasi, penerima jasa akan membayar pihak independen atau melakukannya sendiri.
3. **Larangan Pembebanan Biaya Pemegang Saham (Shareholder Costs)**:
   Aktivitas yang semata-mata dilakukan oleh entitas induk untuk kepentingan kepemilikan sahamnya sendiri (misal: biaya RUPS grup, biaya penerbitan laporan keuangan konsolidasi induk, biaya kepatuhan pasar modal induk) dilarang dialokasikan sebagai biaya jasa kepada entitas anak.

---

## 8. Contoh Numerik Kertas Kerja & Formula Penyesuaian True-Up

Misalkan PT Manufaktur Anak (`ENT_MANUF_01`) membukukan kinerja keuangan tahun pajak 2026:
- Pendapatan Usaha: $\text{Rp } 1.500.000.000,00$ ($150.000.000.000\text{ sen}$)
- Beban Operasional Total: $\text{Rp } 1.440.000.000,00$
- Laba Operasi Komersial: $\text{Rp } 60.000.000,00$
- Marjin Operasi Aktual ($M_T$): $\frac{60.000.000}{1.500.000.000} = 4,00\% \quad (400\text{ bps})$

Hasil pencarian pembanding independen menghasilkan rentang interkuartil terverifikasi:
- Kuartil Bawah ($Q_1$): $6,50\% \quad (650\text{ bps})$
- Median ($Q_2$): $8,50\% \quad (850\text{ bps})$
- Kuartil Atas ($Q_3$): $11,00\% \quad (1.100\text{ bps})$

### Evaluasi Kewajaran & Penyesuaian Moneter:
Karena $M_T (400\text{ bps}) < Q_1 (650\text{ bps})$, marjin pihak teruji berada di bawah rentang kewajaran.
Sesuai PMK 172/2023, penyesuaian diarahkan ke titik Median ($Q_2 = 850\text{ bps}$):
$$\Delta\text{Margin} = 850\text{ bps} - 400\text{ bps} = 450\text{ bps} \quad (4,50\%)$$
$$\text{Penyesuaian True-Up Positif} = \text{Rp } 1.500.000.000,00 \times 4,50\% = \text{Rp } 67.500.000,00$$
Koreksi fiskal positif sebesar $\text{Rp } 67.500.000,00$ dimasukkan ke dalam Lampiran 3A SPT Tahunan PPh Badan, menghasilkan tambahan penghasilan kena pajak dan penyesuaian buku besar cermin secara simetris pada entitas lawan transaksi.

