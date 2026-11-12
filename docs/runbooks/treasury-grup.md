# Runbook Operasional: Perbendaharaan & Keuangan Grup

Dokumen ini memuat prosedur operasional standar (SOP), jadwal eksekusi batch, penanganan insiden, pemulihan bencana (*disaster recovery*), dan checklist penutupan periode perbendaharaan untuk seluruh perintah artisan dan pekerjaan latar belakang domain Treasury.

---

## 1. Daftar Perintah Terjadwal & Konsol (Fase 48)

| Nama Perintah | Jadwal (Cron) | Sifat Idempotensi | Tujuan Utama |
|---|---|---|---|
| `treasury:fx-revalue {period}` | Bulanan (Hari terakhir 23:59) | Idempoten per `(period, lot_id)` | Menghitung dan memposting revaluasi kurs pos moneter valas sesuai PSAK 10. |
| `treasury:reverse-revaluations` | Bulanan (Hari pertama 00:05) | Idempoten per `revaluation_id` | Memposting jurnal pembalik otomatis revaluasi periode sebelumnya. |
| `treasury:import-statement` | On-demand / Harian 06:00 | Idempoten per `(account, ref, date, amount)` | Mengimpor mutasi rekening bank dari berkas CSV/MT940 simulasi. |
| `treasury:reconcile` | Harian 07:00 | Idempoten per statement | Menjalankan algoritma pemadanan rekonsiliasi bank 3-tahap (D2). |
| `treasury:mtm` | Harian 18:00 | Idempoten per `(date, contract_id)` | Mengevaluasi Mark-to-Market harian forward hedge contract. |
| `treasury:covenant-test` | Harian 08:00 & 20:00 | Read-only / Alert idempoten | Menguji seluruh covenant fasilitas kredit bank dan eskalasi. |
| `treasury:pool-sweep` | Harian 21:00 (Hari kerja) | Idempoten per `(pool, date)` | Melakukan zero-balancing sweep rekening anak ke induk. |
| `treasury:forecast` | Mingguan (Senin 05:00) | Idempoten per `(scenario, date)` | Menghasilkan ramalan likuiditas kas 13 minggu ke depan (D3). |
| `treasury:export-reports` | Mingguan / Akhir bulan | Idempoten per file output | Mengekspor 8 jenis laporan resmi perbendaharaan dalam format CSV. |
| `treasury:audit` | On-demand / Harian 23:00 | Read-only / Deterministic | Audit dua sumber tabel perbendaharaan vs ledger buku besar. |

---

## 2. Prosedur Pemulihan Insiden Spesifik (Recovery Playbooks)

### Skenario A: Revaluasi Menggunakan Kurs Salah / Keliru Input
- **Gejala**: Jurnal revaluasi akhir periode telah diposting ke ledger menggunakan kurs penutupan yang salah (misal salah ketik digit kurs).
- **Prosedur Pemulihan**:
  1. Jangan mengubah atau mengedit baris tabel revaluasi lama (prinsip append-only).
  2. Daftarkan versi kurs koreksi baru melalui `recordExchangeRate` dengan nomor versi yang lebih tinggi (`version + 1`).
  3. Jalankan kembali perintah:
     ```bash
     php artisan treasury:fx-revalue YYYY-MM --force-recalculate
     ```
  4. Sistem akan secara otomatis:
     - Memposting jurnal koreksi pembalik atas selisih kurs lama vs baru.
     - Memperbarui revaluasi snapshot dengan nomor versi kurs yang benar.
     - Memperbarui target jurnal pembalik awal periode berikutnya.

### Skenario B: Rekonsiliasi Bank Mengalami Kebuntuan (*Stuck*)
- **Gejala**: Terdapat baris mutasi bank yang salah terpadankan pada Tahap 2 (Toleransi) sehingga mutasi lain yang sebenarnya cocok menjadi terblokir.
- **Prosedur Pemulihan**:
  1. Buka antarmuka rekonsiliasi bank pada mutasi yang bermasalah.
  2. Klik tombol **Unmatch** dengan menyertakan alasan operasional resmi (misal: "Salah cocok toleransi; pasangan mutasi sebenarnya ada di batch berikutnya").
  3. Sistem membatalkan tautan rekonsiliasi dan mengembalikan status mutasi bank serta mutasi buku kas ke antrean `unmatched`.
  4. Jalankan kembali pemadanan manual atau re-run command:
     ```bash
     php artisan treasury:reconcile --account-id=<UUID>
     ```

### Skenario C: Kegagalan Jurnal Pembalik Awal Periode (*Reversal Failure*)
- **Gejala**: Perintah `treasury:reverse-revaluations` gagal berjalan pada tanggal 1 akibat database maintenance atau server restart.
- **Prosedur Pemulihan**:
  1. Jalankan perintah secara manual segera setelah server kembali aktif:
     ```bash
     php artisan treasury:reverse-revaluations --period=YYYY-MM
     ```
  2. Perintah ini aman dijalankan kapan saja karena bersifat idempoten penuh: sistem memeriksa bendera `is_reversed = false` dan kunci idempotensi `IDEM:TRS:REVAL_REV:{period}:{lot_id}` sebelum memposting.

### Skenario D: Diskrepansi pada Audit Dua Sumber (`treasury:audit` Keluar Kode $\ne 0$)
- **Gejala**: Perintah `php artisan treasury:audit` mengembalikan exit code 1 dan mencantumkan ketidakcocokan saldo rekening kas atau akun derivatif.
- **Prosedur Pemulihan**:
  1. Tinjau pesan keluaran audit untuk mengidentifikasi ID entitas atau akun ledger yang berselisih.
  2. Periksa apakah terdapat transaksi valas yang belum selesai diposting dua kaki (*unbalanced transaction*).
  3. Lakukan query saldo ledger akun terkait vs total subledger lot valas `trs_fx_lots`.
  4. Jika terdapat transaksi menggantung akibat kegagalan worker antrean, jalankan rekonsiliasi ulang dan kompensasi saldo.

### Skenario E: Peringatan Dini Pelanggaran Covenant Utang Bank
- **Gejala**: Notifikasi TaskInbox otomatis masuk mengindikasikan rasio DER mendekati plafon batas (> 90% dari `max_der_permil`).
- **Prosedur Pemulihan**:
  1. Segera hentikan seluruh permintaan penarikan fasilitas kredit (`DrawFacilityAction`) baru pada fasilitas terkait.
  2. Lakukan koordinasi dengan tim Enterprise Finance dan Tax untuk verifikasi posisi modal ekuitas entitas.
  3. Jika terdapat kas surplus di entitas anak lain, lakukan sweeping kas pool untuk mempercepat amortisasi utang berbunga tinggi.
  4. Bila drawdown mutlak dibutuhkan untuk operasional mendesak, mintakan surat penegasan *Covenant Waiver* resmi dari bank sindikasi sebelum memasukkan override approval di sistem.

### Skenario F: Penanganan Mutasi Rekening Koran Bank Korup / Tidak Lengkap
- **Gejala**: File mutasi bank format CSV/MT940 memiliki baris rusak atau checksum saldo awal dan akhir tidak seimbang.
- **Prosedur Pemulihan**:
  1. Parser mutasi bank akan menolak importasi secara atomik (*rollback all*) dan mencatat error ke tabel `trs_bank_recon_errors`.
  2. Minta berkas mutasi pengganti resmi dari portal perbankan e-banking institusi.
  3. Jalankan kembali importasi dengan flag validasi ketat:
     ```bash
     php artisan treasury:import-statement --path=/path/to/statement.csv --verify-balance
     ```

### Skenario G: Kegagalan Penyapuan Kas Notional Cash Pool (Sweep Block)
- **Gejala**: Perintah `treasury:pool-sweep` gagal memindahkan dana karena saldo rekening anak berada di bawah target balance minimum yang diizinkan regulator lokal.
- **Prosedur Pemulihan**:
  1. Sistem secara default menolak sweep yang menyebabkan saldo akhir negatif atau di bawah saldo pagu cadangan.
  2. Periksa batas minimum `target_balance` pada master cash pool `CashPoolAccount`.
  3. Jika terdapat kebutuhan likuiditas mendesak, ajukan transfer pinjaman bilateral antar-perusahaan (*intercompany loan*) khusus yang memiliki persetujuan dewan direksi anak perusahaan.

---

## 3. Checklist Pra-Penutupan Periode Treasury (Month-End Close Checklist)

Sebelum buku besar perbendaharaan ditutup pada akhir bulan buku, manajer Treasury wajib menyelesaikan dan menandatangani 10 langkah verifikasi berikut:

- [ ] **L-1. Kurs Penutupan Resmi**: Memastikan seluruh kurs penutupan valas (`CLOSING`) hari bursa terakhir telah terinput dan diverifikasi sesuai kurs tengah Bank Indonesia / JISDOR.
- [ ] **L-2. Rekonsiliasi Rekening Koran 100%**: Memastikan seluruh mutasi bank telah diimpor dan persentase keterpadanan mutasi mencapai $\ge 99\%$ (sisa pos tak tertandingi telah diinvestigasi).
- [ ] **L-3. Eksekusi Revaluasi Valas (D1)**: Menjalankan `RunMultiCurrencyFxRevaluationAction` untuk seluruh valas aktif dan memverifikasi sertifikat D1 ber-hash SHA-256.
- [ ] **L-4. Valuasi Mark-to-Market Lindung Nilai**: Memastikan seluruh kontrak forward aktif telah diperbarui nilai wajar MTM-nya pada tanggal penutupan.
- [ ] **L-5. Penyelesaian Kontrak Jatuh Tempo**: Memastikan kontrak forward yang jatuh tempo pada bulan berjalan telah berstatus `SETTLED` dan keuntungan/kerugian terealisasi telah dibukukan.
- [ ] **L-6. Verifikasi Kepatuhan Covenant DER**: Menjalankan pengujian covenant kredit bank; memastikan tidak ada fasilitas yang berstatus wanprestasi.
- [ ] **L-7. Penyapuan Akhir Bulan Kas Pool**: Mengeksekusi final monthly sweep untuk konsolidasi bunga intercompany pooling.
- [ ] **L-8. Rekonsiliasi Kas Kecil (Imprest)**: Memastikan seluruh voucher kas kecil telah diverifikasi dan diajukan penggantian dana (*replenishment*).
- [ ] **L-9. Ekspor Paket Laporan Resmi**: Mengekspor 8 berkas CSV laporan keuangan perbendaharaan ke arsip penyimpanan resmi menggunakan `treasury:export-reports`.
- [ ] **L-10. Verifikasi Audit Nol Selisih**: Menjalankan `php artisan treasury:audit` dan memastikan kode keluar adalah `0` (Zero Discrepancy).

---

## 4. Arsitektur Disaster Recovery & Single-Sign Integrity Check

Domain perbendaharaan menerapkan prinsip **Triple-Ledger Guarantee**:
1. **Ledger Immutability**: Baris jurnal yang telah diposting tidak pernah di-UPDATE atau di-DELETE. Koreksi hanya sah melalui jurnal pembalik (*reversing entry*).
2. **Single-Sign Netto Nol**: Seluruh transaksi yang melibatkan multi-mata uang wajib menyeimbangkan akun debit dan kredit secara tepat nol per kode mata uang.
3. **Idempotency Protection**: Setiap aksi finansial wajib menyertakan kunci idempotensi deterministik berformat `IDEM:{MODULE}:{ACTION}:{ENTITY_ID}:{TIMESTAMP_OR_HASH}`.

---

## 5. Panduan Diagnostik 8 Pemeriksaan `treasury:audit` (D48-12)

Saat perintah `php artisan treasury:audit` gagal (exit code `1`), identifikasi nomor pemeriksaan `[Check 1]` hingga `[Check 8]` pada output konsol dan ikuti langkah perbaikan di bawah ini:

| Pemeriksaan | Invarian yang Diuji | Penyebab Kegagalan Umum | Langkah Diagnostik & Remediasi |
|---|---|---|---|
| **Check 1** | $\Sigma$ entri buku besar per transaksi & per mata uang $= 0$ | Entri jurnal parsial akibat transaksi manual di luar `PostingDTO` / `Posting::lines()` | Query `bank_ledger_entries` yang memiliki `SUM(amount) != 0` per `transaction_id`, lalu buat jurnal koreksi penyeimbang. |
| **Check 2** | Konsistensi subledger `trs_credit_facilities`, `trs_credit_facility_drawdowns`, & `trs_forward_contracts` | `drawn_amount_idr` pada fasilitas kredit tidak sama dengan $\Sigma\text{amount\_idr}$ di `trs_credit_facility_drawdowns`, atau `drawn_amount_idr > credit_limit_idr` | Periksa riwayat drawdown fasilitas terkait dan sinkronkan saldo terpakai berdasarkan bukti transaksi buku besar `TRS_FACILITY_DRAW`. |
| **Check 3** | Kelengkapan pasangan revaluasi–pembalik (`trs_revaluations`) | Rekaman pembalik memiliki `reverse_of` ke ID yang tidak ada, atau `gain_loss_idr` pembalik $\neq -\text{gain\_loss\_idr}$ induk | Jalankan `ReverseFxRevaluationAction` ulang atau periksa integritas referensi `reverse_of` pada periode terkait. |
| **Check 4** | Invarian satu keadaan (*single-state*) pada `trs_bank_statements` | Mutasi bank berstatus `is_reconciled = true` tetapi masih memiliki baris `pending` di `trs_bank_reconciliation_unmatched` atau `reconciliation_status = unmatched` | Gunakan `UnmatchBankStatementAction` untuk mereset mutasi yang ambigu, lalu jalankan kembali `php artisan treasury:reconcile`. |
| **Check 5** | Kecocokan nominal `exact match` buku vs bank | Baris `trs_bank_reconciliation_matches` bertipe `exact` memiliki `matched_amount != statement.amount` | Batalkan match yang korup dengan `UnmatchBankStatementAction` dan jalankan rekonsiliasi ulang. |
| **Check 6** | Sisa saldo & carrying IDR $= 0$ untuk lot `exhausted` (`trs_fx_lots`) | Lot valas telah ditandai `exhausted` tetapi `remaining_amount != 0` atau `current_carrying_idr != 0` | Periksa penyelesaian terakhir pada `settleFxLot()` dan pastikan pelepasan carrying cost akhir diposting ke `trs:fx_settlement_clearing:IDR`. |
| **Check 7** | Larangan saldo negatif pada Kas Kecil & akun `allow_negative = false` | Rekening `is_petty_cash = true` di `trs_bank_accounts` memiliki `balance < 0` atau akun beban/kas di `bank_ledger_accounts` bersaldo negatif | Jalankan pengisian kembali dana kas kecil (`replenishImprestFund`) dari rekening operasional induk sebelum mencairkan voucher baru. |
| **Check 8** | Hitung ulang rasio covenant DER (`covenant_der_permil`) | Nilai `covenant_der_permil` pada `trs_credit_facility_drawdowns` menyimpang dari $\lfloor \text{verified\_debt\_idr} \times 1000 / \text{verified\_equity\_idr} \rfloor$ | Verifikasi snapshot utang dan ekuitas grup dari `LoanExposureProvider` pada tanggal penarikan fasilitas. |

---

## 6. Prosedur Operasional Standar (SOP) Harian, Mingguan, & Bulanan

### 6.1 Rutinitas Pagi Meja Perbendaharaan (06:00 – 09:00 WIB)
1. **Verifikasi Impor Rekening Koran H-1 (`06:00 WIB`)**:
   - Pastikan job otomatis `treasury:import-statement` telah memproses seluruh berkas MT940/CSV dari bank koresponden utama.
   - Periksa tabel `trs_statement_import_batches` untuk memastikan `status = completed` dan tidak ada checksum saldo awal/akhir yang meleset.
2. **Eksekusi & Tinjauan Rekonsiliasi Tiga Tahap (`07:00 WIB`)**:
   - Jalankan `php artisan treasury:reconcile` untuk memadankan mutasi bank dengan jurnal buku kas.
   - Tinjau daftar mutasi di `trs_bank_reconciliation_unmatched`:
     - Mutasi berumur $1\text{--}5$ hari: verifikasi apakah bukti kas masuk/keluar dari cabang belum diinput.
     - Mutasi berumur $> 5$ hari: pastikan tugas eskalasi otomatis telah terbuat di `TaskInbox` (`escalation_task_id` terisi) dan ditindaklanjuti oleh supervisor akuntansi.
3. **Pembaruan Kurs Spot & JISDOR (`08:30 WIB`)**:
   - Input kurs spot pembukaan untuk mata uang utama (`USD`, `EUR`, `SGD`, `JPY`) menggunakan pasangan pembilang dan penyebut rasional bulat.
   - Pastikan tidak ada selisih mencolok ($> 5\%$) terhadap kurs penutupan hari sebelumnya kecuali terjadi gejolak pasar terkonfirmasi.

### 6.2 Rutinitas Sore & Penutupan Harian (16:00 – 21:30 WIB)
1. **Valuasi Mark-to-Market (MTM) Kontrak Lindung Nilai (`18:00 WIB`)**:
   - Jalankan `php artisan treasury:mtm` setelah kurs penutupan sore tersedia.
   - Sistem menghitung nilai wajar seluruh kontrak forward berstatus `active`, memosting delta MTM harian ke `trs:hedge_derivative_asset:IDR` atau `trs:hedge_derivative_liability:IDR`, serta mencatat lawan jurnal pada `trs:hedge_mtm_reserve:IDR`.
2. **Pengujian Kepatuhan Covenant Sore (`20:00 WIB`)**:
   - Jalankan `php artisan treasury:covenant-test` untuk mengevaluasi posisi utang berbunga dan ekuitas terkini seluruh entitas grup.
   - Jika terdapat fasilitas dengan rasio DER $> 90\%$ dari batas `max_der_permil`, kirimkan peringatan dini ke CFO grup.
3. **Eksekusi Zero-Balancing Cash Pool Sweep (`21:00 WIB`)**:
   - Jalankan `php artisan treasury:pool-sweep` untuk menyapu kelebihan likuiditas dari rekening operasional anak perusahaan ke rekening header holding (`sub_to_header`), atau menginjeksi likuiditas ke rekening anak yang defisit (`header_to_sub`).
   - Pastikan setiap penyapuan menghasilkan pencatatan pinjaman antar-perusahaan (`IntercompanyLoan`) yang seimbang.

---

## 7. Prosedur Penanganan Kasus Khusus Kontrak Lindung Nilai (Hedge Operations)

### 7.1 Penyelesaian Kas saat Jatuh Tempo (`matured -> settled`)
1. Pada tanggal jatuh tempo (`maturity_date`), pindahkan status kontrak dari `active` ke `matured` melalui `HedgeContractStateMachine::transition($contract, 'matured', $actorId, 'Maturity date reached', $closingRate)`.
2. Sistem secara otomatis memancarkan event `treasury.hedge.matured.v1` ke `EventSpine` dan membuat tugas penyelesaian di `TaskInboxService`.
3. Setelah konfirmasi penyelesaian nilai tukar diterima dari bank counterparty, eksekusi transisi `matured -> settled` dengan menyertakan `closingRate` aktual penyelesaian.
4. `TreasuryLedgerPoster::postHedgeSettlement()` akan membalik cadangan MTM kumulatif dan mengakui keuntungan/kerugian terealisasi bersih pada `trs:fx_realized_gain_loss:IDR`.

### 7.2 Perpanjangan Kontrak Forward (`matured -> rolled`)
1. Apabila eksposur utang impor atau piutang ekspor yang dilindungi mundur jadwal pelunasannya, dealer dapat memperpanjang (*roll-over*) kontrak yang telah `matured`.
2. Buat draf kontrak forward baru (`childContract`) untuk periode jatuh tempo berikutnya.
3. Eksekusi `HedgeContractStateMachine::transition($parentContract, 'rolled', $actorId, 'Roll-over ke periode berikutnya', $closingRate, $childContractId)`.
4. Kontrak induk akan mengunci status `rolled`, menyimpan referensi `rolled_to_contract_id`, dan membalik cadangan MTM terbuka secara bersih.

### 7.3 Pembatalan Dini Kontrak Terikat (`booked -> cancelled`)
1. Jika kontrak yang sudah berstatus `booked` harus dibatalkan karena pembatalan pesanan pembelian impor, bank counterparty umumnya mengenakan biaya penalti pembatalan (*unwind fee*).
2. Hitung penalti pembatalan secara rasional menggunakan `HedgeContractStateMachine::calculateCancellationPenaltyIdr($contract, $unwindRate, $adminFeePermil)`.
3. Eksekusi transisi `booked -> cancelled`; sistem akan memosting biaya penalti ke `trs:hedge_cancellation_cost:IDR` (Debit) dan `trs:bank_clearing:IDR` (Kredit).

---

## 8. Prosedur Pengajuan & Persetujuan Covenant Waiver serta Opname Kas Kecil

### 8.1 Alur Dispensasi Covenant (`CovenantWaiver`)
1. Ketika penarikan fasilitas kredit terblokir oleh `CovenantBreachException` (`TRS-COV-001`), tim perbendaharaan bernegosiasi dengan bank pemberi pinjaman untuk mendapatkan surat persetujuan keringanan sementara (*Waiver Letter*).
2. Dealer mengajukan permohonan waiver di sistem menggunakan `TreasuryCovenantGovernanceService::requestCovenantWaiver()` dengan melampirkan nomor surat referensi bank (`bank_waiver_reference`), plafon DER sementara (`waived_max_der_permil`), dan tanggal masa berlaku (`valid_until`).
3. Kepala Divisi Treasury atau CFO (harus pengguna yang berbeda dari pemohon — prinsip Four-Eyes) memverifikasi dokumen fisik surat bank dan memanggil `approveCovenantWaiver()`.
4. Setelah berstatus `approved`, `DrawFacilityAction` akan menggunakan `waived_max_der_permil` sebagai batas efektif selama tanggal penarikan masih berada dalam periode `valid_until`.

### 8.2 Rekonsiliasi Opname Fisik & Pengisian Kembali Kas Kecil Imprest
1. Setiap akhir pekan atau saat saldo kas kecil mendekati batas minimum, kasir bersama pengawas internal melakukan perhitungan fisik uang tunai di brankas (`physical_cash_counted_idr`).
2. Panggil `ProcessPettyCashVoucherAction::replenishImprestFund()` dengan memasukkan nominal hasil hitung fisik dan daftar ID voucher yang telah dicairkan.
3. Apabila `physical_cash_counted_idr != book_balance_before_idr`, sistem otomatis membukukan selisih kas ke akun `trs:bank_reconciliation_diff:IDR` dan mencatatnya pada `trs_petty_cash_replenishments.cash_count_variance_idr`.
4. Sistem kemudian memosting transfer pengisian kembali dari rekening bank utama ke `trs:petty_cash:IDR` sehingga saldo akhir kas kecil kembali tepat sebesar `imprest_limit`.

---

## 9. Matriks Pemantauan SLA, Metrik Kesehatan & Tanggap Darurat Perbendaharaan

### 9.1 Indikator Kinerja Utama (KPI) & Ambang Peringatan Operasional
| Indikator Kesehatan | Target Normal (Hijau) | Ambang Peringatan (Kuning) | Ambang Kritis / Darurat (Merah) | Tindakan Otomatis Sistem |
|---|---|---|---|---|
| **Persentase Rekonsiliasi H-1** | $\ge 99\%$ baris cocok | $95\%\text{--}98.9\%$ baris cocok | $< 95\%$ baris cocok | Terbitkan tiket eskalasi prioritas tinggi di `TaskInboxService`. |
| **Umur Maksimum Pos Unmatched (`aging_days`)** | $0\text{--}2$ hari kerja | $3\text{--}5$ hari kerja | $> 5$ hari kerja | Isi `escalation_task_id` dan blokir penutupan periode di `FinancialPeriodClosingService`. |
| **Headroom Covenant DER (`max_der - actual_der`)** | $> 250$ permil ($0.25\text{x}$) | $100\text{--}250$ permil | $< 100$ permil atau Breach | Pancarkan `treasury.covenant.breached.v1` dan tolak `DrawFacilityAction`. |
| **Rasio Saldo Kas Kecil terhadap `imprest_limit`** | $30\%\text{--}100\%$ pagu | $20\%\text{--}29.9\%$ pagu | $< 20\%$ pagu | Panggil `TreasuryTaskInboxNotifier::notifyLowPettyCashBalance()`. |
| **Status Audit Dua Sumber (`treasury:audit`)** | `0` diskrepansi (Exit `0`) | — | $\ge 1$ diskrepansi (Exit `1`) | Gagalkan pipeline CI / gerbang tutup buku dan catat detail `[Check 1..8]` ke log error. |

### 9.2 Prosedur Tanggap Darurat Ketika Bank Koresponden Mengalami Gangguan Sistem (Offline)
1. **Kondisi Pemicu**: Portal perbankan elektronik bank koresponden tidak dapat mengeluarkan berkas MT940 pada jam potong pagi (`06:00 WIB`).
2. **Langkah Mitigasi Sementara**:
   - Gunakan fitur impor CSV cadangan pada `BankStatementImportService::importCsvFile()` dari laporan mutasi intraday yang diunduh oleh pemegang token otorisasi bank.
   - Semua baris mutasi yang diimpor dari CSV cadangan tetap melewati validasi SHA-256 `trs_statement_import_batches` dan pemeriksaan duplikasi `(bank_account_id, reference_no, transaction_date, amount)`.
   - Ketika berkas MT940 resmi akhirnya diterima pada sore hari, operator dapat mengimpor berkas MT940 tersebut tanpa khawatir terjadi penggandaan baris, karena `BankStatementImportService` otomatis melewati baris yang sudah ada (`skipped_duplicate_lines`).

### 9.3 Prosedur Audit Forensik Mandiri Sebelum Tutup Buku Kuartalan
Jalankan rangkaian perintah verifikasi berikut secara berurutan pada terminal produksi atau staging untuk memastikan kesiapan penuh:
```bash
# 1. Impor dan rekonsiliasi seluruh rekening aktif
php artisan treasury:reconcile

# 2. Evaluasi nilai wajar MTM seluruh kontrak derivatif forward aktif
php artisan treasury:mtm

# 3. Uji kepatuhan covenant seluruh fasilitas kredit bank
php artisan treasury:covenant-test

# 4. Jalankan penyapuan kas zero-balancing harian
php artisan treasury:pool-sweep

# 5. Hasilkan proyeksi likuiditas 13 minggu untuk 3 skenario
php artisan treasury:forecast --scenario=base
php artisan treasury:forecast --scenario=optimistic
php artisan treasury:forecast --scenario=pessimistic

# 6. Ekspor ke-8 laporan resmi CSV perbendaharaan
php artisan treasury:export-reports --type=all

# 7. Pastikan audit 8 pemeriksaan dua sumber menghasilkan 0 selisih
php artisan treasury:audit
```



