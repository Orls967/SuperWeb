# Bukti Kondisi Awal Fase 52 (L.0)

**Fase**: Fase 52 — Kerja Sama Internasional II: Intercompany, Transfer Pricing & Konsolidasi Keuangan Grup  
**Waktu Pengambilan**: 2026-11-11T05:36:00+08:00  
**Base Commit**: `fe3bf6e6bd23d882e8011174b88800f55c4880df` (Merge PR #109 [W8-P5.1] ke master)  
**Branch**: `feature/w8-p5-treasury-grup-2`  
**LP_ID**: `sw-w8-p5`

---

## 1. Verifikasi Fondasi Master Terkini Pasca Merge PR #109
Master terkini berada pada commit `fe3bf6e6bd23d882e8011174b88800f55c4880df`.
Modul Treasury (Fase 48) telah beroperasi dengan 43 acceptance & feature tests lulus, 262 architecture tests lulus, 0 pelanggaran ui:lint, dan B1 8.502 baris.

### A. Verifikasi Alat dan Artisan Commands
- `quality:phase-metrics` (tersedia)
- `docs:check`, `docs:drift`, `docs:inventory` (tersedia)
- `ui:lint` (tersedia)
- `group:audit` (tersedia di Intercompany, fixture ada di `GroupAuditCorruptionFixture`)
- `spine:*` commands dari W8-P3 (tersedia)

---

## 2. Tabel Evaluasi Faktual Modul Intercompany Eksisting (Ada / Benar / Salah / Tidak Ada)

| Komponen | Status | Penjelasan & Temuan Faktual |
|---|---|---|
| **Tabel Skema DB** | Ada / Salah | 5 tabel ada (`ic_transactions`, `ic_loans`, `ic_transfer_pricing_rules`, `ic_elimination_entries`, `ic_subsidiary_nci`). **Salah**: `ic_loans` dan `ic_transfer_pricing_rules` serta `ic_subsidiary_nci` memakai kolom `decimal` (risiko float / pelanggaran A3); `ic_transactions` hanya berstatus 3 (`matched`, `settled`, `disputed`); entitas hanya berupa string tanpa master entitas formal; belum ada tabel riwayat status state machine; belum ada tabel mutasi aset/stok logistik (`ic_asset_transfers`, `ic_stock_transfers`); belum ada tabel jadwal amortisasi pinjaman (`ic_loan_schedules`); belum ada tabel cost sharing (`ic_cost_sharing_agreements`, `ic_cost_sharing_allocations`); belum ada tabel laba inventori belum terealisasi (`ic_unrealized_inventory_profits`). |
| **Model Eloquent** | Ada / Salah | 5 model ada (`IntercompanyTransaction`, `IntercompanyLoan`, `TransferPricingRule`, `EliminationEntry`, `SubsidiaryNci`). **Salah**: Cast memakai float/decimal; belum ada proteksi immutabilitas append-only; belum ada relasi terikat ke ledger entries perbankan. |
| **IntercompanyService** | Ada / Salah | 192 baris. **Salah**: Pembuatan kode memakai `Str::random()` (non-deterministik); tanggal memakai `Carbon::today()` (melanggar ClockInterface); validasi margin memakai `float` dan pembagian langsung (bukan aritmetika rasional); tidak memposting ke `LedgerService` pada transaksi umum, pinjaman, dan eliminasi; tidak ada generator dokumentasi TP (Local File / Master File / FAR Analysis); tidak ada mekanisme TP true-up akhir tahun. |
| **IntercompanyPortService** | Ada / Benar Sebagian | 161 baris. Mengimplementasikan `IntercompanyBillingPort` dan `IntercompanyPortInterface`. Memposting transaksi dua kaki ke `LedgerService`, namun terbatas pada billing telekomunikasi/energi tanpa dukungan state machine penuh 8 status dan approval engine. |
| **Akun Ledger Intercompany** | Tidak Ada / Sebagian | Belum ada kelas `IntercompanyAccounts` terpadu dengan aturan single-sign convention (`ic:ar`, `ic:ap`, `ic:sales`, `ic:cogs`, `ic:loan_receivable`, `ic:loan_payable`, `ic:interest_income`, `ic:interest_expense`, `ic:withholding_tax_payable`, `ic:cost_sharing_recovery`, `ic:management_fee_expense`, `ic:fctr_reserve`, `ic:nci_equity`, `ic:nci_profit_share`, `ic:unrealized_profit_reserve`). |
| **State Machine 8 Status** | Tidak Ada | Belum ada state machine formal 8 status (`draft`, `submitted`, `arm_length_validated`, `posted_seller`, `posted_buyer`, `reconciled`, `eliminated`, `reversed`) dengan audit trail transisi dan maker-checker. |
| **3 Algoritma Bersertifikat (D4, D5, D6)** | Tidak Ada | Belum ada: `TransferPricingMarkupCalculator` (D4: OECD/PMK 172 IQR & median), `BilateralEliminationMatcher` (D5: Bilateral reciprocal matching & tolerance), `IntercompanyAmortizationScheduler` (D6: Effective interest amortization, withholding tax PPh 23 15% / 26 20%). |
| **Kontrak Antarmuka** | Tidak Ada / Kurang | Kontrak `SubledgerTotalProvider` belum ada. Belum ada kontrak keterikatan modul logistik & aset. |
| **Perpindahan Aset & Logistik (52.3)** | Tidak Ada | Belum ada mutasi aset tetap antar-entitas (transfer nilai buku & akumulasi penyusutan) dan pengiriman stok lintas batas (PEB/PIB, PPN, bea masuk). |
| **Kepentingan Non-Pengendali NCI (52.5)** | Ada / Salah | Hanya hitungan dasar 1 baris di `IntercompanyService`, tidak ada dividen non-pengendali, tidak ada posting ke ledger ekuitas minoritas. |
| **Pelaporan Segmen Usaha Terkonsolidasi (52.6)** | Tidak Ada | Belum ada laporan laba rugi dan neraca segmen operasi / geografis dengan drill-down ke voucher transaksi anak. |
| **UI Intercompany** | Ada / Kurang | View blade dasar di `resources/views/intercompany/` belum memenuhi tokenisasi DS, belum ada form state machine, belum ada visualisasi eliminasi bilateral matriks dan laporan segmen. |

---

## 3. Hasil Test Awal (Baseline Test Suite Eksisting)
```
$ vendor/bin/pest modules/Intercompany/tests/Feature/
PASS Modules\Intercompany\tests\Feature\IntercompanyOperationsSliceTest (6 tests)
PASS Modules\Intercompany\tests\Feature\IntercompanyTest (7 tests)
Tests: 13 passed (47 assertions)
Duration: 7.48s
```
Semua 13 feature test lama Intercompany hijau. Kompatibilitas signature publik lama dipertahankan dengan membungkus atau memperluas kelas secara backwards-compatible.
