# Panduan Operasional Perbendaharaan & Valuta Asing (Fase 48)

**Modul**: `Treasury` (`Modules\Treasury`)  
**Sasaran Pengguna**: Dealer Treasury, Manajer Perbendaharaan, Pengendali Keuangan Grup (`finance_controller`), dan Auditor Internal (`auditor`)  
**Standar Akuntansi Acuan**: PSAK 10 (Revisi 2018) / IAS 21, PSAK 71 / IFRS 9, Standar SWIFT MT940

---

## 1. Gambaran Umum Operasional Meja Perbendaharaan

Modul **Treasury & Multi-Currency** mengelola siklus hidup penuh likuiditas grup konglomerasi lintas entitas dan lintas mata uang. Setiap operasi dirancang dengan tiga prinsip utama:
1. **Aritmetika Rasional Bulat Tanpa Float**: Seluruh nilai kurs disimpan sebagai pasangan pembilang (`rate_numerator`) dan penyebut (`rate_denominator`) bulat positif melalui objek nilai `RationalRate`. Seluruh nilai nominal uang disimpan dalam satuan terkecil (*minor units*, seperti sen USD atau rupiah penuh IDR) bertipe integer 64-bit.
2. **Keseimbangan Buku Besar Satu Tanda (*Single-Sign Double-Entry*)**: Setiap mutasi perbendaharaan yang berdampak pada posisi keuangan grup wajib diposting melalui `TreasuryLedgerPoster` ke `LedgerService` dengan konvensi Debit negatif (`-`), Kredit positif (`+`), dan $\Sigma = 0$ per transaksi serta per kode mata uang.
3. **Pemisahan Wewenang Empat Mata (*Four-Eyes Segregation of Duties*)**: Pembuatan draf kontrak lindung nilai (`ForwardContract`), pengajuan keringanan rasio utang (`CovenantWaiver`), dan pengajuan voucher kas kecil (`PettyCashVoucher`) wajib disetujui oleh pejabat yang berbeda dari pembuatnya (`creator_id != approver_id`).

---

## 2. Pengelolaan Master Mata Uang & Kurs Rasional Harian (48.1)

### 2.1 Pendaftaran Mata Uang ISO 4217
Mata uang didaftarkan pada tabel `trs_currencies` dengan atribut:
- `code`: Kode 3 huruf ISO 4217 (`IDR`, `USD`, `EUR`, `SGD`, `JPY`, `GBP`, `AUD`, `CNY`).
- `minor_units`: Jumlah desimal unit terkecil (`0` untuk IDR dan JPY; `2` untuk USD, EUR, SGD, GBP, AUD, CNY).
- `is_active`: Status aktif perdagangan mata uang.

### 2.2 Pencatatan Kurs Harian Ber-versi (`trs_exchange_rates`)
Dealer mencatat kurs harian dari sumber resmi (kurs tengah Bank Indonesia / JISDOR, kurs spot pasar, atau kurs pajak Kementerian Keuangan):
- **Tipe Kurs (`rate_type`)**:
  - `spot`: Kurs transaksi harian untuk konversi kas dan pencatatan awal lot valas.
  - `closing`: Kurs penutupan akhir periode (hari bursa terakhir bulan buku) untuk revaluasi PSAK 10.
  - `tax`: Kurs Keputusan Menteri Keuangan (KMK) untuk pelaporan perpajakan.
- **Representasi Rasional**:
  - Kurs Rp 15.650 per 1 USD disimpan sebagai `rate_numerator = 15650`, `rate_denominator = 1` (atau dalam skala $10^6$ sebagai `15650000000 / 1000000`).
  - Kurs fraksional seperti Rp 17.333,50 per 1 EUR disimpan secara eksak sebagai `rate_numerator = 34667`, `rate_denominator = 2`.
- **Aturan Validasi**:
  - Pembilang dan penyebut wajib bilangan bulat $\ge 1$. Nilai `0` atau negatif ditolak seketika dengan galat `INVALID_RATE`.

---

## 3. Transaksi Multi-Currency & Subledger Lot Valas (48.2)

### 3.1 Konversi & Posting Multi-Mata Uang
Saat perusahaan melakukan transfer atau pembayaran dalam mata uang asing melalui endpoint `POST /api/treasury/fx-transfers`:
1. Header HTTP `Idempotency-Key` wajib disertakan. Permintaan tanpa header ini ditolak dengan kode HTTP `422 Unprocessable Entity`.
2. `TreasuryService::postMultiCurrency()` mengonversi nominal valas ke nilai ekuivalen fungsional IDR menggunakan kurs `spot` tanggal transaksi.
3. Entri buku besar diposting secara atomik dengan kunci idempotensi deterministik.

### 3.2 Pelacakan Subledger `trs_fx_lots`
Setiap posisi moneter valuta asing terbuka (kas valas, piutang usaha ekspor, utang usaha impor) dicatat sebagai lot di tabel `trs_fx_lots`:
- Menyimpan `original_amount`, `remaining_amount`, kurs perolehan historis (`original_rate_num / original_rate_den`), serta nilai tercatat buku saat ini (`current_carrying_idr`).
- Saat terjadi pelunasan parsial atau penuh melalui `TreasuryService::settleFxLot()`:
  - Nilai tercatat buku yang diselesaikan dihitung secara proporsional dengan pembulatan setengah menjauhi nol (*round half away from zero*).
  - Selisih antara nilai kas realisasi pada kurs penyelesaian (`settlementRate`) terhadap nilai tercatat yang dialokasikan diakui sebagai laba/rugi selisih kurs terealisasi (*Realized FX Gain/Loss*) pada akun `trs:fx_realized_gain_loss:IDR` dengan akun lawan `trs:fx_settlement_clearing:IDR`.
  - Ketika `remaining_amount` mencapai `0`, status lot berubah menjadi `exhausted` dan `current_carrying_idr` wajib bernilai `0`.

### 3.3 Agregasi Eksposur Valas Lintas-Modul (`FxMonetaryItemFeed`)
Selain lot yang dibuat langsung di modul Treasury, sistem secara otomatis menarik pos moneter valas terbuka dari modul lain yang mengimplementasikan kontrak `Modules\Treasury\Contracts\FxMonetaryItemFeed`:
- **Modul Procurement (`ProcurementFxMonetaryItemFeed`)**: Menarik Purchase Order impor berdenominasi valas yang berstatus `APPROVED`, `ISSUED`, `PARTIALLY_DELIVERED`, atau `INVOICED`.
- **Modul Trade (`TradeFxMonetaryItemFeed`)**: Menarik pesanan impor (`trd_import_orders`) sebagai pos liabilitas moneter dan pengapalan ekspor (`trd_export_shipments`) sebagai pos aset moneter.
- Layanan `TreasuryCovenantGovernanceService::aggregateAndRevalueCrossModuleFeeds()` menyinkronkan seluruh feed tersebut ke dalam `trs_fx_lots` sebelum menjalankan perhitungan revaluasi akhir bulan.

---

## 4. Revaluasi Kurs Akhir Periode & Pembalik Otomatis (48.3)

### 4.1 Eksekusi Revaluasi Akhir Bulan (Algoritma D1)
Pada penutupan bulan buku, manajer Treasury menjalankan perintah:
```bash
php artisan treasury:fx-revalue --period=2026-11 --currency=USD
```
atau mengeksekusi batch seluruh mata uang melalui `RunFxRevaluationAction`:
1. `FxRevaluationCalculator` (Algoritma D1) menghitung nilai wajar IDR baru untuk setiap mata uang dan lot aktif menggunakan kurs penutupan (`closing` / `spot` akhir bulan).
2. Menghasilkan sertifikat kriptografis `FxRevaluationCertificate` ber-hash SHA-256 yang dapat diverifikasi ulang oleh `FxRevaluationCertificateVerifier`.
3. Memosting jurnal penyesuaian ke buku besar melalui `TreasuryLedgerPoster`:
   - **Aset Valas Menguat (Untung Belum Terealisasi)**:
     - Debit `trs:fx_reval_asset_adj:IDR`
     - Kredit `trs:fx_unrealized_gain_loss:IDR`
   - **Aset Valas Melemah (Rugi Belum Terealisasi)**:
     - Debit `trs:fx_unrealized_gain_loss:IDR`
     - Kredit `trs:fx_reval_asset_adj:IDR`
   - **Liabilitas Valas (Kurs Naik -> Rugi; Kurs Turun -> Untung)**:
     - Memakai akun penyesuaian `trs:fx_reval_liability_adj:IDR` melalui `postFxLiabilityRevaluation()`.
4. Memancarkan event domain resmi `treasury.revaluation.posted.v1` (`TreasuryRevaluationPostedEvent`) ke `EventSpine` dan dispatcher aplikasi.

### 4.2 Jurnal Pembalik Otomatis Awal Periode Baru
Pada tanggal 1 bulan berikutnya pukul 00:05, penjadwal menjalankan:
```bash
php artisan treasury:reverse-revaluations --period=2026-11
```
- `ReverseFxRevaluationAction` membuat rekaman pembalik di `trs_revaluations` dengan `reverse_of` menunjuk ke ID revaluasi bulan sebelumnya dan `gain_loss_idr` bertanda kebalikan persis (`-gain_loss_idr`).
- Memosting jurnal pembalik ke `LedgerService` dengan kunci idempotensi `IDEM:TRS:REVAL_REV:{periodeBaru}:{mataUang}:{idAsal}`.
- Menandai rekaman revaluasi asal dengan `is_reversed = true` dan stempel waktu `reversed_at`.

---

## 5. Rekonsiliasi Bank 3-Tahap, Impor MT940 & Kas Kecil Imprest (48.4)

### 5.1 Impor Mutasi Rekening Koran (`treasury:import-statement`)
Mutasi rekening koran diimpor menggunakan perintah:
```bash
php artisan treasury:import-statement --account=ACC-HOLDING-BCA --file=/path/to/mt940.txt --format=MT940
```
- `BankStatementImportService` mendukung parsing tag standar SWIFT MT940 (`:20:` referensi pernyataan, `:25:` nomor rekening, `:60F:` saldo awal, `:61:` baris mutasi nilai & tanggal, `:86:` narasi deskripsi, `:62F:` saldo akhir) maupun berkas CSV.
- Setiap batch dicatat pada `trs_statement_import_batches` dengan `payload_hash` SHA-256. Jika file yang sama diimpor dua kali, impor kedua dilewati secara idempoten (`is_duplicate_batch = true`).

### 5.2 Pemadanan Otomatis 3-Tahap (`treasury:reconcile`)
Perintah harian:
```bash
php artisan treasury:reconcile --account=ACC-HOLDING-BCA
```
menjalankan `BankReconciliationMatcher` (Algoritma D2):
1. **Tahap 1 — Exact Match**: Mencocokkan baris mutasi bank dan baris buku besar yang memiliki nominal identik dan nomor referensi non-kosong yang sama persis.
2. **Tahap 2 — Tolerance Window Match**: Mencocokkan sisa mutasi yang memiliki selisih nominal $|\Delta \text{amount}| \le 500$ minor unit (biaya admin/pembulatan kliring) dan selisih tanggal $|\Delta \text{hari}| \le 3$ hari. Pasangan dipilih secara deterministik dengan urutan prioritas `(selisih_nominal ASC, selisih_hari ASC, book_id ASC)`.
3. **Tahap 3 — Bounded Many-to-One Subset Match**: Untuk mutasi bank gabungan (misal 1 kredit kliring bank yang merupakan gabungan 2 hingga 4 faktur buku), algoritma mencari subset berukuran $k \in \{2, 3, 4\}$ dari maksimal 12 kandidat buku dalam jendela $\pm 5$ hari yang jumlahnya tepat sama dengan nominal mutasi bank.

### 5.3 Penanganan Antrean Unmatched & Pembatalan Rekonsiliasi (Unmatch)
- Baris mutasi bank yang tidak menemukan pasangan setelah ketiga tahap dimasukkan ke tabel `trs_bank_reconciliation_unmatched` dengan status `pending`, perhitungan umur `aging_days`, dan pembuatan tiket otomatis di `TaskInboxService` (`escalation_task_id`).
- Apabila auditor atau supervisor menemukan pasangan yang perlu dibatalkan, panggil endpoint `POST /api/treasury/reconciliation/statements/{statement}/unmatch` atau `UnmatchBankStatementAction::execute()` dengan menyertakan `reason` tertulis. Sistem mencatat `unmatch_reason`, `unmatched_by`, dan `unmatched_at` pada `trs_bank_statements` serta mengembalikan status mutasi menjadi `unreconciled`.

### 5.4 Pengelolaan Kas Kecil Sistem Imprest (`trs_petty_cash_vouchers` & `trs_petty_cash_replenishments`)
1. **Pengajuan Pengeluaran (`submitVoucher`)**: Staf kasir mengajukan voucher pengeluaran kas kecil dengan menyebutkan penerima (`payee`), kode akun beban (`expense_account_code`), dan nominal.
2. **Persetujuan & Pencairan (`approveAndReimburse`)**: Supervisor (yang berbeda dari pembuat voucher) menyetujui pencairan. Saldo rekening kas kecil dikurangi, jurnal beban diposting (`Debit Beban / Kredit trs:petty_cash:IDR`), dan bila sisa saldo kas kecil turun di bawah 20% dari `imprest_limit`, `TreasuryTaskInboxNotifier` mengirim tugas pengisian kembali ke inbox Treasury.
3. **Opname Fisik & Pengisian Kembali (`replenishImprestFund`)**: Saat pengisian kembali, kasir memasukkan hasil hitung fisik uang tunai (`physical_cash_counted_idr`). Jika terdapat selisih antara fisik kas dan saldo buku (`shortage_overage_idr != 0`), sistem otomatis memosting selisih tersebut ke `trs:bank_reconciliation_diff:IDR`, lalu memosting pengisian kembali dari rekening bank operasional hingga saldo kas kecil kembali tepat sebesar `imprest_limit`.

---

## 6. Peramalan Arus Kas 13-Minggu Musiman (48.5)

Perintah mingguan:
```bash
php artisan treasury:forecast --scenario=base --start=2026-11-01
```
menjalankan `SeasonalCashForecaster` (Algoritma D3):
- Membutuhkan riwayat arus kas mingguan dengan panjang kelipatan siklus musiman $p = 4$ minggu dan minimal $2p = 8$ minggu.
- Menghitung rata-rata per posisi minggu dalam bulan, indeks musiman dalam permille ($\lfloor 1000 \times S_k \rfloor$), deret ter-deseasonalisasi, serta parameter garis tren *Ordinary Least Squares* (intersep $a$ dan kemiringan $b$) menggunakan pecahan rasional eksak `bcmath`.
- Menghasilkan proyeksi arus kas masuk, arus kas keluar, arus kas bersih, dan saldo kas penutup selama 13 minggu ke depan untuk skenario `base`, `conservative`, atau `aggressive`, lengkap dengan sertifikat `SeasonalCashForecastCertificate`.

---

## 7. Siklus Hidup Lindung Nilai Forward Contract & Mark-to-Market (48.6)

Kontrak lindung nilai valas (`ForwardContract`) dikendalikan oleh `HedgeContractStateMachine` melalui 8 status:
1. **`draft`**: Kontrak diajukan oleh dealer dengan mencantumkan mata uang valas, nosional, kurs forward rasional, dan tanggal jatuh tempo.
2. **`approved`**: Disetujui oleh manajer perbendaharaan (wajib berbeda dari `creator_id`).
3. **`booked`**: Konfirmasi deal bank lawan (*counterparty bank*) telah diterima dan dicatat.
4. **`active`**: Kontrak memasuki masa berlaku efektif. Setiap sore hari bursa, perintah `php artisan treasury:mtm` menghitung nilai wajar *Mark-to-Market* terhadap kurs spot terbaru dan memosting selisihnya melalui `TreasuryLedgerPoster::postForwardMarkToMarket()`.
5. **`matured`**: Kontrak mencapai tanggal jatuh tempo. Transisi ke status ini otomatis memancarkan event `treasury.hedge.matured.v1` (`TreasuryHedgeMaturedEvent`) dan menerbitkan tugas instruksi penyelesaian di `TaskInboxService`.
6. **`settled`**: Kontrak diselesaikan pada kurs spot jatuh tempo melalui `SettleForwardContractAction`, memosting keuntungan/kerugian derivatif ke `trs:derivative_gain_loss:IDR`.
7. **`rolled`**: Kontrak diperpanjang ke periode berikutnya melalui `RollForwardContractAction` dengan menautkan `rolled_to_contract_id` dan `rolled_from_contract_id`.
8. **`cancelled`**: Kontrak dibatalkan. Jika pembatalan dilakukan setelah status `booked`, `active`, atau `matured`, sistem mewajibkan pembebanan biaya penalti pembatalan (`cancellation_fee_idr`) yang diposting ke akun beban `trs:hedge_cancellation_cost:IDR`.

---

## 8. Fasilitas Kredit Bank, Covenant DER & Covenant Waiver (48.7)

### 8.1 Pengawasan Rasio Debt-to-Equity (DER) Berbasis Permil
Setiap fasilitas pinjaman bank pada `trs_credit_facilities` menyimpan plafon `credit_limit_idr`, bunga tahunan dalam permil (`interest_rate_permil`), dan batas maksimum rasio utang terhadap ekuitas dalam permil (`max_der_permil`, misal `2500` untuk rasio $2,50\times$).
- Saat penarikan fasilitas diajukan melalui `DrawFacilityAction`:
  1. Sistem memverifikasi bahwa `drawn_amount_idr + amountIdr <= credit_limit_idr`.
  2. Sistem mengevaluasi rasio DER proyeksi dalam permil bulat.
  3. Apabila DER melampaui batas efektif, sistem memancarkan event `treasury.covenant.breached.v1` (`TreasuryCovenantBreachedEvent`) dan menolak transaksi dengan `CovenantBreachException`.

### 8.2 Prosedur Keringanan Covenant (`CovenantWaiver`)
Apabila perusahaan telah memperoleh surat dispensasi resmi dari bank pemberi pinjaman:
1. Dealer mengajukan permohonan keringanan melalui `TreasuryCovenantGovernanceService::requestCovenantWaiver()` dengan menyebutkan batas DER baru yang diizinkan (`waived_max_der_permil`) dan masa berlaku (`valid_until`).
2. Manajer/Pengendali Keuangan yang berbeda dari pemohon menyetujui waiver melalui `approveCovenantWaiver()` (Four-Eyes principle).
3. Selama waiver berstatus `approved` dan belum melewati `valid_until`, penarikan fasilitas dan pengujian `php artisan treasury:covenant-test` menggunakan batas `waived_max_der_permil` tersebut.

---

## 9. Cash Pooling Grup & Audit Dua Sumber (48.8–48.9)

### 9.1 Penyapuan Likuiditas Zero-Balancing (`treasury:pool-sweep`)
Perintah harian `php artisan treasury:pool-sweep` menyapu kelebihan saldo di atas `target_balance_idr` dari rekening anak perusahaan (`sub_account_id`) ke rekening utama holding (`header_account_id`), mencatat riwayat di `trs_cash_pool_sweeps`, dan memosting empat baris jurnal seimbang melalui akun kliring antar-entitas `EntityAccountFactory::forPoolSweepClearing()`.

### 9.2 Pemeriksaan Integritas Dua Sumber (`treasury:audit`)
Perintah `php artisan treasury:audit` menjalankan 8 pemeriksaan silang antara tabel operasional `trs_*` dan buku besar `bank_ledger_*`. Seluruh pemeriksaan wajib menghasilkan 0 diskrepansi (kode keluar `0`) sebelum periode akuntansi diizinkan tutup buku.

---

## 10. Contoh Perhitungan Angka Lengkap (Worked Examples — D1, D2, D3)

Bagian ini menyajikan contoh langkah demi langkah perhitungan deterministik untuk ketiga algoritma bersertifikat di modul `Treasury` agar analis perbendaharaan dan auditor internal dapat melakukan verifikasi independen secara manual maupun terprogram.

### 10.1 Contoh Perhitungan Algoritma D1 (`FxRevaluationCalculator`)

#### Kasus A: Revaluasi Piutang Usaha Ekspor (Aset Moneter USD)
- **Data Masukan**:
  - Mata Uang: `USD` (`currencyDecimals = 2`, $\text{Scale} = 10^2 = 100$)
  - Saldo Valas Tersisa (`foreignBalanceMinor`): `100.000` sen USD (setara USD 1.000,00)
  - Nilai Tercatat Buku Saat Ini (`carryingFunctionalIdr`): `Rp 15.500.000` (berasal dari kurs perolehan Rp 15.500/USD)
  - Kurs Penutupan Akhir Bulan (`rate`): `RationalRate(15650, 1)` (Rp 15.650/USD)
  - Sisi Akun (`isAsset`): `true`
- **Langkah Perhitungan**:
  1. Hitung pembilang gabungan:
     $$\text{Numerator} = 100.000 \times 15.650 = 1.565.000.000$$
  2. Hitung penyebut gabungan:
     $$\text{Denominator} = 1 \times 100 = 100$$
  3. Bagi dengan pembulatan setengah menjauhi nol (`roundHalfAwayFromZero`):
     $$\text{RevaluedFunctionalIdr} = \frac{1.565.000.000}{100} = 15.650.000$$
  4. Hitung selisih nilai tercatat:
     $$\text{Difference} = 15.650.000 - 15.500.000 = +150.000$$
  5. Karena `isAsset = true`, maka `unrealizedGainLossIdr = +150.000` (Keuntungan selisih kurs belum terealisasi).
- **Jurnal Buku Besar Akhir Periode (`2026-11-30`)**:
  - Debit `trs:fx_reval_asset_adj:IDR` sebesar `-150.000`
  - Kredit `trs:fx_unrealized_gain_loss:IDR` sebesar `+150.000`
- **Jurnal Pembalik Otomatis Awal Periode Berikutnya (`2026-12-01`)**:
  - Debit `trs:fx_unrealized_gain_loss:IDR` sebesar `-150.000`
  - Kredit `trs:fx_reval_asset_adj:IDR` sebesar `+150.000`

#### Kasus B: Revaluasi Utang Usaha Impor (Liabilitas Moneter USD)
- **Data Masukan**:
  - Mata Uang: `USD` (`currencyDecimals = 2`, $\text{Scale} = 100$)
  - Saldo Utang Valas (`foreignBalanceMinor`): `50.000` sen USD (USD 500,00)
  - Nilai Tercatat Buku (`carryingFunctionalIdr`): `Rp 7.750.000`
  - Kurs Penutupan (`rate`): `RationalRate(15650, 1)`
  - Sisi Akun (`isAsset`): `false` (Liabilitas)
- **Langkah Perhitungan**:
  1. Nilai ekuivalen IDR baru pada kurs penutupan:
     $$\text{RevaluedFunctionalIdr} = \frac{50.000 \times 15.650}{100} = 7.825.000$$
  2. Selisih kewajiban:
     $$\text{Difference} = 7.825.000 - 7.750.000 = +75.000$$
  3. Karena `isAsset = false` (kenaikan kewajiban berarti kerugian bagi perusahaan):
     $$\text{unrealizedGainLossIdr} = -\text{Difference} = -75.000$$
- **Jurnal Buku Besar (`postFxLiabilityRevaluation`)**:
  - Debit `trs:fx_unrealized_gain_loss:IDR` sebesar `-75.000`
  - Kredit `trs:fx_reval_liability_adj:IDR` sebesar `+75.000`

#### Kasus C: Pelunasan Parsial Lot Valas (`calculateSettlement`)
- **Data Masukan**:
  - Lot USD sebesar `100.000` sen dengan nilai tercatat `Rp 15.500.000`.
  - Dilunasi sebagian sebesar `40.000` sen pada kurs realisasi `RationalRate(15700, 1)`.
- **Langkah Perhitungan**:
  1. Alokasi nilai tercatat proporsional:
     $$\text{AllocatedCarryingIdr} = \frac{40.000 \times 15.500.000}{100.000} = 6.200.000$$
  2. Nilai kas penyelesaian pada kurs realisasi:
     $$\text{SettlementValueIdr} = \frac{40.000 \times 15.700}{100} = 6.280.000$$
  3. Keuntungan selisih kurs terealisasi:
     $$\text{RealizedGainLossIdr} = 6.280.000 - 6.200.000 = +80.000$$
  4. Sisa lot menjadi `60.000` sen dengan nilai tercatat tersisa `Rp 9.300.000`.

---

### 10.2 Contoh Perhitungan Algoritma D2 (`BankReconciliationMatcher`)

Misalkan rekening koran bank dan buku besar perusahaan pada pekan pertama bulan berjalan memiliki baris mutasi sebagai berikut:

#### Daftar Baris Mutasi Rekening Koran Bank (`$bankLines`)
1. `B1`: Tanggal Hari ke-1, Referensi `INV-2026-001`, Nominal `Rp 1.000.000`
2. `B2`: Tanggal Hari ke-2, Referensi `""` (kosong), Nominal `Rp 2.500.000`
3. `B3`: Tanggal Hari ke-3, Referensi `""` (kosong), Nominal `Rp 750.500`
4. `B4`: Tanggal Hari ke-5, Referensi `BATCH-CLR-09`, Nominal `Rp 3.000.000`
5. `B5`: Tanggal Hari ke-6, Referensi `FEE-ADMIN-99`, Nominal `Rp 99.999`

#### Daftar Baris Buku Besar Perusahaan (`$bookLines`)
1. `L1`: Tanggal Hari ke-1, Referensi `INV-2026-001`, Nominal `Rp 1.000.000`
2. `L2`: Tanggal Hari ke-2, Referensi `VCH-02`, Nominal `Rp 2.500.400`
3. `L3`: Tanggal Hari ke-4, Referensi `VCH-03`, Nominal `Rp 750.500`
4. `L4`: Tanggal Hari ke-4, Referensi `AR-101`, Nominal `Rp 1.200.000`
5. `L5`: Tanggal Hari ke-5, Referensi `AR-102`, Nominal `Rp 1.000.000`
6. `L6`: Tanggal Hari ke-5, Referensi `AR-103`, Nominal `Rp 800.000`
7. `L7`: Tanggal Hari ke-5, Referensi `AR-104`, Nominal `Rp 1.000.000`

#### Jalannya Eksekusi 3 Tahap:
1. **Tahap 1 (Exact Match)**:
   - Memeriksa pasangan dengan referensi tidak kosong yang sama persis dan nominal identik.
   - `B1` (`INV-2026-001`, `1.000.000`) cocok tepat dengan `L1` (`INV-2026-001`, `1.000.000`).
   - Keduanya dikeluarkan dari daftar kandidat sisa.
2. **Tahap 2 (Tolerance Match: $|\Delta\text{nominal}| \le 500$ dan $|\Delta\text{hari}| \le 3$)**:
   - Untuk `B2` (`2.500.000`, hari 2): Kandidat `L2` (`2.500.400`, hari 2) memiliki $|\Delta\text{nominal}| = 400 \le 500$ dan $|\Delta\text{hari}| = 0 \le 3$. Dipasangkan sebagai `ToleranceMatchDTO(bankId: B2, bookId: L2, discrepancyAmount: 400, dayDifference: 0)`.
   - Untuk `B3` (`750.500`, hari 3): Kandidat `L3` (`750.500`, hari 4) memiliki $|\Delta\text{nominal}| = 0 \le 500$ dan $|\Delta\text{hari}| = 1 \le 3$. Dipasangkan sebagai `ToleranceMatchDTO(bankId: B3, bookId: L3, discrepancyAmount: 0, dayDifference: 1)`.
3. **Tahap 3 (Many-to-One Subset Match: ukuran $k \in \{2, 3, 4\}$, jendela $|\Delta\text{hari}| \le 5$)**:
   - Untuk `B4` (`3.000.000`, hari 5): Kandidat buku tersisa dengan nominal $< 3.000.000$ dan $|\Delta\text{hari}| \le 5$ adalah `[L4 (1.200.000), L5 (1.000.000), L6 (800.000), L7 (1.000.000)]`.
   - Tidak ada subset ukuran $k=2$ yang berjumlah `3.000.000`.
   - Pada ukuran $k=3$, terdapat dua kombinasi yang berjumlah `3.000.000`:
     - Subset 1: `[L4, L5, L6]` ($1.200.000 + 1.000.000 + 800.000 = 3.000.000$)
     - Subset 2: `[L4, L6, L7]` ($1.200.000 + 800.000 + 1.000.000 = 3.000.000$)
   - Berdasarkan aturan tie-break leksikografis urutan ID (`L5 < L7`), algoritma memilih `[L4, L5, L6]` secara deterministik.
4. **Baris Sisa Tidak Terpadankan**:
   - Mutasi bank `B5` (`99.999`) masuk ke `trs_bank_reconciliation_unmatched` dengan perhitungan `aging_days` dan pembuatan tugas investigasi di `TaskInboxService`.
   - Baris buku `L7` (`1.000.000`) tetap berstatus terbuka untuk periode rekonsiliasi berikutnya.

---

### 10.3 Contoh Perhitungan Algoritma D3 (`SeasonalCashForecaster`)

Misalkan perusahaan memiliki riwayat arus kas masuk bersih selama $n = 12$ minggu (3 siklus penuh dengan periode musiman $p = 4$ minggu):
- Siklus 1 (Minggu 1–4): `[100, 80, 60, 140]` (dalam jutaan rupiah)
- Siklus 2 (Minggu 5–8): `[110, 90, 70, 150]`
- Siklus 3 (Minggu 9–12): `[120, 100, 80, 160]`

#### Langkah Dekomposisi & Regresi OLS Rasional:
1. **Rata-rata Keseluruhan ($\bar{y}$)**:
   $$\sum_{t=1}^{12} y_t = 1.260 \implies \bar{y} = \frac{1.260}{12} = 105$$
2. **Rata-rata Musiman per Posisi Minggu ($m_k$ untuk $k \in \{0, 1, 2, 3\}$)**:
   - $m_0 = (100 + 110 + 120) / 3 = 110$
   - $m_1 = (80 + 90 + 100) / 3 = 90$
   - $m_2 = (60 + 70 + 80) / 3 = 70$
   - $m_3 = (140 + 150 + 160) / 3 = 150$
3. **Indeks Musiman dalam Permille ($\lfloor 1000 \times m_k / \bar{y} \rfloor$)**:
   - $S_0 = \lfloor 110.000 / 105 \rfloor = 1047\text{ permil}$
   - $S_1 = \lfloor 90.000 / 105 \rfloor = 857\text{ permil}$
   - $S_2 = \lfloor 70.000 / 105 \rfloor = 666\text{ permil}$
   - $S_3 = \lfloor 150.000 / 105 \rfloor = 1428\text{ permil}$
4. **Estimasi Tren OLS Rasional ($d_t = a + b \cdot t$)**:
   - Menghasilkan kemiringan ($b$) positif dan intersep ($a$) dalam bentuk pecahan tereduksi penuh.
5. **Proyeksi 13 Minggu ke Depan ($t = 13 \dots 25$)**:
   - Setiap minggu proyeksi dihitung dengan mengalikan nilai tren $(a + b \cdot t)$ terhadap faktor musiman rasional $\frac{m_k}{\bar{y}}$, lalu dibulatkan ke bawah (`floor`) satu kali di akhir perhitungan.

---

## 11. Spesifikasi 8 Laporan CSV Resmi (`treasury:export-reports`)

Perintah `php artisan treasury:export-reports --type=all` menghasilkan 8 berkas CSV terstandar untuk kebutuhan pelaporan manajemen dan audit eksternal:

| Kode Tipe (`--type`) | Nama Kelas Exporter | Kolom Utama CSV |
|---|---|---|
| `fx-positions` | `FxPositionReportExporter` | `currency`, `active_lots_count`, `foreign_balance_minor`, `carrying_functional_idr`, `spot_rate_num`, `spot_rate_den`, `market_value_idr`, `unrealized_delta_idr` |
| `fx-lots` | `FxLotSubledgerReportExporter` | `lot_id`, `entity_code`, `currency`, `lot_date`, `original_amount`, `remaining_amount`, `original_rate`, `carrying_rate`, `current_carrying_idr`, `status`, `source_ref` |
| `revaluations` | `FxRevaluationReportExporter` | `revaluation_id`, `period`, `currency`, `foreign_balance`, `book_functional_idr`, `revalued_functional_idr`, `gain_loss_idr`, `is_reversed`, `certificate_hash` |
| `hedges` | `HedgePortfolioReportExporter` | `contract_number`, `currency`, `notional_foreign_amount`, `forward_rate_scaled`, `maturity_date`, `mtm_value_idr`, `status`, `settlement_gain_loss_idr` |
| `facilities` | `CreditFacilityCovenantReportExporter` | `facility_code`, `entity_code`, `bank_name`, `credit_limit_idr`, `drawn_amount_idr`, `available_limit_idr`, `interest_rate_permil`, `max_der_permil`, `status` |
| `cash-pools` | `CashPoolSweepsReportExporter` | `sweep_id`, `pool_name`, `sweep_date`, `direction`, `swept_amount_idr`, `header_balance_after`, `sub_balance_after`, `ledger_tx_id` |
| `reconciliation` | `BankReconciliationReportExporter` | `statement_id`, `transaction_date`, `reference_no`, `amount`, `reconciliation_status`, `match_type`, `matched_amount`, `unmatch_reason` |
| `forecast` | `CashForecast13WeekReportExporter` | `scenario`, `week_number`, `start_date`, `projected_inflow_idr`, `projected_outflow_idr`, `net_cash_flow_idr`, `closing_balance_idr` |

---

## 12. Matriks Hak Akses Rute Web & API (`route-roles.php`)

Seluruh rute pada modul `Treasury` dilindungi oleh middleware autentikasi dan pemeriksaan peran (`role:*`):

| Nama Rute / URI | Metode HTTP | Peran yang Diizinkan (`allowedRoles`) | Peran yang Ditolak (`403 Forbidden`) |
|---|---|---|---|
| `treasury.index` (`/treasury`) | `GET` | `admin`, `treasury`, `auditor` | `customer`, `guest`, staf non-keuangan |
| `treasury.positions` (`/treasury/positions`) | `GET` | `admin`, `treasury`, `auditor` | `customer`, `guest` |
| `treasury.forecast` (`/treasury/forecast`) | `GET` | `admin`, `treasury`, `auditor` | `customer`, `guest` |
| `treasury.facilities` (`/treasury/facilities`) | `GET` | `admin`, `treasury`, `auditor` | `customer`, `guest` |
| `treasury.fx` (`/treasury/fx`) | `GET` | `admin`, `treasury` | `auditor`, `customer`, `guest` |
| `treasury.reconciliation` (`/treasury/reconciliation`) | `GET` | `admin`, `treasury` | `auditor`, `customer`, `guest` |
| `treasury.hedges` (`/treasury/hedges`) | `GET` | `admin`, `treasury` | `auditor`, `customer`, `guest` |
| `treasury.pools` (`/treasury/pools`) | `GET` | `admin`, `treasury` | `auditor`, `customer`, `guest` |
| `api.treasury.fx_transfer` (`/api/treasury/fx-transfers`) | `POST` | `admin`, `treasury` | `auditor`, `customer`, `guest` |
| `api.treasury.get_hedge` (`/api/treasury/hedges/{contract}`) | `GET` | `admin`, `treasury` | `customer`, `guest` |
| `api.treasury.draw_facility` (`/api/treasury/facilities/{facility}/draw`) | `POST` | `admin`, `treasury` | `auditor`, `customer`, `guest` |

---

## 13. Panduan Langkah-demi-Langkah Operasional Meja Perbendaharaan

### 13.1 Prosedur Impor Mutasi Bank Format SWIFT MT940 & CSV
1. Unduh berkas pernyataan elektronik harian dari portal perbankan korporat dalam format SWIFT MT940 (`.mt940` / `.sta`) atau CSV terstandar.
2. Untuk mengimpor via antarmuka layanan atau CLI:
   ```bash
   php artisan treasury:import-statement --account-id=<UUID_REKENING> --path=/path/to/statement.mt940
   ```
3. `BankStatementImportService` akan melakukan langkah otomatis berikut:
   - Menghitung hash SHA-256 berkas masukan dan memeriksa tabel `trs_statement_import_batches`. Jika berkas identik sudah pernah diimpor pada rekening tersebut, sistem mengembalikan ringkasan batch dengan `is_duplicate_batch = true` dan `imported_count = 0` (aman dari duplikasi).
   - Mem-parsing tag `:20:` (nomor referensi batch), `:25:` (rekening), `:60F:` (saldo awal), `:61:` (baris mutasi tanggal YYMMDD, indikator Debit/Kredit, nominal minor unit, dan kode referensi), `:86:` (narasi deskripsi), serta `:62F:` (saldo akhir).
   - Menyimpan setiap mutasi baru ke `trs_bank_statements` dengan status awal `reconciliation_status = unreconciled`.

### 13.2 Prosedur Rekonsiliasi Bank Tiga Tahap & Pembatalan (Unmatch)
1. Jalankan pemadanan otomatis:
   ```bash
   php artisan treasury:reconcile
   ```
2. Tinjau hasil pemadanan pada `ReconciliationController`:
   - Baris yang cocok pada **Tahap 1 (`exact`)**, **Tahap 2 (`tolerance`)**, atau **Tahap 3 (`many_to_one`)** akan ditandai `is_reconciled = true` dan dibuatkan sertifikat kriptografis D2 (`BankReconciliationCertificate`).
   - Baris yang tidak menemukan pasangan akan masuk ke tabel `trs_bank_reconciliation_unmatched` dengan informasi `aging_days` dan nomor tugas `escalation_task_id` di `TaskInboxService`.
3. Apabila operator menemukan adanya pasangan mutasi yang perlu dibuka kembali (misalnya karena koreksi alokasi faktur):
   - Panggil `UnmatchBankStatementAction::execute($statementId, $reason, $actorId)` dengan alasan tertulis yang jelas (minimal 3 karakter).
   - Sistem akan mencatat `unmatch_reason`, `unmatched_by`, dan `unmatched_at`, menghapus rekaman `trs_bank_reconciliation_matches`, serta mengembalikan status mutasi menjadi `unmatched` agar siap direkonsiliasi ulang.

### 13.3 Prosedur Pengelolaan Siklus Hidup Kontrak Forward (8 Status)
1. **Pembuatan Draf (`draft`)**:
   - Dealer mendaftarkan rencana lindung nilai dengan menentukan mata uang asing (`USD`, `EUR`, dll.), nilai nosional minor unit, kurs strike rasional (`forward_rate_num / forward_rate_den`), dan tanggal jatuh tempo (`maturity_date`).
2. **Persetujuan Four-Eyes (`draft -> approved`)**:
   - Pejabat penyetuju yang berbeda dari pembuat draf (`actorId !== creator_id`) menyetujui kontrak melalui `HedgeContractStateMachine::transition($contract, 'approved', $approverId, 'Approved by Treasury Head')`.
3. **Konfirmasi Deal Bank & Aktivasi (`approved -> booked -> active`)**:
   - Setelah menerima konfirmasi deal dari bank counterparty, transisikan status ke `booked` lalu `active`.
4. **Penilaian Mark-to-Market Harian (`active`)**:
   - Jalankan `php artisan treasury:mtm` setiap sore untuk menghitung selisih nilai wajar kontrak terhadap kurs penutupan pasar dan memosting jurnal MTM ke buku besar.
5. **Jatuh Tempo, Penyelesaian, atau Perpanjangan (`matured -> settled | rolled`)**:
   - Saat tanggal jatuh tempo tiba, transisikan ke `matured` (memancarkan event `treasury.hedge.matured.v1`).
   - Pilih `settled` untuk pelunasan kas dan pengakuan laba/rugi selisih kurs terealisasi, atau pilih `rolled` dengan menyertakan ID kontrak forward baru (`rolledToContractId`) untuk memperpanjang lindung nilai ke periode berikutnya.

### 13.4 Prosedur Pengawasan Covenant Kredit, Pengajuan Waiver, & Kas Kecil Imprest
1. **Pemeriksaan Covenant Sebelum Penarikan Fasilitas**:
   - Setiap pemanggilan `DrawFacilityAction` menghitung proyeksi rasio utang terhadap ekuitas dalam satuan permil:
     $$\text{DER}_{\text{permil}} = \left\lfloor \frac{(\text{Utang Tercatat} + \text{Nominal Penarikan}) \times 1000}{\text{Ekuitas Tercatat}} \right\rfloor$$
   - Jika $\text{DER}_{\text{permil}} > \text{max\_der\_permil}$, sistem memancarkan event `treasury.covenant.breached.v1` dan menolak penarikan dengan `CovenantBreachException`.
2. **Pengajuan & Persetujuan Covenant Waiver (`trs_covenant_waivers`)**:
   - Apabila bank pemberi pinjaman menerbitkan surat keringanan covenant sementara, ajukan permohonan melalui `TreasuryCovenantGovernanceService::requestCovenantWaiver()`.
   - Pejabat kedua yang independen menyetujui waiver tersebut melalui `approveCovenantWaiver()`. Selama masa berlaku waiver belum habis, penarikan fasilitas diizinkan hingga batas `waived_max_der_permil`.
3. **Pengelolaan Kas Kecil Sistem Imprest (`trs_petty_cash_replenishments`)**:
   - Pengeluaran tunai harian dicatat melalui `ProcessPettyCashVoucherAction::submitVoucher()` dan dicairkan oleh penyetuju berbeda melalui `approveAndReimburse()`.
   - Pada saat pengisian kembali dana kas kecil, lakukan opname fisik uang tunai di brankas dan panggil `replenishImprestFund($pettyCashAccount, $physicalCashCountedIdr, $approverId, $creatorId)`. Sistem akan membukukan selisih opname (bila ada) dan mengisi kembali saldo kas kecil tepat ke plafon `imprest_limit`.

---

## 14. Pertanyaan yang Sering Diajukan (FAQ Operasional & Audit Perbendaharaan)

### Q1: Mengapa modul Treasury tidak menggunakan tipe data `float` untuk kurs mata uang maupun rasio keuangan?
Penggunaan `float` IEEE 754 menyebabkan galat representasi biner (seperti `0.1 + 0.2 != 0.3`) yang dapat mengakumulasi selisih beberapa rupiah pada transaksi valas bernilai miliaran rupiah dan memicu kegagalan invarian buku besar $\Sigma = 0$. Oleh sebab itu:
- Seluruh kurs disimpan sebagai pecahan rasional bulat `RationalRate(numerator, denominator)`.
- Seluruh rasio covenant dan suku bunga disimpan dalam satuan permil bulat (`1 permil = 0,1%`, atau `2500 permil = 2,50x`).
- Seluruh nominal uang disimpan dalam satuan minor bulat (`int`).

### Q2: Bagaimana cara kerja rekonsiliasi lintas-modul antara PO Impor (`Procurement`), Pengapalan (`Trade`), dan Revaluasi (`Treasury`)?
Modul `Procurement` dan `Trade` masing-masing mengimplementasikan antarmuka `Modules\Treasury\Contracts\FxMonetaryItemFeed` dan mendaftarkannya dengan container tag `'treasury.fx_monetary_item_feed'`. Ketika `TreasuryCovenantGovernanceService::aggregateAndRevalueCrossModuleFeeds()` dijalankan pada penutupan periode:
1. Seluruh pos moneter valas terbuka dari kedua modul tersebut dibaca secara malas (*lazy generator*).
2. Setiap pos yang belum terdaftar di `trs_fx_lots` disinkronkan sebagai lot valas aktif dengan referensi sumber `Procurement:PO-...` atau `Trade:IMP-...`.
3. Seluruh lot aktif direvaluasi menggunakan kurs penutupan periode berjalan melalui algoritma D1 (`FxRevaluationCalculator`) dan menghasilkan sertifikat ber-hash SHA-256.

### Q3: Apa yang terjadi jika jurnal revaluasi akhir bulan lupa dibalik pada tanggal 1 bulan berikutnya?
Jadwal otomatis `treasury:reverse-revaluations` dirancang untuk berjalan setiap tanggal 1 pukul `00:05`. Namun apabila jadwal tersebut sempat tertunda, operator dapat menjalankannya kapan saja secara manual. Karena setiap rekaman `trs_revaluations` memiliki bendera `is_reversed` dan kunci idempotensi `IDEM:TRS:REVAL_REV:{period}:{ccy}:{id}`, pemanggilan berulang kali dijamin hanya memosting tepat satu jurnal pembalik untuk setiap revaluasi asli.

### Q4: Bagaimana auditor eksternal memverifikasi bahwa sertifikat perhitungan D1, D2, dan D3 tidak dimanipulasi di database?
Setiap sertifikat menyimpan kolom `certificateHash` (SHA-256 atas representasi JSON kanonis dari `algorithm`, `inputs`, `steps`, dan `results`). Auditor atau sistem dapat memanggil:
- `FxRevaluationCertificateVerifier::assertValid($certificate)`
- `BankReconciliationCertificateVerifier::assertValid($certificate)`
- `SeasonalCashForecastCertificateVerifier::assertValid($certificate)`
Jika ada satu angka saja pada `inputs` atau `results` yang diubah secara manual di tabel database, hash SHA-256 maupun pemeriksaan invarian matematis di dalam verifier akan langsung melempar `InvalidCertificateException`.



