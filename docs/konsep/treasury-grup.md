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


---

# BAGIAN 3 — FASE 54: TATA KELOLA KEUANGAN GRUP, ANGGARAN & TUTUP BUKU

## 1. Masalah Bisnis
Pengelolaan keuangan di tingkat grup konglomerasi menghadapi tantangan struktural yang memerlukan sistem terpadu:
1. **Kebocoran Anggaran Akibat Ketiadaan Encumbrance**: Pengadaan barang/jasa yang menerbitkan Purchase Order tanpa mengunci pagu secara otomatis menyebabkan over-budget saat faktur ditagihkan di kemudian hari.
2. **Keterlambatan & Kerentanan Tutup Buku Fiskal**: Penutupan periode bulanan seringkali terhambat oleh diskrepansi antara buku besar dengan subledger pembantu (Piutang, Utang, Persediaan, Aset Tetap). Penutupan buku manual tanpa penguncian otomatis rawan disusupi transaksi susulan (*backdated transactions*).
3. **Risiko Sanksi Perpajakan**: Perhitungan PPN masukan vs keluaran yang lambat dan pelaporan SPT Masa yang tidak terintegrasi berisiko menimbulkan denda bunga perpajakan serta ketidakteraturan nomor seri faktur.
4. **Kecurangan Akibat Konflik Pemisahan Tugas (SoD)**: Penggabungan wewenang berbahaya (seperti pembuat PO yang sekaligus menyetujui, atau staf AP yang mengeksekusi transfer bank) menciptakan celah penipuan tanpa audit trail yang transparan.
5. **Kelemahan Kontrol Internal atas Pelaporan Keuangan (ICFR)**: Ketiadaan pengujian titik kontrol berkala menyebabkan auditor eksternal menemukan defisiensi material saat audit tahunan.
6. **Beban Pengumpulan Bukti Audit Eksternal**: Pengumpulan bukti pembukuan dan dokumen pendukung untuk Kantor Akuntan Publik (KAP) memakan waktu berminggu-minggu tanpa jaminan integritas hash checksum data.

---

## 2. Aktor & Peran (RBAC)
- **`budget_owner`**: Pemilik mata anggaran departemen yang berwenang menyusun usulan pagu, memantau realisasi komitmen, dan mengajukan revisi anggaran.
- **`finance_controller`**: Pejabat pengendali keuangan korporat yang bertugas menyetujui revisi anggaran, mengesahkan dispensasi SoD, mengeksekusi checklist tutup buku bulanan, dan merekonsiliasi kewajiban perpajakan.
- **`legal`**: Penasihat hukum korporat yang memantau kalender kepatuhan regulasi, tenggat waktu lisensi operasional, dan eskalasi risiko litigasi.
- **`auditor`**: Pemeriksa internal dan auditor eksternal independen yang menguji efektivitas matriks kontrol (RCM), menelaah laporan rekonsiliasi, dan mengunduh paket bukti audit.
- **`admin`**: Administrator sistem platform dengan wewenang konfigurasi parameter global.

---

## 3. Konsep Inti & Glosarium Istilah
- **Komitmen Anggaran (*Budget Encumbrance*)**: Pencatatan alokasi pagu anggaran yang dicadangkan segera setelah dokumen komitmen hukum (seperti Purchase Order disetujui) diterbitkan, sebelum kewajiban faktur diakui dalam buku besar.
- **Akun Memo (*Memo Account*)**: Akun pelaporan non-ledger yang melacak mutasi tanpa memengaruhi neraca saldo formal buku besar (D76).
- **Penguncian Periode (*Financial Period Lock*)**: Pembekuan status buku besar untuk periode bulan tertentu setelah seluruh checklist terpenuhi, sehingga menolak segala mutasi baru bertanggal periode tersebut.
- **Ikhtisar Laba Rugi (`Income Summary`)**: Akun kliring ekuitas penampung penutupan seluruh akun nominal pendapatan dan beban pada akhir siklus fiskal.
- **Pemisahan Tugas (*Segregation of Duties / SoD*)**: Kebijakan tata kelola yang memisahkan tanggung jawab penyusunan, persetujuan, pencatatan, dan rekonsiliasi transaksi keuangan untuk memitigasi risiko fraud.
- **Matriks Risiko & Kontrol (*Risk and Control Matrix / RCM*)**: Katalog titik kendali internal yang diuji secara deterministik untuk menjamin keandalan pelaporan keuangan.
- **Nomor Faktur Pajak Gapless**: Penomoran faktur pajak simulasi yang terurut sempurna tanpa celah angka yang hilang sesuai ketentuan administrasi perpajakan.
- **Paket Bukti Audit (*Auditor Data Pack*)**: Bundel arsip kompresi deterministik berisi buku besar, neraca saldo, mutasi rekening, dan daftar aset tetap yang disertai berkas verifikasi `manifest.sha256`.

---

## 4. Invarian Sistem Tata Kelola Finansial Grup
1. **Invarian Pagu Non-Negatif**: Sisa anggaran bebas ($\text{Available} = \text{Allocated} - \text{Encumbered} - \text{Spent}$) dilarang bernilai negatif pada anggaran berkonfigurasi hard-stop.
2. **Invarian Keseimbangan Tutup Buku**: Penutupan akun pendapatan dan beban ke `ef:income_summary:{entitas}:IDR` wajib memenuhi $\Sigma \text{Debit} = \Sigma \text{Kredit}$.
3. **Invarian Penguncian Mutlak**: Seluruh transaksi pembukuan baru pada entitas dan periode yang telah tercatat dalam `ef_financial_period_locks` wajib ditolak dengan pengecualian `PeriodAlreadyLockedException`.
4. **Invarian Rekonsiliasi Subledger Sempurna**: Penutupan buku ditolak apabila selisih antara saldo buku besar dengan agregasi `SubledgerTotalProvider` $\neq 0$.
5. **Invarian Penegakan Four-Eyes**: Operator pembuat anggaran (`created_by`) dilarang menyetujui anggaran miliknya sendiri pada saat transisi ke status `APPROVED`.
6. **Invarian Pencegahan SoD Tanpa Pengecualian**: Penugasan dua peran yang tercantum dalam 12 aturan konflik SoD wajib ditolak kecuali terdapat pengecualian aktif yang belum kedaluwarsa di `ef_sod_exceptions`.
7. **Invarian Keaslian Paket Data Audit**: Setiap berkas CSV dalam arsip paket data audit KAP wajib cocok 100% dengan digest heksadesimal SHA-256 pada `manifest.sha256`.

---

## 5. Alur Nilai & Tabel Penjurnalan Buku Besar (D54-10)

| Kejadian Finansial | Debit | Kredit | Keterangan & Idempotensi |
|---|---|---|---|
| Penutupan Akun Pendapatan | Akun Pendapatan Operasional | `ef:income_summary:{entitas}:IDR` | Mengosongkan saldo kredit nominal pendapatan ke ikhtisar laba rugi. Kunci: `IDEM:EF:CLOSE_REV:{ENT}:{PER}` |
| Penutupan Akun Beban | `ef:income_summary:{entitas}:IDR` | Akun Beban Operasional | Mengosongkan saldo debit nominal beban ke ikhtisar laba rugi. Kunci: `IDEM:EF:CLOSE_EXP:{ENT}:{PER}` |
| Pemindahan Laba Bersih | `ef:income_summary:{entitas}:IDR` | `ef:retained_earnings:{entitas}:IDR` | Memindahkan saldo kredit bersih (laba) ke ekuitas saldo laba ditahan. Kunci: `IDEM:EF:CLOSE_NET:{ENT}:{PER}` |
| Pemindahan Rugi Bersih | `ef:retained_earnings:{entitas}:IDR` | `ef:income_summary:{entitas}:IDR` | Menutup saldo debit bersih (rugi) dengan mengurangi saldo laba ditahan. Kunci: `IDEM:EF:CLOSE_NET:{ENT}:{PER}` |
| Setoran Kurang Bayar PPN | `ef:vat_output_payable:{entitas}:IDR` | `ef:vat_input_receivable:{entitas}:IDR` dan `ef:vat_settlement_clearing:{entitas}:IDR` | Menghilangkan utang PPN keluaran dengan mengkreditkan PPN masukan dan setoran kas negara. Kunci: `IDEM:EF:VAT_SETTLE:{ENT}:{PER}` |
| Pengumuman Deviden Subholding | `ef:dividend_declared:{entitas}:IDR` | `ef:dividend_payable:{entitas}:IDR` | Mengakui kewajiban pembagian dividen anak perusahaan kepada induk holding. Kunci: `IDEM:EF:DIV_DEC:{ENT}:{ID}` |

---

## 6. Katalog Kasus Uji Nyata Fase 54

1. **`EF-CASE-01`**: Reservasi Anggaran Sukses. PO Pengadaan Rp 50.000.000 pada pagu aktif Rp 100.000.000 mengunci pagu komitmen (`RESERVED`), sisa bebas menjadi Rp 50.000.000.
2. **`EF-CASE-02`**: Pelanggaran Pagu Hard-Stop. PO Pengadaan Rp 60.000.000 saat sisa pagu bebas hanya Rp 50.000.000 langsung ditolak dengan `BudgetGuardrailBreachException`.
3. **`EF-CASE-03`**: Likuidasi Komitmen Anggaran. Faktur vendor terbit Rp 48.000.000 atas PO Rp 50.000.000. Status komitmen menjadi `LIQUIDATED`, realisasi tercatat Rp 48.000.000, dan sisa Rp 2.000.000 dilepas kembali ke pagu bebas.
4. **`EF-CASE-04`**: Pelepasan Komitmen Dibatalkan. PO dibatalkan oleh procurement, memanggil `BudgetCommitmentPort::release()`, komitmen menjadi `RELEASED` dan pagu bebas bertambah penuh.
5. **`EF-CASE-05`**: Peringatan Ambang Batas Anggaran. Penarikan pagu yang menyebabkan sisa bebas $< 10\%$ memancarkan event `enterprise.budget.threshold_exceeded.v1`.
6. **`EF-CASE-06`**: Pelanggaran Persetujuan Mandiri Maker-Checker. Pengguna pembuat anggaran mencoba menyetujui anggarannya sendiri, memicu penolakan seketika dengan `BudgetMakerCheckerViolationException`.
7. **`EF-CASE-07`**: Revisi Anggaran Berversi. Anggaran aktif berstatus `ACTIVE` disetujui direvisi: status versi lama diubah menjadi `REVISED` permanen dan dibuat entri anggaran baru versi ke-2.
8. **`EF-CASE-08`**: Rekonsiliasi Subledger Sukses. Seluruh saldo Piutang, Utang, Aset Tetap, dan Persediaan cocok persis dengan buku besar ($\text{Diskrepansi} = 0$).
9. **`EF-CASE-09`**: Penolakan Tutup Buku karena Diskrepansi. Saldo subledger Piutang berselisih Rp 500.000 dengan buku besar, checklist tutup buku gagal dan menghentikan proses penutupan.
10. **`EF-CASE-10`**: Jurnal Penutupan Laba Bersih Fiskal. Entitas membukukan pendapatan Rp 500.000.000 dan beban Rp 350.000.000. Jurnal tutup buku memposting debit `ef:income_summary` Rp 150.000.000 dan kredit `ef:retained_earnings` Rp 150.000.000 secara seimbang.
11. **`EF-CASE-11`**: Pencegahan Mutasi pada Periode Terkunci. Penjurnalan susulan ke bulan yang berstatus `is_locked = true` ditolak keras oleh sistem dengan `PeriodAlreadyLockedException`.
12. **`EF-CASE-12`**: Deteksi & Pencegahan Konflik SoD. Pengguna dengan peran `procurement_buyer` ditugaskan peran `procurement_manager`, sistem melempar `SodViolationException`.
13. **`EF-CASE-13`**: Dispensasi SoD Aktif. Pengguna memiliki pengecualian yang terdaftar di `ef_sod_exceptions` dengan masa berlaku 14 hari ke depan, penugasan berhasil diproses dengan pencatatan log audit.
14. **`EF-CASE-14`**: Penolakan Dispensasi SoD Kedaluwarsa. Dispensasi SoD telah melewati tanggal `expires_at`, sistem otomatis mencabut hak dan memicu tiket remediasi di Kotak Masuk Tugas.
15. **`EF-CASE-15`**: Uji Kontrol Internal RCM & Penerbitan Tugas. Uji 3-Way Match mendeteksi 1 pembayaran tanpa Good Receipt Note, sistem mencatat hasil uji `DEFICIENT` dan memanggil `TaskInboxService::createTask()`.
16. **`EF-CASE-16`**: Rekonsiliasi PPN & Nomor Faktur Gapless. PPN Keluaran Rp 110.000.000 dan PPN Masukan Rp 80.000.000 menghasilkan setoran PPN Rp 30.000.000 dengan urutan nomor faktur tanpa celah.
17. **`EF-CASE-17`**: Ekspor Paket Data Bukti Audit KAP. Command `enterprise:data-pack` menghasilkan arsip ZIP dengan checksum `manifest.sha256` yang valid.
