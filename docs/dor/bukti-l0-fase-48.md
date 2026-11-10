# Bukti Kondisi Awal Fase 48 (L.0)

**Fase**: Fase 48 — Multi-Currency & Treasury  
**Waktu Pengambilan**: 2026-11-11T03:07:00+08:00  
**Base Commit**: `5863b28a86f2c82c70da229113184bcca423d32b` (Merge PR #108 ke master)  
**Branch**: `feature/w8-p5-treasury-grup`  
**LP_ID**: `sw-w8-p5`

---

## 1. Verifikasi Alat dan Fondasi di Master

### A. Perintah Artisan Kualitas, Dokumentasi, UI Lint & Spine
```
$ php artisan list | grep -E "quality:|docs:|ui:lint|spine"
  core:consume-spine              Worker to consume events from the event spine by consumer group with persistent offset, persistent inbox idempotency and DLQ backoff
  docs:check                      Memeriksa kesegaran dokumen desain, broken links, dan integritas diagram
  docs:drift                      Mendeteksi pergeseran (drift) antara spesifikasi dokumen dengan implementasi skema database
  docs:export                     Mengekspor seluruh bundle dokumentasi arsitektur dan skema
  docs:generate                   Menghasilkan artefak diagram SVG secara deterministik dari blok Mermaid murni PHP (IZIN-2)
  docs:inventory                  Generate bagian faktual docs/CODEBASE.md (modul, prefiks, migrasi, rute, command & jadwal, akun ledger) (K-03, PROGRESS R5.1)
  quality:coverage-gap            Mengevaluasi selisih gap cakupan test suite terhadap target
  quality:phase-metrics           Mengukur metrik fase B1-B3, cyclomatic density, serta mendeteksi manipulasi kode M-2 s/d M-5
  quality:pipeline-status         Menampilkan status integrasi pipeline CI/CD dan trunk git
 spine
  spine:archive                   Mengarsipkan rentang event spine historis ke berkas NDJSON cold storage
  spine:audit                     Perform dual-source audit across outbox, event spine log, consumer inbox and deliveries
  spine:consistency-check         Menjalankan audit konsistensi aliran event spine vs inbox lintas modul
  spine:dlq-replay                Replay dead-lettered messages in batches with operational audit reason
  spine:gap-scan                  Scan outbox stream for aggregate sequence gaps and record them idempotently (every 15 mins)
  spine:lag-report                Report current consumer lag metrics and overall nearest-rank p95 lag
  spine:parity-verify             Verify runtime parity and class existence of all legacy event listeners adapted to event spine
  spine:partition-status          Menampilkan metrik distribusi beban dan kesehatan partisi virtual event spine
  spine:replay                    Replay event spine stream deterministically without side effects and verify projection hash
  spine:scale-recommend           Menganalisis lag antrean dan merekomendasikan penskalaan worker per consumer group
  spine:schema-evolve             Menyimulasikan dampak perubahan skema terhadap data historis dan konsumen terdaftar
  spine:seed-t1                   Seed T1 scale dataset for Event Spine (20,000 outbox messages, 7 SM statuses, DLQ, poison messages)
  spine:snapshot                  Create a deterministic projection snapshot for a consumer group and verify canonical hash
  ui:lint                         Linter aksesibilitas, tokenisasi warna, responsivitas, dan kepatuhan Design System untuk template Blade
```

### B. Pemeriksaan `docs/gates/ambang.json` dan `interface TaskInboxProvider`
```
$ ls docs/gates/ambang.json 2>&1
ls: docs/gates/ambang.json: No such file or directory

$ grep -rn "interface TaskInboxProvider" modules app 2>&1
(exit code 1 - tidak ditemukan)
```
- Kontrak `TaskInboxProvider` tidak ada; implementasi nyata yang ada di master adalah `Modules\Core\Application\Actions\TaskInbox\TaskInboxService::createTask(array $data)` dengan model `CoreTask`.
- `docs/gates/ambang.json` tidak ada; ambang M-2..M-6 diukur sesuai prompt via skrip /tmp/gerbang.sh dan `quality:phase-metrics` sebagai pelengkap.

### C. Fondasi Spine W8-P3
- `Modules\Core\Contracts\EventSpineInterface` (ada)
- `Modules\Core\Contracts\OutboxBusInterface` (ada)
- `Modules\Core\Application\Services\EventSchemaRegistry` (ada)
- `Modules\Core\Domain\Events\EventCatalog` (ada, entri aditif diperbolehkan)

---

## 2. Tabel Evaluasi Kondisi Lama Modul Treasury (Ada / Benar / Salah / Tidak Ada)

| Komponen | Status | Penjelasan & Temuan Faktual |
|---|---|---|
| **Tabel Skema DB** | Ada / Salah | 9 tabel ada (`trs_currencies`, `trs_exchange_rates`, `trs_revaluations`, `trs_bank_accounts`, `trs_bank_statements`, `trs_cash_forecasts`, `trs_forward_contracts`, `trs_credit_facilities`, `trs_cash_pools`). **Salah**: `trs_credit_facilities` menggunakan tipe `decimal` untuk interest rate dan max DER; belum ada tabel subledger lot valas `trs_fx_lots`; belum ada tabel riwayat transisi state machine forward contract; `trs_bank_statements` belum memuat tahap rekonsiliasi dan reason unmatch. |
| **Model Eloquent** | Ada / Salah | Model ada (`Currency`, `ExchangeRate`, `Revaluation`, `BankAccount`, `BankStatement`, `CashForecast`, `ForwardContract`, `CreditFacility`, `CashPool`). **Salah**: `CreditFacility` menggunakan cast `float`; model belum append-only untuk transaksi fakta. |
| **TreasuryService** | Ada / Salah | 447 baris. **Salah**: `convertAmount` memakai pembagian integer sederhana tanpa rational object terpisah; `performRevaluation` tidak membuat jurnal posting ledger; `autoReconcileStatements` langsung menandai matched tanpa algoritma rekonsiliasi tiga tahap; `drawFacility` menerima parameter `float $currentDer` (melanggar A3); `sweepCashPool` hanya mengubah saldo tabel lokal tanpa posting ke ledger dan tanpa Intercompany Loan; `auditTreasury` hanya agregat count DB lokal tanpa rekonsiliasi dual-source ke ledger. |
| **UniversalGroupCashPoolingService** | Ada / Salah | 96 baris. **Salah**: Memakai `date('Y-m-d')` hardcoded alih-alih `ClockInterface`. |
| **Actions Eksisting** | Ada / Salah | `RunMultiCurrencyFxRevaluationAction`, `SettleForwardFxHedgeContractAction`, `ExecuteNotionalCashPoolSweepAction`. **Salah**: Tidak terhubung dengan state machine lengkap dan tidak memposting dua kaki fungsional/valas seimbang. |
| **Akun Ledger (`TreasuryAccounts`)** | Ada / Salah | 5 akun terdefinisi (`TRS_USD_SRC`, `TRS_USD_DEST`, `treasury:yield_pool:IDR`, `trs:fx_gain_loss:IDR`, `treasury:intercompany_sweep:default:IDR`). **Salah**: Belum ada akun standar revaluasi (`trs:fx_reval_asset_adj:IDR`, `trs:fx_unrealized_gain_loss:IDR`, `trs:fx_reval_liability_adj:IDR`), akun settlement (`trs:fx_settlement_clearing:IDR`, `trs:fx_realized_gain_loss:IDR`), akun derivatif MTM (`trs:derivative_asset:IDR`, `trs:derivative_liability:IDR`, `trs:derivative_gain_loss:IDR`, `trs:hedge_cancellation_cost:IDR`), akun rekonsiliasi (`trs:bank_reconciliation_diff:IDR`, `trs:bank_clearing:IDR`), akun fasilitas (`trs:facility_payable:IDR`, `trs:interest_expense:IDR`, `trs:interest_payable:IDR`), dan akun pooling (`trs:pool_sweep_clearing:{anak}:IDR`). |
| **Jenis Transaksi (`TreasuryTransactionTypes`)** | Ada / Salah | Hanya ada `TREASURY_FX_TRANSFER`. Perlu penambahan jenis transaksi resmi sesuai tabel posting D48-10. |
| **State Machine Lindung Nilai (HedgeContract)** | Tidak Ada | Belum ada state machine 8 status (`draft`, `approved`, `booked`, `active`, `matured`, `settled`, `rolled`, `cancelled`) dengan guard dan riwayat append-only. |
| **Algoritma Bersertifikat (D1, D2, D3)** | Tidak Ada | Belum ada: `FxRevaluationCalculator` (D1), `BankReconciliationMatcher` (D2), `SeasonalCashForecaster` (D3). |
| **Kontrak Antarmuka (Katalog Kontrak)** | Tidak Ada | Belum ada `ExchangeRateLookup` dan `FxMonetaryItemFeed` di modul Treasury. Belum ada `LoanExposureProvider` di modul Finance. |
| **Dual-Source Audit Command** | Ada / Salah | `treasury:audit` ada tetapi hanya memverifikasi hitungan internal tabel `trs_*`, belum membandingkan saldo buku dengan entri LedgerService dan subledger lot valas. |
| **UI & Dashboard** | Ada / Salah | Ada view Blade dasar (`cash_pooling`, `fx_hedging`, `fx_revaluation`, `index`, `treasury_dashboard`), tetapi belum mengadopsi 31 komponen `x-ds.*` penuh, belum memenuhi P-UI responsif 375px, dan belum ada ekspor CSV untuk seluruh data posisi kas. |
| **Service Referensi di Integration** | Ada | `TreasuryAlgorithmicMarketRiskService.php`, `TreasuryUnificationService.php`, `GlobalFinanceTreasuryRiskService.php`. Diberi status referensi dan tidak diubah langsung. |

---

## 3. Hasil Test Awal (Baseline Test Suite Eksisting)
```
$ vendor/bin/pest modules/Treasury/tests
PASS Modules\Treasury\tests\Feature\TreasuryOperationsSliceTest
PASS Modules\Treasury\tests\Feature\TreasuryTest
PASS Modules\Treasury\tests\Feature\UniversalGroupCashPoolingTest
Tests: 16 passed (58 assertions)
Duration: 15.45s
```
Semua 16 test lama Treasury hijau. Seluruh perluasan Fase 48 akan mempertahankan kompatibilitas signature publik lama dengan membungkusnya secara rapi.

