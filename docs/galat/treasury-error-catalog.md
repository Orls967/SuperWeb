# Katalog Kode Galat & Pengecualian Domain Treasury (Fase 48)

**Modul**: `Treasury` (`Modules\Treasury`)  
**Cakupan**: Validasi Kurs Rasional, Revaluasi Valas PSAK 10, Rekonsiliasi Bank 3-Tahap, Peramalan Kas 13-Minggu, State Machine Lindung Nilai Forward, Pengawasan Covenant Kredit, Cash Pooling, Kas Kecil Imprest, dan Audit Dua Sumber.

---

## 1. Struktur Klasifikasi Galat Perbendaharaan

Seluruh pengecualian (*exceptions*) dan penolakan validasi pada modul `Treasury` dikelompokkan ke dalam 8 kategori domain dengan kode galat terstandar agar mudah dilacak oleh operator meja perbendaharaan, sistem pemantauan log, maupun auditor internal:

| Kode Prefiks | Kategori Domain | Kelas Pengecualian Utama |
|---|---|---|
| `TRS-FX-*` | Kurs Rasional, Konversi & Revaluasi Valas (D1) | `InvalidArgumentException`, `ExchangeRateNotFoundException`, `RevaluationAlreadyReversedException`, `TreasuryException` |
| `TRS-CERT-*` | Verifikasi Sertifikat Kriptografis Algoritma (D1/D2/D3) | `InvalidCertificateException` |
| `TRS-RECON-*` | Impor Mutasi MT940/CSV, Rekonsiliasi Bank & Unmatch (D2) | `InvalidArgumentException`, `TreasuryException` |
| `TRS-FCST-*` | Peramalan Arus Kas Musiman 13-Minggu (D3) | `InvalidArgumentException` |
| `TRS-HDG-*` | Siklus Hidup Kontrak Lindung Nilai Forward & Four-Eyes | `HedgeInvalidTransitionException`, `HedgeMakerCheckerViolationException`, `InvalidArgumentException` |
| `TRS-COV-*` | Plafon Fasilitas Kredit, Covenant DER & Waiver | `CovenantBreachException`, `HedgeMakerCheckerViolationException`, `InvalidArgumentException` |
| `TRS-POOL-*` | Penyapuan Cash Pooling & Perlindungan Saldo Negatif | `PoolSweepNegativeBalanceException`, `InvalidArgumentException` |
| `TRS-PCV-*` | Kas Kecil Sistem Imprest & Opname Fisik Kas | `InvalidArgumentException`, `TreasuryException` |

---

## 2. Daftar Lengkap Kode Galat & Prosedur Penanganan

### 2.1 Kategori `TRS-FX`: Kurs Rasional & Revaluasi Valuta Asing

#### `TRS-FX-001` — `INVALID_RATE: Numerator dan denominator kurs rasional wajib bilangan bulat positif (> 0)`
- **Kelas Pemicu**: `Modules\Treasury\Domain\Algorithms\RationalRate::__construct()`
- **Penyebab**: Operator atau sistem pengumpan kurs mengirimkan nilai pembilang (`numerator <= 0`) atau penyebut (`denominator <= 0`) saat membuat objek `RationalRate`.
- **Dampak**: Perhitungan konversi dan revaluasi dihentikan seketika untuk mencegah pembagian dengan nol (*division by zero*) atau pembalikan tanda moneter ilegal.
- **Tindakan Korektif**: Pastikan kurs spot maupun kurs penutupan yang diinput pada `StoreExchangeRateRequest` atau `TreasuryService::recordExchangeRate()` bernilai integer $\ge 1$.

#### `TRS-FX-002` — `Kurs dari {FROM} ke {TO} untuk tanggal {DATE} tidak ditemukan`
- **Kelas Pemicu**: `Modules\Treasury\Application\Services\TreasuryService::convertAmount()` / `ExchangeRateNotFoundException`
- **Penyebab**: Tidak tersedia rekaman kurs langsung (`from -> to`) maupun kurs invers (`to -> from`) pada tabel `trs_exchange_rates` untuk pasangan mata uang yang diminta.
- **Dampak**: Transaksi transfer multi-currency atau revaluasi tidak dapat dikonversi ke mata uang fungsional IDR.
- **Tindakan Korektif**: Daftarkan kurs harian terlebih dahulu melalui `POST /api/treasury/exchange-rates` atau `TreasuryService::recordExchangeRate()`.

#### `TRS-FX-003` — `Header Idempotency-Key wajib disertakan untuk menjamin konsistensi transfer valas`
- **Kelas Pemicu**: `Modules\Treasury\Http\Controllers\TreasuryApiController::postFxTransfer()`
- **Status HTTP**: `422 Unprocessable Entity`
- **Penyebab**: Klien API memanggil endpoint transfer multi-currency tanpa menyertakan header `Idempotency-Key`.
- **Dampak**: Permintaan ditolak sebelum menyentuh buku besar untuk mencegah pendebitan ganda bila terjadi pengiriman ulang jaringan (*network retry*).
- **Tindakan Korektif**: Sertakan header `Idempotency-Key: IDEM:TRS:<UNIK>` pada setiap permintaan HTTP POST mutasi uang.

#### `TRS-FX-004` — `Nominal pelunasan ({settled}) melampaui sisa lot ({remaining})`
- **Kelas Pemicu**: `Modules\Treasury\Application\Services\TreasuryService::settleFxLot()`
- **Penyebab**: Nominal pelunasan valas yang diminta lebih besar daripada `remaining_amount` pada rekaman `trs_fx_lots`.
- **Dampak**: Transaksi dibatalkan (*rollback*) agar subledger valas tidak pernah memiliki saldo tersisa negatif.
- **Tindakan Korektif**: Periksa sisa saldo lot pada laporan subledger `fx-lots` dan bagi pelunasan ke beberapa lot aktif sesuai urutan FIFO atau identifikasi khusus.

#### `TRS-FX-005` — `Revaluasi {period} {currency} sudah pernah dibalik`
- **Kelas Pemicu**: `Modules\Treasury\Application\Actions\ReverseFxRevaluationAction::execute()` / `RevaluationAlreadyReversedException`
- **Penyebab**: Percobaan membalik rekaman `trs_revaluations` yang sudah memiliki bendera `is_reversed = true`.
- **Dampak**: Mencegah penggandaan jurnal pembalik pada awal periode baru.
- **Tindakan Korektif**: Tidak diperlukan tindakan; revaluasi periode tersebut sudah selesai dibalik secara sah.

#### `TRS-FX-006` — `Tidak ada selisih kurs yang perlu diposting`
- **Kelas Pemicu**: `Modules\Treasury\Application\Services\TreasuryLedgerPoster::postFxRevaluation()` / `postFxLiabilityRevaluation()`
- **Penyebab**: Pemanggilan langsung metode poster buku besar dengan nilai `gainLossIdr === 0`.
- **Tindakan Korektif**: Pastikan action pemanggil memeriksa `if ($gainLossIdr !== 0)` sebelum memanggil metode pemostingan jurnal.

---

### 2.2 Kategori `TRS-CERT`: Verifikasi Sertifikat Algoritma (D1, D2, D3)

#### `TRS-CERT-001` — `Sertifikat Revaluasi Valas (D1) tidak valid atau telah dimodifikasi`
- **Kelas Pemicu**: `Modules\Treasury\Domain\Algorithms\Certificates\FxRevaluationCertificateVerifier::assertValid()`
- **Pengecualian**: `Modules\Treasury\Domain\Exceptions\InvalidCertificateException`
- **Penyebab**: Nilai `certificateHash` SHA-256 yang tersimpan tidak cocok dengan hasil hitung ulang kanonis atas `inputs`, `steps`, dan `results`, atau persamaan invariansi $\text{revalued\_idr} - \text{current\_carrying\_idr} \neq \pm\text{unrealized\_gain\_loss\_idr}$ dilanggar.
- **Tindakan Korektif**: Selidiki potensi manipulasi langsung basis data pada kolom JSON `certificate` di `trs_revaluations` dan jalankan ulang perhitungan revaluasi resmi.

#### `TRS-CERT-002` — `Sertifikat Rekonsiliasi Bank (D2) tidak valid atau telah dimodifikasi`
- **Kelas Pemicu**: `Modules\Treasury\Domain\Algorithms\Certificates\BankReconciliationCertificateVerifier::assertValid()`
- **Pengecualian**: `Modules\Treasury\Domain\Exceptions\InvalidCertificateException`
- **Penyebab**: Hash SHA-256 sertifikat rekonsiliasi tidak sesuai dengan payload kanonis atau jumlah baris terpadankan + tidak terpadankan tidak sama dengan jumlah baris masukan (`bank_lines_count`).
- **Tindakan Korektif**: Batalkan sesi rekonsiliasi yang rusak dan jalankan kembali `php artisan treasury:reconcile`.

#### `TRS-CERT-003` — `Sertifikat Peramalan Kas Musiman (D3) tidak valid atau telah dimodifikasi`
- **Kelas Pemicu**: `Modules\Treasury\Domain\Algorithms\Certificates\SeasonalCashForecastCertificateVerifier::assertValid()`
- **Pengecualian**: `Modules\Treasury\Domain\Exceptions\InvalidCertificateException`
- **Penyebab**: Ketidakcocokan hash SHA-256 atau total `projected_sum` tidak sama dengan penjumlahan elemen `projected_weeks`.
- **Tindakan Korektif**: Hasilkan ulang ramalan arus kas 13 minggu melalui `php artisan treasury:forecast`.

---

### 2.3 Kategori `TRS-RECON`: Impor Rekening Koran & Rekonsiliasi Bank

#### `TRS-RECON-001` — `Konten MT940 tidak boleh kosong`
- **Kelas Pemicu**: `Modules\Treasury\Application\Services\BankStatementImportService::importMt940Content()`
- **Penyebab**: String atau berkas MT940 yang diunggah hanya berisi spasi kosong.
- **Tindakan Korektif**: Unggah berkas SWIFT MT940 yang memuat minimal tag `:20:`, `:25:`, `:60F:`, `:61:`, dan `:62F:`.

#### `TRS-RECON-002` — `Alasan pembatalan rekonsiliasi (unmatch reason) wajib diisi untuk jejak audit`
- **Kelas Pemicu**: `Modules\Treasury\Application\Actions\UnmatchBankStatementAction::execute()`
- **Penyebab**: Operator mencoba membatalkan status kecocokan mutasi bank dengan string alasan kosong.
- **Tindakan Korektif**: Berikan penjelasan alasan pembatalan minimal 3 karakter untuk disimpan pada kolom `unmatch_reason` di `trs_bank_statements`.

#### `TRS-RECON-003` — `Mutasi bank {reference_no} belum pernah direkonsiliasi`
- **Kelas Pemicu**: `Modules\Treasury\Application\Actions\UnmatchBankStatementAction::execute()`
- **Penyebab**: Pemanggilan aksi unmatch pada baris mutasi yang masih berstatus `is_reconciled = false` dan `reconciliation_status = unreconciled`.
- **Tindakan Korektif**: Hanya mutasi yang telah berstatus `matched` atau memiliki antrean rekonsiliasi aktif yang dapat di-unmatch.

---

### 2.4 Kategori `TRS-FCST`: Peramalan Arus Kas 13-Minggu (D3)

#### `TRS-FCST-001` — `INSUFFICIENT_HISTORY: Riwayat arus kas kurang dari 2 siklus musiman minimum`
- **Kelas Pemicu**: `Modules\Treasury\Domain\Algorithms\SeasonalCashForecaster::forecast()`
- **Penyebab**: Panjang array `historyWeeks` kurang dari $2 \times \text{seasonPeriod}$ (misal kurang dari 8 minggu untuk periode musiman 4 mingguan).
- **Tindakan Korektif**: Sediakan minimal 2 siklus penuh data historis mingguan (8, 12, atau 52 minggu).

#### `TRS-FCST-002` — `HISTORY_NOT_FULL_CYCLES: Jumlah minggu wajib kelipatan dari periode musiman`
- **Kelas Pemicu**: `Modules\Treasury\Domain\Algorithms\SeasonalCashForecaster::forecast()`
- **Penyebab**: Jumlah minggu riwayat tidak habis dibagi oleh `seasonPeriod` ($n \bmod p \neq 0$), yang akan menyebabkan bias bobot rata-rata musiman.
- **Tindakan Korektif**: Potong atau genapkan jendela riwayat masukan menjadi kelipatan bulat dari periode musiman (contoh: 8, 12, 16, atau 52 minggu untuk $p=4$).

---

### 2.5 Kategori `TRS-HDG`: State Machine Lindung Nilai Forward & Four-Eyes

#### `TRS-HDG-001` — `Transisi status kontrak lindung nilai dari '{from}' ke '{to}' tidak diizinkan`
- **Kelas Pemicu**: `Modules\Treasury\Domain\StateMachines\HedgeContractStateMachine::transition()`
- **Pengecualian**: `Modules\Treasury\Domain\Exceptions\HedgeInvalidTransitionException`
- **Penyebab**: Percobaan melompati tahapan siklus hidup yang sah (misal langsung dari `draft` ke `settled`, atau mengubah kontrak yang sudah final di `settled` / `rolled` / `cancelled`).
- **Tindakan Korektif**: Ikuti urutan state machine resmi: `draft -> approved -> booked -> active -> matured -> (settled | rolled | cancelled)`.

#### `TRS-HDG-002` — `Penyetuju kontrak lindung nilai tidak boleh sama dengan pembuat draf (Four-Eyes Segregation of Duties)`
- **Kelas Pemicu**: `Modules\Treasury\Domain\StateMachines\HedgeContractStateMachine::transition()`
- **Pengecualian**: `Modules\Treasury\Domain\Exceptions\HedgeMakerCheckerViolationException`
- **Penyebab**: Pengguna yang membuat kontrak (`creator_id`) mencoba mengeksekusi transisi persetujuan ke status `approved` dengan ID pengguna yang sama (`actorId === creator_id`).
- **Tindakan Korektif**: Minta manajer perbendaharaan atau pejabat berwenang lain untuk melakukan peninjauan dan persetujuan kontrak.

#### `TRS-HDG-003` — `Kontrak lindung nilai harus menggunakan mata uang valas selain mata uang fungsional IDR`
- **Kelas Pemicu**: `Modules\Treasury\Domain\StateMachines\HedgeContractStateMachine::validateContractIntegrity()`
- **Penyebab**: Kontrak forward dibuat dengan mata uang `IDR` atau nominal nosional $\le 0$.
- **Tindakan Korektif**: Gunakan mata uang asing yang sah (`USD`, `EUR`, `SGD`, `JPY`, dll.) dengan nosional positif.

---

### 2.6 Kategori `TRS-COV`: Fasilitas Kredit Bank, Covenant DER & Waiver

#### `TRS-COV-001` — `Penarikan ditolak: Rasio DER ({actual} permil) melampaui batas covenant bank ({max} permil)`
- **Kelas Pemicu**: `Modules\Treasury\Application\Actions\DrawFacilityAction::execute()`
- **Pengecualian**: `Modules\Treasury\Domain\Exceptions\CovenantBreachException`
- **Efek Samping**: Memancarkan event `treasury.covenant.breached.v1` ke `EventSpine` dan mencatat log peringatan.
- **Penyebab**: Rasio utang terhadap ekuitas dalam satuan permil melampaui `max_der_permil` fasilitas kredit dan tidak terdapat `CovenantWaiver` aktif yang disetujui.
- **Tindakan Korektif**: Kurangi nominal penarikan, tambah ekuitas/lunasi sebagian utang, atau ajukan dan setujui `CovenantWaiver` resmi berdasarkan surat dispensasi bank.

#### `TRS-COV-002` — `Penarikan melampaui plafon kredit yang disetujui`
- **Kelas Pemicu**: `Modules\Treasury\Application\Actions\DrawFacilityAction::execute()` / `DrawCreditFacilityAction::execute()`
- **Penyebab**: `drawn_amount_idr + amountIdr > credit_limit_idr`.
- **Tindakan Korektif**: Gunakan fasilitas kredit sindikasi lain yang masih memiliki sisa plafon (`availableLimitIdr()`) mencukupi.

#### `TRS-COV-003` — `Penyetuju covenant waiver tidak boleh sama dengan pemohon (Four-Eyes Segregation of Duties)`
- **Kelas Pemicu**: `Modules\Treasury\Application\Services\TreasuryCovenantGovernanceService::approveCovenantWaiver()`
- **Pengecualian**: `Modules\Treasury\Domain\Exceptions\HedgeMakerCheckerViolationException`
- **Penyebab**: Pejabat yang mengajukan `requestCovenantWaiver` mencoba menyetujui permohonannya sendiri.
- **Tindakan Korektif**: Persetujuan waiver wajib dilakukan oleh pejabat kedua yang independen.

---

### 2.7 Kategori `TRS-POOL` & `TRS-PCV`: Cash Pooling & Kas Kecil Imprest

#### `TRS-POOL-001` — `Penyapuan kas ditolak: saldo anak tidak mencukupi dan akan bersaldo negatif`
- **Kelas Pemicu**: `Modules\Treasury\Application\Actions\ExecuteCashPoolSweepAction::execute()`
- **Pengecualian**: `Modules\Treasury\Domain\Exceptions\PoolSweepNegativeBalanceException`
- **Penyebab**: Operasi penyapuan kas menyebabkan saldo rekening anak perusahaan (`sub_account_id`) jatuh di bawah nol.
- **Tindakan Korektif**: Sesuaikan `target_balance_idr` atau gunakan mekanisme injeksi likuiditas (*reverse sweep* `header_to_sub`) dari induk holding.

#### `TRS-PCV-001` — `Penyetuju voucher kas kecil tidak boleh sama dengan pembuat voucher (Four-Eyes principle)`
- **Kelas Pemicu**: `Modules\Treasury\Application\Actions\ProcessPettyCashVoucherAction::approveAndReimburse()`
- **Penyebab**: Kasir pembuat voucher (`created_by`) mencoba mencairkan vouchernya sendiri (`approverId === created_by`).
- **Tindakan Korektif**: Minta supervisor keuangan untuk menyetujui dan mencairkan voucher kas kecil.

#### `TRS-PCV-002` — `Saldo kas kecil tidak mencukupi untuk pembayaran voucher`
- **Kelas Pemicu**: `Modules\Treasury\Application\Actions\ProcessPettyCashVoucherAction::approveAndReimburse()`
- **Penyebab**: Saldo rekening kas kecil saat ini lebih kecil daripada nominal voucher yang akan dicairkan.
- **Tindakan Korektif**: Lakukan pengisian kembali dana kas kecil terlebih dahulu melalui `ProcessPettyCashVoucherAction::replenishImprestFund()`.

#### `TRS-PCV-003` — `Rekening bukan merupakan dana kas kecil imprest`
- **Kelas Pemicu**: `Modules\Treasury\Application\Actions\ProcessPettyCashVoucherAction::replenishImprestFund()`
- **Penyebab**: ID rekening yang diberikan pada parameter `pettyCashAccountId` memiliki bendera `is_petty_cash = false` pada tabel `trs_bank_accounts`.
- **Tindakan Korektif**: Pastikan ID rekening yang dipilih untuk pengisian kembali imprest adalah rekening kas kecil resmi yang telah dikonfigurasi dengan `is_petty_cash = true` dan `imprest_limit > 0`.

#### `TRS-PCV-004` — `Hasil perhitungan fisik kas (physical_cash_counted_idr) tidak boleh bernilai negatif`
- **Kelas Pemicu**: `Modules\Treasury\Application\Actions\ProcessPettyCashVoucherAction::replenishImprestFund()`
- **Penyebab**: Operator memasukkan angka negatif pada hasil berita acara opname fisik kas kecil.
- **Tindakan Korektif**: Masukkan nilai integer non-negatif ($\ge 0$) sesuai lembar berita acara perhitungan fisik kas.

---

### 2.8 Kategori `TRS-AUD`: Kegagalan Invarian Audit Dua Sumber (`treasury:audit`)

#### `TRS-AUD-001` — `[Check 1] Unbalanced ledger transaction {txId} for currency {ccy}: sum={sum}`
- **Kelas Pemicu**: `Modules\Treasury\Console\Commands\AuditTreasuryCommand::handle()`
- **Penyebab**: Penjumlahan seluruh baris `bank_ledger_entries` untuk suatu `transaction_id` dan `currency` tidak sama dengan `0`.
- **Tindakan Korektif**: Telusuri transaksi jurnal terkait dan pastikan seluruh pemostingan dilakukan melalui `LedgerService::post()` yang memvalidasi invariansi zero-sum.

#### `TRS-AUD-002` — `[Check 2] Facility {code} drawn_amount_idr ({drawn}) != sum of approved drawdowns ({sum})`
- **Kelas Pemicu**: `Modules\Treasury\Console\Commands\AuditTreasuryCommand::handle()`
- **Penyebab**: Kolom agregat `drawn_amount_idr` pada `trs_credit_facilities` tidak sinkron dengan total baris anak di `trs_credit_facility_drawdowns`.
- **Tindakan Korektif**: Rekonsiliasi tabel `trs_credit_facilities` terhadap bukti transaksi buku besar bertipe `TRS_FACILITY_DRAW`.

#### `TRS-AUD-003` — `[Check 3] Reversal record {id} gain_loss_idr ({rev}) is not exact negation of original ({orig})`
- **Kelas Pemicu**: `Modules\Treasury\Console\Commands\AuditTreasuryCommand::handle()`
- **Penyebab**: Rekaman pembalik revaluasi pada `trs_revaluations` memiliki nominal yang tidak persis berlawanan tanda dengan rekaman revaluasi induknya, atau induk masih berstatus `is_reversed = false`.
- **Tindakan Korektif**: Periksa integritas referensi `reverse_of` dan jalankan ulang `ReverseFxRevaluationAction` untuk memperbaiki status pembalikan.

#### `TRS-AUD-004` — `[Check 4] Statement {reference_no} is marked reconciled but has pending unmatched queue record`
- **Kelas Pemicu**: `Modules\Treasury\Console\Commands\AuditTreasuryCommand::handle()`
- **Penyebab**: Baris mutasi rekening koran memiliki status ganda (terpadankan sekaligus masih mengantre di `trs_bank_reconciliation_unmatched`).
- **Tindakan Korektif**: Jalankan `UnmatchBankStatementAction` pada mutasi tersebut lalu ulangi `php artisan treasury:reconcile`.

#### `TRS-AUD-005` — `[Check 5] Exact match {id} matched_amount ({matched}) != statement amount ({stmt})`
- **Kelas Pemicu**: `Modules\Treasury\Console\Commands\AuditTreasuryCommand::handle()`
- **Penyebab**: Baris rekonsiliasi yang diklaim sebagai kecocokan persis (`exact`) memiliki selisih nominal antara mutasi bank dan mutasi buku.
- **Tindakan Korektif**: Batalkan rekonsiliasi baris tersebut dan jalankan pencocokan ulang agar diproses pada tahap toleransi atau manual.

#### `TRS-AUD-006` — `[Check 6] Exhausted FX lot {id} still has non-zero remaining_amount or current_carrying_idr`
- **Kelas Pemicu**: `Modules\Treasury\Console\Commands\AuditTreasuryCommand::handle()`
- **Penyebab**: Lot valuta asing yang sudah ditutup (`status = exhausted`) masih menyisakan saldo valas atau residu nilai tercatat IDR akibat pembulatan yang tidak dibersihkan.
- **Tindakan Korektif**: Pastikan pelunasan akhir lot valas di `TreasuryService::settleFxLot()` mengalokasikan seluruh sisa `current_carrying_idr` ke perhitungan laba/rugi selisih kurs terealisasi.

#### `TRS-AUD-007` — `[Check 7] Petty cash account {account_number} has negative balance ({balance})`
- **Kelas Pemicu**: `Modules\Treasury\Console\Commands\AuditTreasuryCommand::handle()`
- **Penyebab**: Pencairan kas kecil melampaui dana yang tersedia sehingga saldo rekening atau akun buku besar `allow_negative = false` menjadi negatif.
- **Tindakan Korektif**: Eksekusi pengisian kembali dana kas kecil (`replenishImprestFund`) dan pastikan pemeriksaan saldo sebelum pencairan voucher selalu aktif.

#### `TRS-AUD-008` — `[Check 8] Drawdown {id} stored DER ({stored} permil) != recalculated DER ({expected} permil)`
- **Kelas Pemicu**: `Modules\Treasury\Console\Commands\AuditTreasuryCommand::handle()`
- **Penyebab**: Nilai rasio DER yang tercatat pada bukti penarikan fasilitas kredit tidak cocok dengan hasil hitung ulang integer $\lfloor \text{verified\_debt\_idr} \times 1000 / \text{verified\_equity\_idr} \rfloor$.
- **Tindakan Korektif**: Audit rekaman `trs_credit_facility_drawdowns` terkait terhadap laporan posisi keuangan pada tanggal penarikan.

---

## 3. Matriks Pemetaan Pengecualian ke Kode Status HTTP & Respons API

| Kelas Pengecualian PHP | Kode Galat Katalog | HTTP Status | Perilaku Transaksi DB & Outbox |
|---|---|---|---|
| `ExchangeRateNotFoundException` | `TRS-FX-002` | `422 Unprocessable Entity` | Rollback penuh; tidak ada event dipancarkan |
| `RevaluationAlreadyReversedException` | `TRS-FX-005` | `409 Conflict` | Rollback penuh; jurnal pembalik lama tetap utuh |
| `InvalidCertificateException` | `TRS-CERT-001..003` | `422 Unprocessable Entity` | Rollback penuh; tolak penyimpanan sertifikat cacat |
| `HedgeInvalidTransitionException` | `TRS-HDG-001` | `422 Unprocessable Entity` | Rollback penuh; status kontrak tidak berubah |
| `HedgeMakerCheckerViolationException` | `TRS-HDG-002`, `TRS-COV-003` | `403 Forbidden` | Rollback penuh; pelanggaran Four-Eyes dicatat di log audit |
| `CovenantBreachException` | `TRS-COV-001` | `422 Unprocessable Entity` | Penarikan dibatalkan; event `treasury.covenant.breached.v1` tetap dipancarkan |
| `PoolSweepNegativeBalanceException` | `TRS-POOL-001` | `422 Unprocessable Entity` | Rollback penyapuan kas; saldo rekening anak tetap aman |
| `InvalidArgumentException` | `TRS-FX-001`, `TRS-RECON-*`, `TRS-FCST-*` | `422 Unprocessable Entity` | Validasi input gagal sebelum mutasi state |

---

## 4. Panduan Pemecahan Masalah Berdasarkan Gejala Log & Jejak Audit

### 4.1 Gejala: `IdempotencyConflictException` pada Pemostingan Jurnal Treasury
- **Pesan Log**: `Idempotency key 'IDEM:TRS:...' telah digunakan dengan payload transaksi yang berbeda.`
- **Akar Penyebab**: Pemanggilan ulang aksi perbendaharaan menggunakan kunci idempotensi yang sama tetapi dengan nominal atau kode akun yang berubah (misalnya mengubah kurs atau nominal revaluasi tanpa menggunakan versi kurs baru).
- **Solusi**:
  - Jika transaksi merupakan pengiriman ulang jaringan (*network retry*) yang sah, pastikan seluruh parameter payload identik sehingga `LedgerService::post()` mengembalikan transaksi asli secara idempoten.
  - Jika transaksi merupakan koreksi atau revisi nilai baru, gunakan mekanisme pembalikan resmi (`ReverseFxRevaluationAction`) atau sertakan nomor versi baru pada kunci idempotensi.

### 4.2 Gejala: `InsufficientFundsException` pada Akun Buku Besar `allow_negative = false`
- **Pesan Log**: `Saldo tidak mencukupi pada akun 'trs:...'. Saldo saat ini: 0.00, jumlah diminta: ...`
- **Akar Penyebab**: Dalam konvensi single-sign `LedgerService`, entri Debit disimpan sebagai angka bertanda negatif (`-`) dan entri Kredit disimpan sebagai angka bertanda positif (`+`). Akun yang menerima pendebitan awal (seperti akun beban bunga `trs:interest_expense:IDR`, beban kas kecil `trs:petty_cash_expense:IDR`, atau kas operasional) wajib memiliki atribut `allow_negative = true` di `TreasuryAccounts::definitions()`.
- **Solusi**:
  - Pastikan seeder `LedgerAccountsSeeder` telah dijalankan ulang setelah pembaruan definisi `TreasuryAccounts`.
  - Gunakan Check 7 pada `php artisan treasury:audit` untuk memantau saldo operasional kas kecil di tabel `trs_bank_accounts`.

### 4.3 Gejala: Kontrak Lindung Nilai Gagal Roll-Over (`TRS-HDG-004`)
- **Pesan Log**: `Transisi ke status 'rolled' mewajibkan ID kontrak forward baru (rolledToContractId) yang valid.`
- **Akar Penyebab**: Operator mengeksekusi transisi `matured -> rolled` pada `HedgeContractStateMachine` tanpa membuat draf kontrak forward pengganti terlebih dahulu, atau memberikan `rolledToContractId` yang sama dengan ID kontrak induk.
- **Solusi**:
  1. Buat kontrak forward baru untuk periode jatuh tempo berikutnya melalui `TreasuryService::createForwardContract()`.
  2. Salin UUID kontrak baru tersebut ke parameter `$rolledToContractId` saat memanggil `HedgeContractStateMachine::transition($parentContract, 'rolled', $actorId, $reason, $closingRate, $rolledToContractId)`.

### 4.4 Daftar Periksa Verifikasi Cepat Setelah Pemulihan Galat
Setiap kali operator menyelesaikan penanganan galat pada salah satu kategori `TRS-*` di atas, jalankan tiga langkah verifikasi berikut:
1. Pastikan tidak ada antrean pekerjaan gagal pada tabel outbox atau antrean rekonsiliasi yang berstatus ambigu.
2. Jalankan `php artisan treasury:reconcile` untuk memastikan invarian single-state rekening koran tetap terjaga.
3. Jalankan `php artisan treasury:audit` dan pastikan output konsol menampilkan `Treasury audit PASSED with 0 discrepancy.` dengan kode keluar `0`.


