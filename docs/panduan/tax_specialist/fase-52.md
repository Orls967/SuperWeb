# Panduan Pengguna — Spesialis Perpajakan & Transfer Pricing (Tax Specialist)

**Modul**: `Modules\Intercompany` (Fase 52)  
**Sasaran Pengguna**: Tax Manager Grup, Transfer Pricing Specialist, Tax Compliance Officer  
**Tanggal Efektif**: 2026-11-12

---

## 1. Landasan Regulasi & Prinsip Kewajaran Usaha (PMK 172/2023)

Berdasarkan Peraturan Menteri Keuangan No. 172/2023 tentang Penerapan Prinsip Kewajaran dan Kelaziman Usaha dalam Transaksi yang Dipengaruhi Hubungan Istimewa:
1. **Analisis Komparabilitas**: Transaksi afiliasi wajib diuji kewajarannya terhadap transaksi independen yang sebanding.
2. **Penggunaan Rentang Kewajaran (Arm's Length Range)**: Rentang kewajaran ditentukan menggunakan metode interkuartil ($Q_1$ hingga $Q_3$).
3. **Titik Acuan Penyesuaian**: Jika marjin pihak yang diuji berada di luar rentang interkuartil, penyesuaian harga transfer (*true-up adjustment*) wajib diarahkan tepat ke titik tengah (**Median / $Q_2$**).
4. **Dokumentasi Wajib**: Wajib Pajak yang memenuhi ambang peredaran bruto tertentu wajib menyusun Dokumen Induk (*Master File*) dan Dokumen Lokal (*Local File*) sebelum batas akhir penyampaian SPT Tahunan PPh Badan.

---

## 2. Pengujian Marjin Multivariat & Penyesuaian Modal Kerja

Sistem menyediakan mesin `MultivariateBenchmarkComparator` yang dapat diakses melalui antarmuka web maupun API:
1. **Input Data Keuangan Pihak Teruji**:
   - Pendapatan Usaha (*Revenue*)
   - Beban Pokok Penjualan (*COGS*)
   - Beban Operasional (*Opex*)
   - Saldo Piutang Usaha, Persediaan, dan Utang Usaha
2. **Kalkulasi Indikator Tingkat Laba (Profit Level Indicator / PLI)**:
   - *Operating Margin (OM)*: $\frac{\text{Laba Usaha}}{\text{Pendapatan}} \times 10.000\text{ bps}$
   - *Full Cost Mark-Up (FCMU)*: $\frac{\text{Laba Usaha}}{\text{Total Beban}} \times 10.000\text{ bps}$
   - *Berry Ratio*: $\frac{\text{Laba Kotor}}{\text{Beban Operasional}} \times 10.000\text{ bps}$
3. **Penyesuaian Modal Kerja (Working Capital Adjustment / WCA)**:
   Perbedaan hari piutang, perputaran stok, dan hari utang antara pembanding independen dan pihak teruji diselaraskan menggunakan suku bunga pasar wajar (*prime rate*, default 750 bps / 7,50%):
   $$\text{WCA Net} = (\Delta\text{AR Ratio} + \Delta\text{Inv Ratio} - \Delta\text{AP Ratio}) \times \frac{\text{PrimeRateBps}}{10.000}$$
4. **Hasil Uji Rentang Interkuartil**:
   - Jika $\text{OM Actual} \in [Q_1, Q_3]$: Transaksi dinyatakan **Wajar (Arm's Length Compliant)**. Tidak diperlukan penyesuaian.
   - Jika $\text{OM Actual} < Q_1$: Laba pihak teruji terlalu rendah (indikasi pengalihan laba keluar negeri / sub-arm's length). Sistem menerbitkan instruksi penyesuaian laba ke atas (*upward true-up*) ke titik median.
   - Jika $\text{OM Actual} > Q_3$: Laba pihak teruji di atas rentang wajar. Diterbitkan penyesuaian ke bawah (*downward true-up*) ke titik median.

---

## 3. Penerbitan Paket Audit Kepatuhan (Audit Pack Dossier)

Untuk keperluan pemeriksaan pajak atau pengisian lampiran 3A/3B SPT Tahunan PPh Badan, ekspor paket audit lengkap menggunakan perintah konsol:
```bash
php artisan group:tp-audit-pack --entity=ENT_MANUF_01 --year=2026 --rule=TP-MFG-001
```

Perintah ini secara otomatis menghasilkan dossier audit komprehensif:
1. **Dokumen Local File**:
   - Struktur kepemilikan dan model bisnis entitas lokal.
   - Analisis Fungsi, Aset, dan Risiko (FAR Analysis) dengan skor pembobotan kuantitatif.
   - Justifikasi pemilihan metode penetapan harga transfer terbaik (*The Most Appropriate Method Rule*), memprioritaskan TNMM atau CPM dan mendokumentasikan alasan penolakan metode CUP/RPM/PSM.
2. **Kertas Kerja Pembanding Independen**:
   - Daftar perusahaan pembanding terverifikasi dari basis data OECD/BvD.
   - Tabel kuartil statistik ($Q_1$, Median, $Q_3$, Min, Max, Standar Deviasi).
3. **Rekonsiliasi Fiskal SPT Tahunan**:
   - Menghitung laba komersial sebelum penyesuaian.
   - Koreksi fiskal positif atas penyesuaian true-up transfer pricing.
   - Penghasilan Kena Pajak (PKP) final dan estimasi liabilitas PPh Badan terutang (tarif 22%).

---

## 4. Mekanisme Pemotongan Pajak Bunga Pinjaman & Dividen

### 1. Bunga Pinjaman Antar-Entitas (`group:ic-interest`)
- Pinjaman intra-grup wajib dikenakan bunga pada tingkat wajar (*arm's length interest rate*) yang didokumentasikan dalam TP Doc.
- Perintah akrual bunga:
  ```bash
  php artisan group:ic-interest --period=2026-10
  ```
  Otomatis memotong **PPh Pasal 23 sebesar 15,00% (1.500 bps)** atas penghasilan bunga bruto yang dibayarkan oleh entitas peminjam kepada entitas pemberi pinjaman, serta membentuk bukti potong elektronik.

### 2. Dividen Intra-Grup & NCI
- **Dividen ke Induk Perusahaan Domestik**: Dikecualikan dari objek pemotongan PPh 23 (tarif 0%) berdasarkan Pasal 4 ayat (3) huruf f UU PPh stdd UU HPP, sepanjang dividen bersumber dari cadangan laba ditahan.
- **Dividen ke Pemegang Saham Minoritas Orang Pribadi**: Dikenakan PPh Final Pasal 17 ayat (2c) sebesar **10,00% (1.000 bps)**.
- **Dividen Lintas Batas (PPh 26)**: Tarif umum 20,00%, atau dapat diturunkan sesuai tarif Perjanjian Penghindaran Pajak Berganda (P3B / Tax Treaty) (misal: 10,00% untuk Singapura/Jepang) jika entitas penerima memiliki formulir **DGT-1 / Certificate of Residence (CoR)** yang masih berlaku pada periode pembayaran dividen.

---

## 5. Kalender Kepatuhan Pajak & Siklus Pelaporan Tahunan

Tim spesialis perpajakan grup mematuhi kalender kepatuhan transfer pricing berikut:
- **Tiap Tanggal 10 Bulan Berikutnya**: Penyetoran PPh 23 atas bunga pinjaman intra-grup yang diakrualkan pada bulan sebelumnya.
- **Tiap Tanggal 20 Bulan Berikutnya**: Pelaporan SPT Masa Unifikasi PPh 23/26 melalui sistem e-Bupot DJP.
- **Akhir Bulan Ketiga Pasca-Tutup Tahun (31 Maret)**: Batas akhir ketersediaan Dokumen Induk (*Master File*) dan Dokumen Lokal (*Local File*) tahun pajak bersangkutan.
- **Akhir Bulan Keempat Pasca-Tutup Tahun (30 April)**: Penyampaian SPT Tahunan PPh Badan beserta Lampiran Khusus 3A/3B (Pernyataan Transaksi Hubungan Istimewa) yang memuat ikhtisar Dokumen Penentuan Harga Transfer.
- **Bulan Ke-12 Pasca-Tutup Tahun (31 Desember)**: Penyampaian Laporan per Negara (*Country-by-Country Report / CbCR*) bagi entitas induk konglomerasi yang memenuhi kriteria omzet konsolidasian.

