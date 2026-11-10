# Definition of Ready (DoR) — Fase 52: Intercompany & Transfer Pricing

**ID Fase**: Fase 52  
**Modul Pemilik**: `Intercompany` (`ic_`)  
**Tautan Rencana**: `rencana-gelombang-8-25.md` (§W8-P5)  
**Target Garis Kode (B1)**: ≥ 7.000 baris logika murni (Target RAB: ≥ 7.350, margin ≥ 5%)  
**Target Dokumentasi (B3)**: ≥ 1.500 baris tulis-tangan (Target RAB: ≥ 1.650, margin ≥ 10%)  

---

## 1. Identitas & Ruang Lingkup
- **Nama Modul**: Intercompany
- **Prefiks Tabel**: `ic_`
- **Prefiks Akun Ledger**: `ic:` (dan multi-entitas segmen sesuai W8-D61)
- **Roles Berhak**: `admin`, `auditor`, `legal`, `treasury`
- **Tingkat Simulasi**: S2 (OECD Transfer Pricing Guidelines & PMK 172/2023 disimulasikan deterministik; dokumen pabean PEB/PIB adalah fixture deterministik).
- **Tier Skala**: T1 (≥ 6 entitas legal grup, ≥ 120 transaksi mirror, ≥ 25 transfer pricing rules, ≥ 15 pinjaman antar-entitas beserta skedul bunga efektif & PPh 23, ≥ 50 eliminasi konsolidasi bilateral, ≥ 8 entitas NCI, laporan segmen terkonsolidasi penuh).

---

## 2. Rencana Anggaran Baris (RAB) — Tabel T-2

| Lapisan | Rentang Rencana (Baris) | Penjelasan Komponen |
|---|---|---|
| **Algoritma & Value Object** | 1.100–1.600 | `TransferPricingMarkupCalculator` (D4: OECD/PMK 172 IQR & median), `BilateralEliminationMatcher` (D5: Bilateral reciprocal matching & tolerance), `IntercompanyAmortizationScheduler` (D6: Effective interest amortization, withholding tax PPh 23 15% / 26 20%), rational rate objects, sertifikat & verifier per algoritma. |
| **State Machine** | 600–900 | `IntercompanyTransactionStateMachine` (8 status: `draft`, `submitted`, `arm_length_validated`, `posted_seller`, `posted_buyer`, `reconciled`, `eliminated`, `reversed`), Guard rules, Transition table, dan `IntercompanyTransactionHistory` append-only. |
| **Aplikasi (Action & Service)** | 1.800–2.500 | Transaksi mirror atomik (`CreateMirroredTransactionAction`), Validasi margin arm's length (`ValidateArmsLengthMarginAction`), Penyesuaian TP akhir tahun (`ExecuteTpTrueUpAdjustmentAction`), Pembuatan draf TP Doc Local/Master file (`GenerateTpDocumentationAction`), Penerbitan pinjaman intercompany (`IssueIntercompanyLoanAction`), Pembayaran & amortisasi bunga pinjaman (`AccrueAndPayIntercompanyInterestAction`), Alokasi biaya bersama (`AllocateCostSharingAction`), Mutasi aset tetap (`TransferIntercompanyAssetAction`), Pengiriman stok logistik lintas batas (`TransferIntercompanyStockAction`), Jurnal eliminasi bilateral (`GenerateBilateralEliminationJournalAction`), Eliminasi laba inventori belum terealisasi (`EliminateUnrealizedInventoryProfitAction`), Perhitungan bagi hasil & dividen NCI (`CalculateNciShareAction`, `DistributeNciDividendAction`), Translasi laporan valas anak FCTR (`TranslateSubsidiaryFinancialsAction`), Konsolidasi pelaporan segmen (`GenerateConsolidatedSegmentReportAction`). |
| **Integrasi & Kontrak** | 500–800 | Kontrak `SubledgerTotalProvider`, spine integration, task inbox for discrepancies, approval engine. |
| **Posting Ledger** | 600–900 | `IntercompanyLedgerPoster`, definisi akun di `IntercompanyAccounts`, enum `IntercompanyTransactionTypes`, pembuat entri dua kaki seimbang fungsional IDR dan mata uang asing tanpa float. |
| **HTTP (Controller, FormRequest, Policy)** | 700–1.100 | Controller tipis (`IntercompanyTransactionController`, `TransferPricingController`, `IntercompanyLoanController`, `ConsolidationEliminationController`, `CostSharingController`, `AssetTransferController`, `SegmentReportingController`), FormRequests dengan validasi ketat, Policy otorisasi anti-IDOR. |
| **Command & Laporan CSV** | 600–1.000 | Scheduled console commands (`ic:accrue-interest`, `ic:tp-trueup`, `ic:eliminate-period`, `ic:allocate-costs`, `ic:fctr-translate`), dual-source audit command `group:audit`, serta 8 modul exporter CSV. |
| **Model & Migrasi** | 500–800 | Model Eloquent dengan proteksi append-only dan casting integer minor units. |
| **Total Target B1** | **7.000–9.500** | Memenuhi ambang batas keras B1 ≥ 7.000 |

---

## 3. Skema Basis Data (`ic_*`)

1. **`ic_entities`**: Master entitas legal grup (`id`, `entity_code`, `legal_name`, `country_code`, `functional_currency`, `tax_id`, `ownership_percent_permille`, `is_parent`, `is_active`).
2. **`ic_transactions`**: Transaksi intercompany antar-entitas (`id`, `transaction_code`, `selling_entity_id`, `buying_entity_id`, `description`, `currency`, `amount_minor`, `amount_functional_idr`, `exchange_rate_numerator`, `exchange_rate_denominator`, `sales_invoice_ref`, `purchase_bill_ref`, `tp_rule_id`, `status`, `reconciled_at`, `eliminated_at`, `created_by`, `approved_by`).
3. **`ic_transaction_histories`**: Riwayat transisi state machine append-only (`id`, `transaction_id`, `from_status`, `to_status`, `reason`, `operator_id`, `created_at`).
4. **`ic_loans`**: Master fasilitas pinjaman antar-entitas (`id`, `loan_agreement_number`, `lender_entity_id`, `borrower_entity_id`, `currency`, `principal_minor`, `interest_rate_bps`, `spread_bps`, `withholding_tax_rate_bps`, `start_date`, `due_date`, `repaid_principal_minor`, `status`, `tp_doc_reference`).
5. **`ic_loan_schedules`**: Jadwal amortisasi bunga efektif (`id`, `loan_id`, `installment_number`, `due_date`, `beginning_balance_minor`, `principal_due_minor`, `interest_due_minor`, `tax_withheld_minor`, `net_interest_minor`, `ending_balance_minor`, `payment_status`, `paid_at`, `ledger_transaction_id`).
6. **`ic_transfer_pricing_rules`**: Aturan penentuan harga transfer (`id`, `rule_code`, `product_category`, `tp_method`, `min_margin_bps`, `max_margin_bps`, `median_margin_bps`, `benchmark_industry_source`, `valid_from`, `valid_to`, `is_active`).
7. **`ic_tp_benchmarks`**: Data pembanding pasar wajar OECD/PMK 172 (`id`, `rule_id`, `comparator_name`, `financial_year`, `operating_margin_bps`, `gross_margin_bps`, `source_dataset`).
8. **`ic_tp_true_ups`**: Log penyesuaian margin akhir tahun (`id`, `fiscal_year`, `entity_id`, `category`, `actual_margin_bps`, `target_median_bps`, `adjustment_amount_idr`, `status`, `ledger_transaction_id`).
9. **`ic_tp_documents`**: Draf dokumentasi TP Local & Master File (`id`, `document_type`, `fiscal_year`, `entity_id`, `far_analysis_json`, `benchmark_summary_json`, `status`, `generated_at`).
10. **`ic_asset_transfers`**: Mutasi aset tetap antar-entitas (`id`, `transfer_code`, `asset_id`, `source_entity_id`, `target_entity_id`, `net_book_value_idr`, `accumulated_depreciation_idr`, `transfer_price_idr`, `gain_loss_idr`, `status`, `ledger_transaction_id`).
11. **`ic_stock_transfers`**: Pengiriman stok barang dagang lintas batas (`id`, `shipment_code`, `source_entity_id`, `target_entity_id`, `customs_peb_number`, `customs_pib_number`, `cif_value_idr`, `import_duty_idr`, `import_vat_idr`, `status`, `ledger_transaction_id`).
12. **`ic_cost_sharing_agreements`**: Perjanjian pembagian biaya manajemen/bersama (`id`, `agreement_code`, `agreement_name`, `provider_entity_id`, `allocation_basis`, `service_category`, `markup_bps`, `is_active`).
13. **`ic_cost_sharing_allocations`**: Eksekusi alokasi biaya periodik (`id`, `agreement_id`, `period`, `beneficiary_entity_id`, `allocation_ratio_permille`, `allocated_cost_idr`, `markup_amount_idr`, `total_billed_idr`, `ledger_transaction_id`).
14. **`ic_elimination_entries`**: Jurnal eliminasi konsolidasi berimbang (`id`, `elimination_code`, `period`, `elimination_type`, `selling_entity_id`, `buying_entity_id`, `debit_account`, `credit_account`, `amount_idr`, `status`, `ledger_transaction_id`).
15. **`ic_unrealized_inventory_profits`**: Penelusuran laba antar-perusahaan dalam persediaan akhir (`id`, `period`, `selling_entity_id`, `buying_entity_id`, `product_id`, `inventory_balance_idr`, `unrealized_profit_ratio_permille`, `unrealized_profit_idr`, `status`, `ledger_transaction_id`).
16. **`ic_subsidiary_nci`**: Kepemilikan non-pengendali anak perusahaan (`id`, `subsidiary_entity_id`, `parent_ownership_permille`, `nci_ownership_permille`, `net_income_idr`, `nci_share_net_income_idr`, `period`).
17. **`ic_nci_dividends`**: Pembagian dividen kepada minoritas (`id`, `subsidiary_entity_id`, `declaration_date`, `total_dividend_idr`, `nci_dividend_idr`, `status`, `ledger_transaction_id`).

---

## 4. Akun Ledger & Tabel Posting Intercompany

Setiap transaksi mematuhi konvensi KONSEP §A2.1 (Debit -, Credit +, Σ = 0 per mata uang dan fungsional IDR). Kunci Idempotensi: `IDEM:IC:{AKSI}:{ID}`.

| No | Kejadian Uang | Akun Debit (-) | Akun Kredit (+) |
|---|---|---|---|
| 1 | Transaksi Mirror: Penjual (Revenue & AR) | `ic:ar:{pembeli}:{penjual}:{ccy}` | `ic:sales:{penjual}:{ccy}` |
| 2 | Transaksi Mirror: Pembeli (Cost & AP) | `ic:cogs:{penjual}:{pembeli}:{ccy}` | `ic:ap:{penjual}:{pembeli}:{ccy}` |
| 3 | Pembayaran Settlement Intercompany | `ic:ap:{penjual}:{pembeli}:{ccy}` | `trs:bank_clearing:{pembeli}:{ccy}` (dan kas masuk di penjual) |
| 4 | Penarikan Pinjaman Intercompany (Lender) | `ic:loan_receivable:{peminjam}:{pemberi}:{ccy}` | `trs:bank_clearing:{pemberi}:{ccy}` |
| 5 | Penerimaan Pinjaman Intercompany (Borrower) | `trs:bank_clearing:{peminjam}:{ccy}` | `ic:loan_payable:{pemberi}:{peminjam}:{ccy}` |
| 6 | Akrual Bunga Pinjaman (Borrower) | `ic:interest_expense:{pemberi}:{peminjam}:{ccy}` | `ic:interest_payable:{pemberi}:{peminjam}:{ccy}` + `ic:withholding_tax_payable:{peminjam}:{ccy}` |
| 7 | Pengakuan Pendapatan Bunga (Lender) | `ic:interest_receivable:{peminjam}:{pemberi}:{ccy}` | `ic:interest_income:{peminjam}:{pemberi}:{ccy}` |
| 8 | Alokasi Biaya Bersama (Management Fee) | `ic:management_fee_expense:{pemberi}:{penerima}:IDR` | `ic:cost_sharing_recovery:{penerima}:{pemberi}:IDR` |
| 9 | Penyesuaian TP Akhir Tahun (True-up Debit Penjual) | `ic:tp_adjustment_expense:{penjual}:IDR` | `ic:ap:{penjual}:{pembeli}:IDR` |
| 10 | Mutasi Aset Tetap Antar-Entitas (Penerima) | `ast:fixed_assets:{penerima}:IDR` + `ast:accum_depr:{pengirim}:IDR` | `ast:fixed_assets:{pengirim}:IDR` + `ic:ap:{pengirim}:{penerima}:IDR` |
| 11 | Eliminasi Resiprokal Saldo Piutang/Hutang | `ic:ap:{penjual}:{pembeli}:IDR` | `ic:ar:{pembeli}:{penjual}:IDR` |
| 12 | Eliminasi Penjualan & Beban Pokok Omzet | `ic:sales:{penjual}:IDR` | `ic:cogs:{penjual}:{pembeli}:IDR` |
| 13 | Eliminasi Laba Persediaan Belum Terealisasi | `ic:unrealized_profit_reserve:{penjual}:IDR` | `ic:inventory_adj:{pembeli}:IDR` |
| 14 | Cadangan Selisih Translasi Valas Anak (FCTR) | `ic:fctr_reserve:{anak}:IDR` | `ic:equity_consolidation:{anak}:IDR` |
| 15 | Distribusi Bagian Laba Non-Controlling Interest | `ic:nci_profit_share:{anak}:IDR` | `ic:nci_equity:{anak}:IDR` |
| 16 | Pembayaran Dividen NCI ke Pemegang Saham Luar | `ic:nci_equity:{anak}:IDR` | `trs:bank_clearing:{anak}:IDR` |

---

## 5. Algoritma Baru Bersertifikat (T-4)

1. **`TransferPricingMarkupCalculator` (D4)**:
   - Rumus IQR OECD: Urutkan margin benchmark, hitung $Q_1$, Median, $Q_3$.
   - Jika margin faktual $< Q_1$ atau $> Q_3$, sesuaikan harga transfer ke Median.
   - Sertifikat: `TransferPricingMarkupCertificate` + `TransferPricingMarkupVerifier`.

2. **`BilateralEliminationMatcher` (D5)**:
   - Mencocokkan saldo timbal balik AR vs AP dan Sales vs COGS antar entitas.
   - Deteksi selisih rekonsiliasi (timing vs rate difference) dengan batas toleransi absolut nol pada tanggal cut-off.
   - Sertifikat: `BilateralEliminationCertificate` + `BilateralEliminationVerifier`.

3. **`IntercompanyAmortizationScheduler` (D6)**:
   - Perhitungan amortisasi bunga efektif pinjaman intercompany dengan penentuan spread arm's length.
   - Perhitungan otomatis pemotongan PPh Pasal 23 (15%) untuk entitas domestik atau PPh Pasal 26 (20% atau tarif P3B/tax treaty) untuk entitas luar negeri.
   - Sertifikat: `IntercompanyAmortizationCertificate` + `IntercompanyAmortizationVerifier`.

---

## 6. Keputusan Arsitektur Terkait (ADR)
- **ADR-0070**: Standarisasi Master Entitas Legal Grup & Segmen Konsolidasi Multi-Entitas.
- **ADR-0071**: State Machine 8 Status Siklus Transaksi Intercompany Mirroring.
- **ADR-0072**: Metodologi OECD Transfer Pricing & Penyesuaian Arm's Length Interkuartil (D4).
- **ADR-0073**: Amortisasi Pinjaman Intercompany Bunga Efektif & Pemotongan Pajak PPh 23/26 (D6).
- **ADR-0074**: Jurnal Eliminasi Bilateral Otomatis & Toleransi Nol Rekonsiliasi (D5).
- **ADR-0075**: Eliminasi Laba Antar-Perusahaan dalam Persediaan Akhir.
- **ADR-0076**: Translasi Valas Laporan Keuangan Anak & FCTR Konsolidasi.
- **ADR-0077**: Pengakuan Kepentingan Non-Pengendali (NCI) & Dividen Minoritas.
- **ADR-0078**: Transfer Aset Tetap & Stok Lintas Batas Kepabeanan Terintegrasi.

