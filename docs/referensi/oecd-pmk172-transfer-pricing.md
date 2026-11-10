# Dokumen Referensi Teknis: PMK 172/2023 & OECD Transfer Pricing Guidelines

## 1. Landasan Hukum & Pasal Kunci PMK 172/2023
Peraturan Menteri Keuangan Nomor 172 Tahun 2023 tentang Penerapan Prinsip Kewajaran dan Kelaziman Usaha dalam Transaksi yang Dipengaruhi Hubungan Istimewa mengatur:
- **Pasal 3**: Wajib Pajak yang melakukan transaksi dengan pihak terafiliasi wajib menerapkan Prinsip Kewajaran dan Kelaziman Usaha (*Arm's Length Principle* / ALP).
- **Pasal 8**: Penentuan metode transfer pricing yang paling sesuai (*The Most Appropriate Method*), mempertimbangkan kekuatan dan kelemahan masing-masing metode, ketersediaan data pembanding yang andal, dan tingkat kesebandingan.
- **Pasal 13**: Penggunaan Rentang Kewajaran (*Arm's Length Range*). Bila data pembanding menghasilkan rentang nilai, rentang yang digunakan adalah rentang interkuartil (*interquartile range* / IQR) dengan kuartil bawah ($Q_1$), titik tengah (median), dan kuartil atas ($Q_3$).
- **Pasal 14**: Bila harga atau laba transaksi yang diuji berada di luar rentang interkuartil, penentuan harga wajar disesuaikan ke titik **Median**.

---

## 2. Struktur Data Pembanding & Penyesuaian Kesebandingan
Data pembanding (*comparable data*) disaring melalui beberapa kriteria:
1. **Kriteria Geografis**: Perusahaan beroperasi di wilayah Asia Tenggara atau pasar domestik Indonesia.
2. **Kriteria Aktivitas Usaha (KBLI)**: Memiliki kode klasifikasi lapangan usaha yang sebanding dengan entitas yang diuji (*tested party*).
3. **Kriteria Keuangan**:
   - Memiliki laporan keuangan auditan minimal 3 tahun berturut-turut.
   - Tidak mengalami kerugian operasional terus-menerus.
   - Rasio R&D terhadap penjualan $< 3\%$ jika entitas yang diuji adalah manufaktur kontrak (*contract manufacturer*).

---

## 3. Studi Kasus dan Contoh Numerik Penyesuaian Akhir Tahun (True-Up)

### Kasus: PT Manufaktur Indonesia (SUB_A) menjual suku cadang ke PT Distribusi Regional (SUB_B)
- Kategori Produk: `AUTOMOTIVE_PARTS`
- Metode yang Dipilih: Cost Plus Method (CPM)
- Total Beban Pokok Penjualan (COGS) Sepanjang Tahun: Rp 50.000.000.000
- Total Penjualan Intercompany Tercatat: Rp 52.000.000.000
- Markup Aktual:
  $$\text{ActualMarkup} = \frac{52.000.000.000 - 50.000.000.000}{50.000.000.000} \times 100\% = 4,00\% \quad (400 \text{ bps})$$

### Hasil Studi Benchmark Independen ($n = 10$ Perusahaan Pembanding):
Markup komparabel dalam bps: $[500, 600, 750, 800, 950, 1050, 1200, 1350, 1500, 1800]$
- $Q_1 = 750 \text{ bps}$ (7,50%)
- $\text{Median} = 1000 \text{ bps}$ (10,00%)
- $Q_3 = 1350 \text{ bps}$ (13,50%)

### Analisis Evaluasi:
- Markup aktual $400 \text{ bps} < Q_1 (750 \text{ bps})$. Transaksi dinilai **TIDAK WAJAR (Under-priced)**.
- Penyesuaian True-Up wajib disesuaikan ke titik Median ($1000 \text{ bps} = 10,00\%$):
  $$\text{TargetRevenue} = 50.000.000.000 \times 1,10 = 55.000.000.000 \text{ IDR}$$
  $$\text{AdjustmentAmount} = 55.000.000.000 - 52.000.000.000 = +3.000.000.000 \text{ IDR}$$
- Jurnal Penyesuaian Akhir Tahun:
  - Pada Penjual: Debit Piutang Penyesuaian TP Rp 3.000.000.000, Kredit Pendapatan Penyesuaian TP Rp 3.000.000.000.
  - Pada Pembeli: Debit Beban Penyesuaian TP Rp 3.000.000.000, Kredit Hutang Penyesuaian TP Rp 3.000.000.000.

