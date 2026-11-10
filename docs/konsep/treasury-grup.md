# Konsep Arsitektur Keuangan Grup & Perbendaharaan Terpadu

Dokumen ini menjadi rujukan tunggal untuk perancangan domain **Treasury, Intercompany, dan Enterprise Finance** di seluruh ekosistem platform.

---

# BAGIAN 1 — FASE 48: MULTI-CURRENCY & TREASURY KORPORAT

## 1. Masalah Bisnis
Perusahaan holding dan konglomerasi menghadapi risiko finansial kompleks:
1. **Volatilitas Nilai Tukar Valuta Asing**: Transaksi pembelian bahan baku impor (misal dalam USD atau EUR) dan penjualan ekspor menciptakan pos moneter valas yang rentan terhadap fluktuasi kurs mata uang fungsional IDR. Ketiadaan revaluasi berkala dan pelacakan lot valas menyebabkan distorsi laporan laba rugi.
2. **Kesenjangan Mutasi Rekening Bank vs Pembukuan**: Volume transaksi perbankan harian yang masif membutuhkan proses rekonsiliasi yang cepat, akurat, dan dapat diaudit tanpa mengubah data fakta buku besar.
3. **Ketidakpastian Likuiditas Kas**: Tanpa peramalan arus kas (*cash forecasting*) berbasis musiman, grup rentan mengalami krisis likuiditas jangka pendek atau penumpukan dana mengendap yang tidak efisien (*idle cash*).
4. **Risiko Fluktuasi Pasar & Beban Bunga**: Komitmen valas di masa depan membutuhkan instrumen lindung nilai (*hedging*) derivatif yang dipantau nilai pasarnya (*Mark-to-Market*).
5. **Kepatuhan Terhadap Batas Pinjaman Bank (*Covenant Monitoring*)**: Penarikan fasilitas kredit tanpa pengawasan rasio keuangan berisiko memicu wanprestasi (*default*) pinjaman sindikasi bank.
6. **Inefisiensi Likuiditas Anak Perusahaan**: Anak perusahaan dengan kelebihan kas membiarkan dananya mengendap, sementara anak perusahaan lain menarik pinjaman bank berbunga mahal.

---

## 2. Aktor & Peran (RBAC)
- **`treasury`**: Dealer dan manajer perbendaharaan yang bertugas mencatat kurs harian, mengelola kontrak lindung nilai forward, mengeksekusi revaluasi akhir periode, menjalankan rekonsiliasi mutasi bank, dan memantau sweep pooling kas.
- **`auditor`**: Auditor internal dan pemeriksa independen yang memiliki hak baca seluruh posisi kas, log audit revaluasi, verifikasi covenant kredit, dan kepatuhan penyelesaian lindung nilai.
- **`admin`**: Administrator sistem yang bertugas mengonfigurasi batas toleransi dan parameter operasional.

---

## 3. Konsep Inti & Glosarium Istilah
- **Kurs Rasional (`RationalRate`)**: Representasi kurs mata uang sebagai pasangan integer pembilang ($n$) dan penyebut ($d$) positif untuk mencegah masalah kepresisian biner float (misal 15.650 IDR/USD = 15650/1).
- **Lot Valas (`FxLot`)**: Unit pencatatan subledger moneter valuta asing yang menyimpan saldo awal, saldo tersisa, carrying amount IDR historis, dan tanggal perolehan.
- **Untung/Rugi Belum Terealisasi (`Unrealized Gain/Loss`)**: Selisih nilai tercatat pos moneter valas pada akhir periode terhadap kurs penutupan yang belum diselesaikan secara kas.
- **Jurnal Pembalik Otomatis (`Auto-Reversal`)**: Jurnal yang membalik persis pengakuan revaluasi belum terealisasi pada hari pertama periode berikutnya.
- **Untung/Rugi Terealisasi (`Realized Gain/Loss`)**: Selisih kurs nyata yang diakui ketika pos moneter valas dilunasi atau diselesaikan dengan kas.
- **Mark-to-Market (MTM)**: Penyesuaian nilai wajar kontrak derivatif forward terhadap kurs forward pasar terkini.
- **Rekonsiliasi Bank 3-Tahap**: Proses pemadanan mutasi bank dan buku besar melalui tahap: (1) Exact, (2) Toleransi nominal & tanggal, (3) Many-to-One terikat subset.
- **Cash Pooling Zero-Balancing**: Mekanisme otomatisasi penyapuan dana surplus dari rekening anak ke rekening induk holding pada akhir hari kerja melalui instrumen pinjaman antar-perusahaan (*Intercompany Loan*).
- **Rasio Covenant Permil**: Pengukuran rasio Debt-to-Equity (DER) dan Interest Coverage (ICR) menggunakan integer per seribu (permille) tanpa float (misal DER 2,50x = 2500 permil).

---

## 4. Invarian Sistem Perbendaharaan
1. **Invarian Keseimbangan Dua Kaki**: Setiap pencatatan transaksi multi-mata uang wajib memenuhi $\Sigma \text{Debit} = \Sigma \text{Kredit}$ pada mata uang asal dan pada mata uang fungsional IDR secara simultan.
2. **Invarian Kurs Positif**: Pembilang dan penyebut kurs wajib integer positif non-nol ($n > 0, d > 0$). Kurs 0 atau negatif ditolak dengan pengecualian `INVALID_RATE`.
3. **Invarian Konsistensi Pembalik**: Setiap rupiah laba/rugi belum terealisasi yang diposting pada penutupan periode $T$ wajib memiliki jurnal pembalik bertaut `reverse_of` pada awal periode $T+1$.
4. **Invarian Pelunasan Proporsional**: Pada pelunasan sebagian (*partial settlement*) lot valas, carrying amount IDR dialokasikan proporsional dengan metode pembulatan setengah menjauhi nol (*half away from zero*).
5. **Invarian Non-Negatif Pooling**: Sweep kas pooling tidak diperbolehkan menyebabkan saldo kas operasional rekening anak bernilai negatif.
6. **Invarian Penegakan Covenant**: Penarikan fasilitas kredit bank yang mengakibatkan rasio DER melampaui plafon covenant wajib ditolak oleh sistem, kecuali terdapat persetujuan *covenant waiver* resmi dari pejabat berwenang.

---

## 5. Aturan & Sumber Rujukan Resmi
- **PSAK 10 (Revisi 2018) / IAS 21**: Pengaruh Perubahan Nilai Tukar Valuta Asing. Rujukan: Ikatan Akuntan Indonesia (IAI), diakses 2026-11-11, tautan: https://iaiglobal.or.id/standar-akuntansi-keuangan/psak-10.
- **PSAK 71 / IFRS 9**: Instrumen Keuangan (Klasifikasi, Pengukuran, dan Akuntansi Lindung Nilai). Rujukan: IAI, diakses 2026-11-11, tautan: https://iaiglobal.or.id/standar-akuntansi-keuangan/psak-71.
- **Spesifikasi Standar Pesan SWIFT MT940**: Customer Statement Message Standard, format perbankan internasional untuk pelaporan mutasi elektronik. Rujukan: SWIFT Standards Developer Platform, diakses 2026-11-11, tautan: https://www.swift.com/standards/data-standards/mt940.

---

## 6. Batasan Simulasi (Simulation Boundaries)
- **Tingkat S2 (Simulasi Terkendali)**: Kurs valuta asing harian diterbitkan oleh mock engine Bank Indonesia yang menghasilkan pergerakan kurs deterministik. Input manual kurs oleh operator didukung dengan verifikasi hak otorisasi.
- **Data Mutasi Perbankan**: Impor berkas CSV dan MT940 menggunakan format data sintetis berlabel `fixture`. Sistem tidak melakukan koneksi HTTP langsung ke antarmuka Host-to-Host (H2H) atau SWIFT jaringan eksternal.

---

## 7. Detail Matematis Algoritma Bersertifikat D1, D2, D3

### Algoritma D1: Revaluasi Kurs Valas Berbasis Rasional Murni
Perhitungan revaluasi kurs untuk setiap lot moneter valas $i$ dengan saldo tersisa $B_i$ dan kurs penutupan rasional $\frac{n_c}{d_c}$:
$$\text{Revalued IDR}_i = \left\lfloor \frac{B_i \cdot n_c + \lfloor d_c / 2 \rfloor}{d_c} \right\rfloor$$
$$\Delta_i = \text{Revalued IDR}_i - \text{Carrying IDR}_i$$
Jika $\sum \Delta_i > 0$, dibukukan jurnal debit pada Aset Valas dan kredit pada Pendapatan Selisih Kurs Belum Terealisasi. Sebaliknya jika $\sum \Delta_i < 0$, dibukukan debit pada Beban Selisih Kurs dan kredit pada Aset Valas. Setiap perhitungan memproduksi hash SHA-256 dari seluruh payload lot untuk menjamin keaslian matematis (*non-repudiation*).

### Algoritma D2: Pencocokan Rekonsiliasi Bank Tiga Tahap
Himpunan transaksi mutasi bank $\mathcal{B}$ dipadankan terhadap transaksi buku kas $\mathcal{L}$:
1. **Tahap 1 (Eksak)**: $(b, l)$ dicocokkan jika $\text{date}(b) = \text{date}(l)$, $\text{amount}(b) = \text{amount}(l)$, dan $\text{ref}(b) = \text{ref}(l)$.
2. **Tahap 2 (Fuzzy Token & Rentang Tanggal)**: Untuk sisa elemen tak terpadankan, $(b, l)$ dicocokkan jika $|\text{date}(b) - \text{date}(l)| \le \delta_{\text{days}}$, $\text{amount}(b) = \text{amount}(l)$, dan kesamaan token referensi $\ge 80\%$.
3. **Tahap 3 (Subset Jendela Tanggal)**: Pencocokan satu baris bank $b$ terhadap kombinasi transaksi buku kas $\{l_1, \dots, l_k\}$ ($k \le 4$) dalam jendela $\pm 3$ hari sedemikian sehingga $\sum_{j=1}^k \text{amount}(l_j) = \text{amount}(b)$.

### Algoritma D3: Peramalan Arus Kas 13-Minggu Musiman OLS
Menggunakan estimasi tren kuadrat terkecil biasa (*Ordinary Least Squares*):
$$\text{Slope } \beta = \frac{N \sum t Y_t - (\sum t)(\sum Y_t)}{N \sum t^2 - (\sum t)^2}$$
$$\text{Intercept } \alpha = \frac{\sum Y_t - \beta \sum t}{N}$$
Faktor musiman rasional dihitung per periode musiman $s \in \{1, \dots, P\}$:
$$S_s = \frac{\text{Rata-rata Musim } s}{\text{Rata-rata Keseluruhan}}$$
Seluruh operasi menggunakan `bcmath` dengan skala desimal nol untuk menghasilkan angka integer rupiah murni yang tahan terhadap overflow integer 64-bit pada nominal skala korporasi triliunan rupiah.

---

## 8. Katalog Kasus (Kasus Uji Nyata)

1. **`TRS-CASE-01`**: AR USD Naik Kurs (Aset). Saldo 100.000 sen USD, carrying IDR 15.500.000, kurs penutupan 15650/1. Revaluasi menghasilkan laba belum terealisasi Rp 150.000. Jurnal pembalik Rp 150.000 dibuat otomatis pada awal periode berikutnya.
2. **`TRS-CASE-02`**: AP USD Naik Kurs (Liabilitas). Utang 50.000 sen USD, carrying IDR 7.750.000, kurs 15650/1. Nilai wajar naik menjadi Rp 7.825.000, menghasilkan rugi selisih kurs belum terealisasi Rp 75.000.
3. **`TRS-CASE-03`**: Kurs EUR Fraksional. Saldo 12.345 cent EUR pada kurs 346670/20 (setara Rp 17.333,5). Perhitungan integer presisi menghasilkan carrying IDR Rp 2.139.821 (pembulatan menjauhi nol dari 2.139.820,575).
4. **`TRS-CASE-04`**: Kasus Pembulatan Menjauhi Nol Simetris. 1 sen USD pada 15650/1 menghasilkan Rp 157. -1 sen USD menghasilkan -Rp 157.
5. **`TRS-CASE-05`**: Pelunasan Penuh Piutang Valas Realized. Piutang pada kasus 01 dilunasi pada kurs 15700/1 (Rp 15.700.000). Realized gain diakui sebesar Rp 200.000 terhadap carrying historis Rp 15.500.000.
6. **`TRS-CASE-06`**: Pelunasan Parsial Piutang Valas. Pelunasan 40.000 dari 100.000 sen USD pada kurs 15700/1. Carrying proporsional adalah Rp 6.200.000, penerimaan kas Rp 6.280.000, realized gain diakui Rp 80.000. Sisa saldo 60.000 sen USD tetap memiliki carrying Rp 9.300.000.
7. **`TRS-CASE-07`**: Penolakan Kurs Nol. Input kurs penutupan 0/1 memicu pengecualian `INVALID_RATE`.
8. **`TRS-CASE-08`**: Rekonsiliasi Bank Tahap 1 (Exact). Mutasi bank B1 (Rp 1.000.000, ref `INV-1`) cocok persis dengan mutasi buku L1 (Rp 1.000.000, ref `INV-1`).
9. **`TRS-CASE-09`**: Rekonsiliasi Bank Tahap 2 (Toleransi Nominal). Mutasi bank B2 (Rp 2.500.000, ref kosong) berpasangan dengan mutasi buku L2 (Rp 2.500.400, selisih Rp 400 $\le$ batas Rp 500 minor, selisih hari 0 $\le$ 3).
10. **`TRS-CASE-10`**: Rekonsiliasi Bank Tahap 2 (Toleransi Tanggal). Mutasi bank B3 (Rp 750.500 pada hari ke-3) berpasangan dengan mutasi buku L3 (Rp 750.500 pada hari ke-4, selisih 1 hari $\le$ 3 hari).
11. **`TRS-CASE-11`**: Rekonsiliasi Bank Tahap 3 (Many-to-One). Mutasi bank B4 (Rp 3.000.000 pada hari ke-5) cocok dengan 3 mutasi buku L4 (Rp 1.200.000), L5 (Rp 1.000.000), dan L6 (Rp 800.000). Kombinasi alternatif yang melibatkan L7 diabaikan karena urutan leksikografis id.
12. **`TRS-CASE-12`**: Mutasi Bank Tak Terpadankan. Mutasi bank B5 (Rp 99.999) dan mutasi buku L7 (Rp 1.000.000) tidak terpadankan dan masuk ke antrean unmatch bertanggal untuk tindak lanjut investigasi operator.
13. **`TRS-CASE-13`**: Forecast Kas 13 Minggu Musiman Multiplikatif. Riwayat arus kas 12 minggu dengan rata-rata 105 juta dan pola musiman 4 mingguan menghasilkan proyeksi minggu 13–25 dengan total Rp 1.761.000.000.
14. **`TRS-CASE-14`**: Pelanggaran Covenant DER Saat Penarikan Kredit. Fasilitas kredit plafon Rp 10 Miliar dengan batas DER 2,50x (2500 permil). Percobaan penarikan Rp 2 Miliar yang menyebabkan proyeksi DER menjadi 2,65x (2650 permil) langsung ditolak oleh sistem dengan pesan `COVENANT_BREACH`.
15. **`TRS-CASE-15`**: Siklus Lengkap Forward Hedge & Roll-Over. Kontrak forward USD 100.000 dibuat, disetujui melalui ApprovalEngine, dibukukan, aktif, dievaluasi MTM harian, jatuh tempo, lalu diperpanjang (*rolled-over*) ke kontrak forward baru dengan mengaitkan ID kontrak induk.
