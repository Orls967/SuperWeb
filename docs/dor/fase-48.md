# Definition of Ready (DoR) — Fase 48: Multi-Currency & Treasury

**ID Fase**: Fase 48  
**Modul Pemilik**: `Treasury` (`trs_`)  
**Tautan Rencana**: `rencana-gelombang-8-25.md` (§W8-P5)  
**Target Garis Kode (B1)**: ≥ 7.000 baris logika murni  
**Target Dokumentasi (B3)**: ≥ 1.500 baris tulis-tangan  

---

## 1. Identitas & Ruang Lingkup
- **Nama Modul**: Treasury
- **Prefiks Tabel**: `trs_`
- **Prefiks Akun Ledger**: `trs:` (dan multi-entitas segmen sesuai W8-D61)
- **Roles Berhak**: `treasury`, `auditor`, `admin`
- **Tingkat Simulasi**: S2 (Kurs Bank Indonesia disimulasikan secara deterministik berversi; feed MT940 dan CSV mutasi bank merupakan fixture deterministik).
- **Tier Skala**: T1 (≥ 8 mata uang, ≥ 400 kurs harian berversi, ≥ 120 lot saldo valas, ≥ 600 baris mutasi bank & 600 baris buku, ≥ 30 HedgeContract mencakup 8 status SM, ≥ 6 fasilitas kredit dengan covenant DER/ICR, ≥ 4 cash pools, riwayat arus kas 52 minggu).

---

## 2. Rencana Anggaran Baris (RAB) — Tabel T-2

| Lapisan | Rentang Rencana (Baris) | Penjelasan Komponen |
|---|---|---|
| **Algoritma & Value Object** | 1.000–1.500 | `FxRevaluationCalculator` (D1), `BankReconciliationMatcher` (D2), `SeasonalCashForecaster` (D3), `RationalRate`, `RationalNumber`, serta Certificate & Verifier per algoritma. |
| **State Machine** | 500–900 | `HedgeContractStateMachine` (8 status: `draft`, `approved`, `booked`, `active`, `matured`, `settled`, `rolled`, `cancelled`), Guard rules, Transition table, dan `ForwardContractHistory` append-only. |
| **Aplikasi (Action & Service)** | 1.600–2.400 | Revaluasi valas (`RunFxRevaluationAction`), Pembalik otomatis (`ReverseRevaluationsAction`), Pencocokan mutasi bank 3-tahap (`ReconcileBankStatementsAction`), Pembatalan rekonsiliasi (`UnmatchBankStatementAction`), Forecast kas 13-minggu (`GenerateSeasonalCashForecastAction`), Siklus lindung nilai (`BookForwardContractAction`, `SettleForwardContractAction`, `RollForwardContractAction`), Penarikan fasilitas kredit (`DrawFacilityAction`), Notional & physical cash pool sweep (`ExecuteCashPoolSweepAction`), Petty cash voucher management. |
| **Integrasi & Kontrak** | 400–700 | Kontrak `ExchangeRateLookup`, `FxMonetaryItemFeed`, `LoanExposureProvider` (di modul Finance), Event publisher via OutboxBus & EventSpine, ApprovalEngine integration, TaskInboxService integration untuk anomali mutasi dan pelanggaran covenant. |
| **Posting Ledger** | 500–800 | `TreasuryLedgerPoster`, definisi akun di `TreasuryAccounts`, enum `TreasuryTransactionTypes`, pembuat entri dua kaki seimbang fungsional IDR dan mata uang asing tanpa float. |
| **HTTP (Controller, FormRequest, ViewModel)** | 600–1.000 | Controller tipis (`TreasuryPositionController`, `ExchangeRateController`, `ReconciliationController`, `CashForecastController`, `HedgeContractController`, `CreditFacilityController`, `CashPoolController`), FormRequests dengan validasi ketat, Policy otorisasi anti-IDOR. |
| **Command & Laporan CSV** | 500–900 | Scheduled console commands (`treasury:fx-revalue`, `treasury:reverse-revaluations`, `treasury:reconcile`, `treasury:mtm`, `treasury:covenant-test`, `treasury:pool-sweep`, `treasury:forecast`), dual-source audit command `treasury:audit`, serta 8 modul exporter CSV. |
| **Model & Migrasi** | 300–500 | Model Eloquent dengan proteksi append-only dan casting integer minor units. |
| **Total Target B1** | **7.000–9.200** | Memenuhi ambang batas keras B1 ≥ 7.000 |

---

## 3. Skema Basis Data (`trs_*`)

1. **`trs_currencies`**: Master kode mata uang ISO 4217 (`code`, `name`, `symbol`, `minor_units`, `is_active`).
2. **`trs_exchange_rates`**: Master kurs harian berversi tak dapat diubah (`id`, `from_currency`, `to_currency`, `rate_date`, `rate_type`, `rate_numerator`, `rate_denominator`, `source`, `version`, `created_by`, `unique(from, to, date, type, version)`).
3. **`trs_fx_lots`**: Subledger lot moneter valas (`id`, `source_module`, `source_entity_id`, `currency`, `original_foreign_amount`, `remaining_foreign_amount`, `original_rate_numerator`, `original_rate_denominator`, `carrying_functional_idr`, `lot_date`, `status`).
4. **`trs_revaluations`**: Log revaluasi akhir periode (`id`, `period`, `lot_id`, `currency`, `foreign_amount`, `old_carrying_idr`, `revalued_carrying_idr`, `gain_loss_idr`, `reversal_period`, `is_reversed`, `ledger_transaction_id`).
5. **`trs_bank_accounts`**: Master rekening bank & kas kecil (`id`, `account_number`, `bank_name`, `currency`, `balance`, `is_petty_cash`, `is_active`, `gl_account_code`).
6. **`trs_bank_statements`**: Feed mutasi bank impor CSV/MT940 (`id`, `bank_account_id`, `transaction_date`, `reference_no`, `amount`, `description`, `reconciliation_status`, `matched_stage`, `matched_at`).
7. **`trs_bank_reconciliation_matches`**: Bukti rekonsiliasi bank (`id`, `statement_id`, `ledger_entry_id`, `match_stage`, `discrepancy_amount`, `approved_by`, `approved_at`, `status`).
8. **`trs_cash_forecasts`**: Hasil ramalan 13 minggu berversi (`id`, `scenario`, `forecast_date`, `week_number`, `projected_inflow_idr`, `projected_outflow_idr`, `net_cash_flow_idr`, `closing_balance_idr`, `symmetric_mape_permille`).
9. **`trs_forward_contracts`**: Kontrak lindung nilai forward (`id`, `contract_number`, `currency`, `notional_foreign_amount`, `contract_rate_numerator`, `contract_rate_denominator`, `market_rate_numerator`, `market_rate_denominator`, `maturity_date`, `mtm_gain_loss_idr`, `status`, `rolled_to_contract_id`).
10. **`trs_forward_contract_histories`**: Riwayat append-only transisi status state machine (`id`, `forward_contract_id`, `from_status`, `to_status`, `actor_id`, `reason`, `occurred_at`).
11. **`trs_credit_facilities`**: Plafon kredit & covenant bank (`id`, `facility_code`, `bank_name`, `credit_limit_idr`, `drawn_amount_idr`, `interest_rate_permille`, `max_der_permille`, `min_icr_permille`, `status`).
12. **`trs_credit_facility_drawdowns`**: Riwayat penarikan fasilitas (`id`, `credit_facility_id`, `drawdown_date`, `amount_idr`, `calculated_der_permille`, `is_waiver_approved`, `approver_id`).
13. **`trs_cash_pools`**: Konfigurasi pooling kas antar-rekening/anak (`id`, `pool_name`, `header_account_id`, `sub_account_id`, `target_balance_idr`, `interest_rate_permille`, `is_active`).
14. **`trs_cash_pool_sweeps`**: Catatan eksekusi sweep harian zero-balancing (`id`, `cash_pool_id`, `sweep_date`, `direction`, `swept_amount_idr`, `intercompany_loan_id`, `ledger_transaction_id`).
15. **`trs_petty_cash_vouchers`**: Kas kecil pengeluaran & pengisian kembali (`id`, `bank_account_id`, `voucher_no`, `voucher_date`, `amount_idr`, `purpose`, `status`).

---

## 4. Akun Ledger & Tabel Posting (D48-10)

Setiap transaksi mematuhi konvensi KONSEP §A2.1 (Debit -, Credit +, Σ = 0 per mata uang dan fungsional IDR). Kunci Idempotensi: `IDEM:TRS:{AKSI}:{ID}`.

| No | Kejadian Uang | Akun Debit (-) | Akun Kredit (+) |
|---|---|---|---|
| 1 | Revaluasi aset valas (Untung) | `trs:fx_reval_asset_adj:IDR` | `trs:fx_unrealized_gain_loss:IDR` |
| 2 | Revaluasi aset valas (Rugi) | `trs:fx_unrealized_gain_loss:IDR` | `trs:fx_reval_asset_adj:IDR` |
| 3 | Revaluasi liabilitas valas (Rugi, kurs naik) | `trs:fx_unrealized_gain_loss:IDR` | `trs:fx_reval_liability_adj:IDR` |
| 4 | Revaluasi liabilitas valas (Untung, kurs turun) | `trs:fx_reval_liability_adj:IDR` | `trs:fx_unrealized_gain_loss:IDR` |
| 5 | Pembalik revaluasi awal periode | *Kebalikan persis entri 1–4* | *Kebalikan persis entri 1–4* |
| 6 | Realized FX gain saat settlement | `trs:fx_settlement_clearing:IDR` | `trs:fx_realized_gain_loss:IDR` |
| 7 | Realized FX loss saat settlement | `trs:fx_realized_gain_loss:IDR` | `trs:fx_settlement_clearing:IDR` |
| 8 | MTM forward naik (Aset derivatif) | `trs:derivative_asset:IDR` | `trs:derivative_gain_loss:IDR` |
| 9 | MTM forward turun (Liabilitas derivatif) | `trs:derivative_gain_loss:IDR` | `trs:derivative_liability:IDR` |
| 10 | Settlement forward jatuh tempo (Kas masuk) | `trs:bank_clearing:IDR` | `trs:derivative_asset:IDR` (+ realized di `trs:derivative_gain_loss:IDR`) |
| 11 | Biaya pembatalan hedge | `trs:hedge_cancellation_cost:IDR` | `trs:bank_clearing:IDR` |
| 12 | Selisih rekonsiliasi bank disetujui | `trs:bank_reconciliation_diff:IDR` | `trs:bank_clearing:IDR` (arah dibalik jika surplus) |
| 13 | Penarikan fasilitas kredit bank | `trs:bank_clearing:IDR` | `trs:facility_payable:IDR` |
| 14 | Akrual bunga fasilitas periodik | `trs:interest_expense:IDR` | `trs:interest_payable:IDR` |
| 15 | Sweep pooling zero-balancing ke induk | `ic:loan_receivable:{induk}:{anak}:IDR` | `trs:pool_sweep_clearing:{anak}:IDR` |

---

## 5. Algoritma Baru Bersertifikat (T-4)

1. **`FxRevaluationCalculator` (D1)**:
   - Rumus: $\text{Revalued} = \text{round\_half\_away\_from\_zero}(\text{balance\_minor} \times \frac{n}{d \times 10^{\text{decimals}}})$.
   - Menghitung unrealized gain/loss untuk aset dan liabilitas, serta realized gain/loss proporsional saat pelunasan parsial.
   - Sertifikat: `FxRevaluationCertificate` + `FxRevaluationCertificateVerifier`.

2. **`BankReconciliationMatcher` (D2)**:
   - Tiga tahap: Tahap 1 Exact ($|\Delta \text{amount}| = 0$, reference identik); Tahap 2 Toleransi ($|\Delta \text{amount}| \le 500$ minor, $|\Delta \text{days}| \le 3$); Tahap 3 Many-to-One (himpunan 2..4 mutasi buku yang pas tepat ke 1 mutasi bank, subset size $\le 4$ dari maks 12 kandidat).
   - Sertifikat: `BankReconciliationCertificate` + `BankReconciliationCertificateVerifier`.

3. **`SeasonalCashForecaster` (D3)**:
   - Ramalan 13 minggu dengan siklus musiman 4 minggu ($p=4$).
   - Menghitung rata-rata siklus, indeks musiman permil, deseasonalisasi rasional OLS intersep $a$ dan kemiringan $b$, proyeksi mingguan berlantai tunggal di akhir.
   - Sertifikat: `SeasonalCashForecastCertificate` + `SeasonalCashForecastCertificateVerifier`.

---

## 6. Keputusan Arsitektur Terkait (ADR)
- **W8-D61**: Multi-entitas pada ledger via segmen kode akun `EntityAccountFactory`.
- **W8-D62**: Kurs rasional tak dapat diubah (`RationalRate`, numerator/denominator).
- **W8-D63**: Ledger multi-currency dua kaki berimbang (`trs_fx_lots`).
- **W8-D64**: Revaluasi pos moneter valas & pembalik otomatis awal periode.
- **W8-D65**: Rekonsiliasi bank 3-tahap dan antrean unmatch.
- **W8-D66**: Forecast arus kas 13 minggu musiman (D3).
- **W8-D67**: Lindung nilai forward contract & MTM daily.
- **W8-D68**: Covenant fasilitas bank berbasis permil integer tanpa float.
- **W8-D69**: Cash pooling zero-balancing via pinjaman intercompany arm's length.

---

## 7. Spesifikasi Kelengkapan W8-P5-b.3 (D48-5, D48-11, D48-12)

### 7.1 Kelengkapan Rekening Bank, Rekonsiliasi & Kas Kecil Imprest (D48-5)
1. **Impor Rekening Koran Idempoten (`BankStatementImportService`)**:
   - Mendukung format SWIFT MT940 (`:20:`, `:25:`, `:28C:`, `:60F:`, `:61:`, `:86:`, `:62F:`) serta format CSV standar perbankan korporat.
   - Setiap batch impor direkam ke tabel `trs_statement_import_batches` dengan sidik jari `payload_hash` (SHA-256). Pengiriman ulang berkas yang sama dikenali secara idempoten (`is_duplicate_batch = true`, `imported_count = 0`).
2. **Antrean Unmatched dengan Umur (`aging_days`) & Eskalasi TaskInbox**:
   - Baris mutasi bank yang tidak terpadankan setelah Tahap 1 (Exact), Tahap 2 (Toleransi), dan Tahap 3 (Subset Many-to-One) dicatat di `trs_bank_reconciliation_unmatched` lengkap dengan perhitungan `aging_days` deterministik terhadap `ClockInterface` dan nomor tiket `escalation_task_id` dari `TaskInboxService::createTask()`.
3. **Pembatalan Pemadanan (`UnmatchBankStatementAction`)**:
   - Mewajibkan alasan tertulis (`unmatch_reason`), mencatat ID petugas (`unmatched_by`) dan waktu pembatalan (`unmatched_at`) pada `trs_bank_statements`, serta menghapus rekaman `trs_bank_reconciliation_matches` dalam satu transaksi atomik.
4. **Kas Kecil Sistem Imprest & Opname Fisik (`trs_petty_cash_replenishments`)**:
   - Mendukung pengajuan voucher (`submitVoucher`), persetujuan & pencairan Four-Eyes (`approveAndReimburse`), serta pengisian kembali pagu imprest (`replenishImprestFund`) yang menghitung selisih opname fisik kas (`shortage_overage_idr`) dan memosting jurnal selisih serta jurnal replenishment ke `LedgerService`.

### 7.2 Enam Titik Integrasi Lintas-Modul (D48-11)
1. **(a) Ledger Posting (`TreasuryLedgerPoster` -> `LedgerService`)**:
   - Seluruh 15 kejadian uang pada Tabel D48-10 diposting dengan konvensi single-sign ($\Sigma\text{debit} + \Sigma\text{kredit} = 0$) dan kunci idempotensi deterministik `IDEM:TRS:*`.
2. **(b) Feed Pos Moneter Valas Lintas-Modul (`FxMonetaryItemFeed`)**:
   - Kontrak `Modules\Treasury\Contracts\FxMonetaryItemFeed` diimplementasikan oleh minimal 2 modul sumber eksternal:
     - `Modules\Procurement\Infrastructure\Treasury\ProcurementFxMonetaryItemFeed` (membaca komitmen/PO impor valas terbuka dari `prc_purchase_orders`).
     - `Modules\Trade\Infrastructure\Treasury\TradeFxMonetaryItemFeed` (membaca pesanan impor `trd_import_orders` dan pengapalan ekspor `trd_export_shipments` berdenominasi valas).
   - Keduanya ditag sebagai `'treasury.fx_monetary_item_feed'` pada ServiceProvider masing-masing dan diagregasi oleh `TreasuryCovenantGovernanceService::aggregateAndRevalueCrossModuleFeeds()`.
3. **(c) Integrasi Intercompany untuk Cash Pooling & Pinjaman**:
   - Sweep zero-balancing dan notional cash pooling membentuk kode akun kliring antar-entitas melalui `EntityAccountFactory::forPoolSweepClearing()` dan menautkan `intercompany_loan_id` pada `trs_cash_pool_sweeps`.
4. **(d) Integrasi Finance untuk Eksposur Covenant (`LoanExposureProvider`)**:
   - `DrawFacilityAction`, `DrawCreditFacilityAction`, dan `TestCreditCovenantCommand` membaca total utang dan ekuitas grup melalui `Modules\Finance\Contracts\LoanExposureProvider`.
5. **(e) Integrasi Governance (`ApprovalEngine` & `TaskInboxService`)**:
   - Persetujuan kontrak lindung nilai (`HedgeContractStateMachine`) dan keringanan covenant (`CovenantWaiver` di `trs_covenant_waivers`) menegakkan prinsip Four-Eyes Segregation of Duties (`creator_id != approver_id` / `requested_by != approved_by`).
   - Mutasi bank tak terpadankan, covenant mendekati batas ($\ge 90\%$), kontrak forward jatuh tempo, dan saldo kas kecil menipis ($< 20\%$ pagu imprest) otomatis menerbitkan tugas di `TaskInboxService`.
6. **(f) Tiga Event Resmi di `EventCatalog` & Konsumen Nyata**:
   - `treasury.revaluation.posted.v1` (`TreasuryRevaluationPostedEvent`)
   - `treasury.covenant.breached.v1` (`TreasuryCovenantBreachedEvent`)
   - `treasury.hedge.matured.v1` (`TreasuryHedgeMaturedEvent`)
   - Ketiganya terdaftar di `Modules\Core\Domain\Events\EventCatalog`, dipancarkan melalui `TreasuryEventPublisher`, dan dikonsumsi oleh `TreasuryDomainEventSubscriber`.

### 7.3 Delapan Pemeriksaan Audit Dua Sumber (`treasury:audit` — D48-12)
Perintah `php artisan treasury:audit` memverifikasi 8 invarian dua sumber dan keluar dengan kode `0` hanya bila seluruh pemeriksaan bersih:
1. **Check 1**: $\Sigma$ entri buku besar per transaksi dan per mata uang bernilai tepat nol.
2. **Check 2**: Konsistensi saldo terpakai fasilitas kredit (`drawn_amount_idr`) terhadap jumlah riwayat penarikan subledger (`trs_credit_facility_drawdowns`), batas plafon (`credit_limit_idr`), dan validitas nosional kontrak forward aktif.
3. **Check 3**: Kelengkapan pasangan revaluasi–pembalik (`reverse_of` merujuk ke induk sah dengan `gain_loss_idr` berlawanan tanda persis).
4. **Check 4**: Invarian satu keadaan (*single-state invariant*) pada mutasi bank (`trs_bank_statements` tidak boleh berstatus `is_reconciled = true` sekaligus memiliki antrean `pending` di `trs_bank_reconciliation_unmatched` atau `reconciliation_status = unmatched`).
5. **Check 5**: Kecocokan nominal antara rekaman `trs_bank_reconciliation_matches` bertipe `exact` dengan nilai mutasi di `trs_bank_statements`.
6. **Check 6**: Lot valas berstatus `exhausted` pada `trs_fx_lots` wajib memiliki `remaining_amount = 0` dan `current_carrying_idr = 0`.
7. **Check 7**: Tidak ada saldo negatif pada rekening kas kecil (`is_petty_cash = true`) maupun akun buku besar perbendaharaan ber-atribut `allow_negative = false`.
8. **Check 8**: Rasio covenant tersimpan (`covenant_der_permil`) pada `trs_credit_facility_drawdowns` cocok dengan hasil hitung ulang rasional $\lfloor \text{verified\_debt\_idr} \times 1000 / \text{verified\_equity\_idr} \rfloor$.

---

## 8. Matriks Ketertelusuran Kriteria Penerimaan (Acceptance Criteria Traceability Matrix)

| Sub-Fase | Deskripsi Kriteria Penerimaan | Komponen Implementasi Utama | Pengujian Verifikasi (`tests/Acceptance/Treasury/`) |
|---|---|---|---|
| **48.1** | Skema 15+ tabel `trs_*`, model Eloquent integer minor units, migrasi dengan indeks pendek $\le 64$ karakter | `2026_03_28_000148_create_treasury_tables.php`, `2026_11_12_480300_create_trs_phase48_completeness_tables.php`, `ExchangeRate`, `FxLot`, `ForwardContract`, `CovenantWaiver`, `PettyCashReplenishment`, `StatementImportBatch` | `TreasuryEndToEndLifecycleTest`, `TreasuryDualSourceAuditTest` |
| **48.2** | Master kurs rasional append-only (`RationalRate`), konversi mata uang tanpa float, subledger `trs_fx_lots`, pelunasan parsial/penuh | `RationalRate`, `TreasuryService::recordExchangeRate()`, `convertAmount()`, `settleFxLot()` | `FxRevaluationCalculatorVectorTest`, `TreasuryEndToEndLifecycleTest` |
| **48.3** | Algoritma D1 `FxRevaluationCalculator`, sertifikat SHA-256 `FxRevaluationCertificateVerifier`, revaluasi aset & liabilitas, pembalik otomatis, feed lintas-modul `FxMonetaryItemFeed` | `FxRevaluationCalculator`, `RunFxRevaluationAction`, `RunMultiCurrencyFxRevaluationAction`, `ReverseFxRevaluationAction`, `ProcurementFxMonetaryItemFeed`, `TradeFxMonetaryItemFeed` | `FxRevaluationCalculatorVectorTest`, `TreasuryEndToEndLifecycleTest`, `TreasuryBusinessRulesAndFailureTest` |
| **48.4** | Algoritma D2 `BankReconciliationMatcher` 3 tahap (exact, tolerance, subset), impor MT940/CSV idempoten, antrean unmatched dengan `aging_days` & eskalasi `TaskInbox`, aksi `UnmatchBankStatementAction` | `BankReconciliationMatcher`, `BankStatementImportService`, `ReconcileBankStatementsAction`, `UnmatchBankStatementAction`, `ReconcileBankStatementsCommand` | `BankReconciliationMatcherVectorTest`, `TreasuryEndToEndLifecycleTest`, `TreasuryBusinessRulesAndFailureTest` |
| **48.5** | Algoritma D3 `SeasonalCashForecaster` 13 minggu, dekomposisi musiman 4-mingguan & regresi OLS rasional, 3 skenario (`base`, `optimistic`, `pessimistic`) | `SeasonalCashForecaster`, `SeasonalCashForecastCertificateVerifier`, `GenerateCashForecastAction`, `ForecastCashCommand` | `SeasonalCashForecasterVectorTest`, `TreasuryEndToEndLifecycleTest` |
| **48.6** | State machine `HedgeContractStateMachine` 8 status (`draft`..`cancelled`), Four-Eyes guard, MTM harian, settlement, roll-over, kalkulasi penalti pembatalan, event `treasury.hedge.matured.v1` | `HedgeContractStateMachine`, `BookForwardContractAction`, `MarkToMarketHedgeCommand`, `ForwardContractHistory`, `TreasuryHedgeMaturedEvent` | `TreasuryEndToEndLifecycleTest`, `TreasuryBusinessRulesAndFailureTest` |
| **48.7** | Fasilitas kredit & pengawasan covenant DER/ICR permil bulat, blokir otomatis saat breach + pancaran `treasury.covenant.breached.v1`, alur `CovenantWaiver` Four-Eyes, akrual bunga rasional | `DrawFacilityAction`, `DrawCreditFacilityAction`, `TreasuryCovenantGovernanceService`, `TestCreditCovenantCommand`, `CovenantWaiver` | `TreasuryEndToEndLifecycleTest`, `TreasuryBusinessRulesAndFailureTest` |
| **48.8** | Cash pooling zero-balancing (`sub_to_header` & `header_to_sub`), perlindungan saldo negatif, integrasi pinjaman intercompany, kas kecil sistem imprest & opname fisik | `ExecuteCashPoolSweepAction`, `ProcessPettyCashVoucherAction`, `SweepCashPoolCommand`, `PettyCashReplenishment` | `TreasuryEndToEndLifecycleTest`, `TreasuryBusinessRulesAndFailureTest` |
| **48.9** | Antarmuka HTTP & API tipis, `TreasuryPolicy` RBAC (`treasury`, `auditor`, `admin`), `TreasuryDashboardViewModel`, 8 laporan CSV `TreasuryReportExportService` | `ExchangeRateController`, `ReconciliationController`, `CashForecastController`, `HedgeContractController`, `CreditFacilityController`, `CashPoolController`, `PettyCashController` | `TreasuryRbacAndIdempotencyTest`, `TreasuryEndToEndLifecycleTest` |
| **48.10** | Perintah `treasury:audit` 8 pemeriksaan dua sumber (ledger vs subledger), dokumentasi DoR/Desain/Konsep/Runbook/Panduan/Galat | `AuditTreasuryCommand`, `TreasuryLedgerPoster`, berkas `docs/**/fase-48.md` & `docs/**/treasury-*.md` | `TreasuryDualSourceAuditTest` |



