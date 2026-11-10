# Konsep dan Prinsip Akuntansi Intercompany & Konsolidasi Keuangan Grup

## 1. Landasan Teori dan Regulasi
Transaksi antar-entitas dalam grup konglomerasi melibatkan interaksi multi-disiplin antara hukum perusahaan, standar akuntansi keuangan (PSAK / IFRS), dan regulasi perpajakan domestik maupun internasional:
1. **PSAK 65 / IFRS 10 (Laporan Keuangan Konsolidasian)**:
   - Mengharuskan entitas induk menyajikan laporan keuangan konsolidasi yang menggabungkan seluruh aset, liabilitas, ekuitas, pendapatan, dan beban dari entitas induk dan entitas anak di bawah kendali tunggal (*single economic entity*).
   - Seluruh saldo timbal balik (*reciprocal balances*) dan transaksi intra-grup wajib dieliminasi penuh agar laporan keuangan hanya mencerminkan hubungan ekonomi dengan pihak ketiga di luar grup.
2. **PSAK 7 / IAS 24 (Pengungkapan Pihak-Pihak Berelasi)**:
   - Menuntut transparansi hubungan afiliasi, sifat transaksi, saldo terutang, komitmen, dan kebijakan penentuan harga antar pihak berelasi.
3. **PMK 172/2023 & OECD Transfer Pricing Guidelines 2022**:
   - Menegakkan Prinsip Kewajaran dan Kelaziman Usaha (*Arm's Length Principle* / ALP) dalam penentuan harga atau margin transaksi antar-pihak yang memiliki hubungan istimewa.
   - Wajib menggunakan rentang interkuartil (*interquartile range* / IQR) dari data pembanding independen untuk menilai kewajaran margin.
4. **UU Pajak Penghasilan (UU PPh Pasal 23 & 26)**:
   - Kewajiban pemotongan pajak atas penghasilan bunga pinjaman intercompany, dividen, royalti, dan imbalan jasa manajemen antar-perusahaan.

---

## 2. Arsitektur Transaksi Cermin (*Mirror Transactions Engine*)
Transaksi penjualan barang atau penyerahan jasa antar-anak perusahaan tidak boleh dicatat secara parsial atau manual oleh salah satu pihak saja. Modul Intercompany menerapkan konsep transaksi cermin atomik (*atomic mirrored transaction*):
- Ketika Entitas Penjual (mis. PT Manufaktur) menerbitkan Sales Invoice (SI) ke Entitas Pembeli (mis. PT Retail), sistem secara otomatis dan dalam satu unit transaksi basis data atomik:
  1. Memvalidasi kepatuhan harga terhadap aturan transfer pricing terkait.
  2. Menghasilkan Sales Invoice pada pembukuan Penjual dengan nomor gapless resmi.
  3. Menghasilkan Purchase Bill pada pembukuan Pembeli dengan referensi silang yang sama.
  4. Memposting ayat jurnal dua kaki di buku besar Penjual (Debit Piutang Intercompany, Kredit Pendapatan).
  5. Memposting ayat jurnal dua kaki di buku besar Pembeli (Debit Beban Pokok/Persediaan, Kredit Hutang Intercompany).
- Bila salah satu posting gagal atau terjadi inkonsistensi saldo, transaksi dibatalkan sepenuhnya (*rollback* penuh), menjamin saldo timbal balik selalu simetris 100%.

---

## 3. Penentuan Harga Transfer & Analisis Interkuartil (PMK 172/2023)
Penentuan harga wajar menggunakan empat metode transfer pricing yang diakui secara internasional:
1. **Comparable Uncontrolled Price (CUP)**: Membandingkan harga barang/jasa dalam transaksi afiliasi dengan harga dalam transaksi independen yang sebanding.
2. **Cost Plus Method (CPM)**: Menambahkan markup laba kotor yang wajar di atas biaya produksi langsung dan tidak langsung.
3. **Resale Price Method (RPM)**: Mengurangkan margin laba kotor wajar dari harga jual kembali barang ke pihak ketiga independen.
4. **Transactional Net Margin Method (TNMM)**: Menguji margin laba operasi bersih relatif terhadap basis yang tepat (biaya, penjualan, atau aset).

### Formulasi Statistik Interkuartil (IQR)
Diberikan himpunan data margin operasional perusahaan pembanding independen terurut naik:
$$X = \{x_1, x_2, x_3, \dots, x_n\} \quad \text{dengan} \quad x_1 \le x_2 \le \dots \le x_n$$
1. Kuartil Bawah ($Q_1$):
   $$\text{Posisi } Q_1 = \lfloor 0.25 \times (n + 1) \rfloor$$
2. Median ($Q_2$):
   $$\text{Posisi Median} = \lfloor 0.50 \times (n + 1) \rfloor$$
3. Kuartil Atas ($Q_3$):
   $$\text{Posisi } Q_3 = \lfloor 0.75 \times (n + 1) \rfloor$$

**Aturan Penetapan Kepatuhan**:
- Jika margin transaksi aktual $M \in [Q_1, Q_3]$, maka harga transaksi dinyatakan memenuhi Prinsip Kewajaran dan Kelaziman Usaha.
- Jika $M < Q_1$ atau $M > Q_3$, maka harga transfer dinilai tidak wajar dan memicu penyesuaian akhir tahun (*year-end true-up adjustment*) ke titik Median ($Q_2$).

---

## 4. Pinjaman Antar-Perusahaan & Pemotongan Pajak Bunga (PPh 23/26)
Pengelolaan fasilitas pinjaman modal kerja intra-kelompok usaha (*intercompany loans*) wajib menerapkan suku bunga arm's length (misal: Suku Bunga Acuan BI-Rate + Credit Spread wajar berdasarkan rating entitas debitur).
- **Metode Bunga Efektif**: Akrual bunga dihitung proporsional terhadap sisa saldo pokok pinjaman (*unpaid principal balance*) dan jumlah hari aktual per basis tahun 360 hari.
- **Pemotongan Pajak Otomatis**:
  - Untuk debitur dan kreditur domestik Indonesia, debitur memotong PPh Pasal 23 sebesar 15% dari jumlah bruto bunga.
  - Untuk kreditur luar negeri, debitur memotong PPh Pasal 26 sebesar 20% (atau tarif sesuai Perjanjian Penghindaran Pajak Berganda / P3B yang berlaku).
  - Kas yang disetor debitur ke rekening kreditur adalah bunga neto setelah pajak ($85\%$), sedangkan sisa $15\%$ dibukukan sebagai kewajiban pajak pemotongan untuk disetor ke kas negara.
  - Kreditur membukukan $85\%$ kas masuk dan $15\%$ uang muka PPh Pasal 23 yang dapat dikreditkan pada SPT Tahunan PPh Badan.

---

## 5. Skema Pembagian Biaya Bersama (*Cost Sharing / Management Fee*)
Layanan terpusat (*shared services*) yang disediakan oleh entitas induk (misalnya layanan TI grup, legal, audit internal, HR, dan manajemen eksekutif) dialokasikan ke anak-anak perusahaan menggunakan basis alokasi yang objektif dan konsisten:
1. **Basis Headcount**: Alokasi biaya sistem informasi dan HR berdasarkan proporsi jumlah karyawan aktif tiap anak perusahaan.
2. **Basis Omzet (Revenue Ratio)**: Alokasi biaya manajemen umum dan branding grup berdasarkan proporsi pendapatan kotor tahunan masing-masing anak perusahaan.
3. Sesuai regulasi perpajakan, penyedia layanan menambahkan markup wajar (mis. 5% - 8%) yang didukung oleh analisis manfaat ekonomi langsung bagi entitas penerima biaya (*benefit test*).

---

## 6. Mesin Eliminasi Konsolidasi & Laba Persediaan Belum Terealisasi
Proses konsolidasi keuangan bulanan menjalankan tahapan eliminasi terotomatisasi:
1. **Eliminasi Saldo Piutang-Hutang (Reciprocal Balances)**:
   - Menghapus saldo Piutang Intercompany dan Hutang Intercompany antar seluruh anak perusahaan.
   - Syarat: Saldo piutang penjual wajib sama persis dengan saldo hutang pembeli. Bila ada selisih, algoritma D5 mendeteksi transaksi transit (*in-transit remittances* atau *goods in transit*).
2. **Eliminasi Penjualan-Pembelian Intra-Grup**:
   - Menghapus pos Pendapatan Penjualan Intercompany di penjual dan Beban Pokok Penjualan / Pembelian di pembeli, sehingga omzet grup tidak berlipat ganda (*zero double counting*).
3. **Eliminasi Laba Belum Terealisasi (*Unrealized Inventory Profit*)**:
   - Jika barang yang dibeli antar-perusahaan masih tersimpan sebagai persediaan di gudang pembeli pada tanggal neraca, margin keuntungan yang dibukukan oleh penjual belum sah terealisasi ke pihak luar grup.
   - Nilai persediaan pembeli diturunkan kembali ke biaya perolehan awal grup melalui akun tandingan cadangan eliminasi laba persediaan.

---

## 7. Translasi Laporan Keuangan Valas Anak & Ekuitas FCTR
Untuk anak perusahaan dengan mata uang fungsional selain IDR (mis. USD di entitas perdagangan internasional):
- Neraca ditranslasikan menggunakan kurs penutupan (*closing rate*) per tanggal laporan.
- Laba rugi ditranslasikan menggunakan kurs rata-rata (*average rate*) sepanjang periode.
- Selisih matematis translasi dibukukan langsung ke pos Ekuitas: *Foreign Currency Translation Reserve* (FCTR) tanpa mempengaruhi laba rugi tahun berjalan, sesuai PSAK 10 / IAS 21.

---

## 8. Kepentingan Non-Pengendali (*Non-Controlling Interest* - NCI)
Pada anak perusahaan yang sahamnya dimiliki bersama pihak ketiga:
- Porsi laba bersih anak yang menjadi hak pemegang saham minoritas dialokasikan ke akun beban bagi hasil NCI di laporan laba rugi konsolidasi dan penambah ekuitas NCI di neraca.
- Ketika anak membagikan dividen kas, porsi dividen minoritas dibukukan sebagai pengurang ekuitas NCI dan kas keluar ke pihak ketiga.

