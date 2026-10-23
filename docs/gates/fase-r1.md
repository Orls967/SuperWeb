# Quality Gate Report: Fase R1

- **Commit:** `81bc527096bd70f741cc9ae4e1e615c3d7fce2d0` (81bc527)
- **Tanggal:** 2026-10-16T12:42:09+00:00
- **PHP:** 8.4.26
- **Database:** sqlite (v3.53.4)
- **Status Keseluruhan:** PASS 🟢

## 1. Ringkasan Test Suite (Pest / JUnit)
- **Total Test:** 2802
- **Total Assertion:** 14458
- **Durasi:** 608.08s
- **Gagal / Error:** 0 failures, 0 errors

## 2. Hasil Perintah Protokol P4
| Perintah | Deskripsi | Exit Code | Status |
|---|---|---|---|
| `composer validate --strict` | Langkah gate: composer validate --strict (0.22s) | 0 | PASS 🟢 |
| `vendor/bin/pest --parallel --log-junit=storage/logs/pest-junit.xml` | Langkah gate: pest (71.46s) | 0 | PASS 🟢 |
| `vendor/bin/pint --test` | Langkah gate: pint --test (0.40s) | 0 | PASS 🟢 |
| `php artisan arch:scan --json` | Langkah gate: arch:scan --json (6.25s) | 0 | PASS 🟢 |
| `php artisan migrate:fresh --seed --force` | Langkah gate: migrate:fresh --seed (26.53s) | 0 | PASS 🟢 |
| `php artisan bank:reconcile` | Langkah gate: bank:reconcile (0.32s) | 0 | PASS 🟢 |
| `php artisan chain:audit-all` | Langkah gate: chain:audit-all (0.23s) | 0 | PASS 🟢 |
| `php artisan super:health-check` | Langkah gate: super:health-check (0.22s) | 0 | PASS 🟢 |
| `npm run build` | Langkah gate: npm run build (1.01s) | 0 | PASS 🟢 |

## 3. Status Test Blok Bukti Fase R1
| Item | Nama Test | File | Status (JUnit) | Durasi |
|---|---|---|---|---|
| R1.1 | `it maps every AccountKind to its correct normal side` | `tests/Unit/Quality/Ledger/PostingHelperTest.php` | PASS 🟢 | 0.000s |
| R1.1 | `it enforces single sign convention with debit as negative and credit as positive` | `tests/Unit/Quality/Ledger/PostingHelperTest.php` | PASS 🟢 | 0.000s |
| R1.2 | `it keeps accounts with inverted normal balances within the ratchet baseline` | `tests/Architecture/LedgerNormalBalanceTest.php` | PASS 🟢 | 2.544s |
| R1.3 | `it keeps unseeded ledger accounts within the ratchet baseline` | `tests/Architecture/LedgerAccountRegistryTest.php` | PASS 🟢 | 2.546s |
| R1.3 | `it keeps tests within the hygiene ratchet baseline` | `tests/Architecture/TestHygieneTest.php` | PASS 🟢 | 1.435s |
| R1.4 | `posting dengan asset_code entri tidak cocok dengan aset akun melempar AssetMismatchException` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.048s |
| R1.4 | `posting ulang dengan key sama dan payload beda melempar IdempotencyConflictException` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.051s |
| R1.4 | `unique violation saat race condition ditangkap dan mengembalikan transaksi pemenang` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.049s |
| R1.4 | `model LedgerTransaction melempar ImmutableRecordException pada updating dan deleting` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.054s |
| R1.4 | `model LedgerEntry melempar ImmutableRecordException pada updating dan deleting` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.044s |
| R1.4 | `posting dengan tipe transaksi lebih dari 32 karakter melempar InvalidArgumentException` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.046s |
| R1.4 | `posting dengan tipe transaksi tidak terdaftar melempar InvalidArgumentException` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.061s |
| R1.4 | `it ensures all registered transaction type enums implement TransactionTypeRegistry` | `tests/Architecture/TransactionTypeRegistryUniqueTest.php` | PASS 🟢 | 0.002s |
| R1.4 | `it ensures all transaction type values are at most 32 characters` | `tests/Architecture/TransactionTypeRegistryUniqueTest.php` | PASS 🟢 | 0.001s |
| R1.4 | `it ensures all transaction type values are unique across all module registries` | `tests/Architecture/TransactionTypeRegistryUniqueTest.php` | PASS 🟢 | 0.001s |
| R1.4 | `two database connections racing same idempotency key return winner without double posting` | `tests/Feature/Banking/LedgerConcurrencyTest.php` | PASS 🟢 | 3.362s |
| R1.4 | `ledger service locks accounts in deterministic ascending id order to prevent deadlock` | `tests/Feature/Banking/LedgerConcurrencyTest.php` | PASS 🟢 | 0.127s |
| R1.5 | `fleet lease amortization idempotency same month one posting next month two` | `modules/Fleet/tests/Feature/FleetTest.php` | PASS 🟢 | 0.094s |
| R1.5 | `it keeps module code within the arch:scan ratchet baseline` | `tests/Architecture/ArchScanBaselineTest.php` | PASS 🟢 | 8.384s |
| R1.5 | `top up action without explicit idempotency key is idempotent and produces single transaction` | `tests/Feature/Banking/IdempotentPostingTest.php` | PASS 🟢 | 0.122s |
| R1.5 | `transfer action without explicit key is idempotent and produces single transaction` | `tests/Feature/Banking/IdempotentPostingTest.php` | PASS 🟢 | 0.090s |
| R1.5 | `manual adjustment action is idempotent producing single ledger transaction` | `tests/Feature/Banking/IdempotentPostingTest.php` | PASS 🟢 | 0.109s |
| R1.5 | `fleet leasing monthly amortization is idempotent and avoids double accumulation` | `tests/Feature/Banking/IdempotentPostingTest.php` | PASS 🟢 | 0.180s |
| R1.5 | `venue creator stream accrual is idempotent and does not double count held royalties` | `tests/Feature/Banking/IdempotentPostingTest.php` | PASS 🟢 | 0.182s |
| R1.5 | `hotel destination package settlement and franchise royalty are idempotent` | `tests/Feature/Banking/IdempotentPostingTest.php` | PASS 🟢 | 0.167s |
| R1.6 | `refund berulang dengan id sama menghasilkan tepat satu posting dan entitas pay_refunds` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.064s |
| R1.6 | `refund melebihi sisa tangkapanan ditolak` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.079s |
| R1.6 | `two partial refunds with same nominal amount via http post bookings refund create two pay refunds rows and two ledger transactions` | `tests/Feature/Payment/RefundEntityTest.php` | PASS 🟢 | 0.155s |
| R1.6 | `retrying the exact same refund request via http post bookings refund is idempotent and produces single transaction` | `tests/Feature/Payment/RefundEntityTest.php` | PASS 🟢 | 0.158s |
| R1.6 | `unauthorized roles receive 403 forbidden on http post bookings refund` | `tests/Feature/Payment/RefundEntityTest.php` | PASS 🟢 | 0.129s |
| R1.7 | `charge dan capture menolak split revenue yang kurang dari nominal` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.056s |
| R1.7 | `charge dan capture menolak split revenue yang lebih dari nominal` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.064s |
| R1.7 | `charge dan capture menolak split revenue bernilai nol` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.060s |
| R1.7 | `charge dan capture menolak split revenue bernilai negatif` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.048s |
| R1.7 | `charge dan capture berhasil bila total split pas sama dengan nominal` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.065s |
| R1.8 | `Money rejects float values` | `tests/Unit/Quality/Ledger/MoneyIntegerOnlyTest.php` | PASS 🟢 | 0.001s |
| R1.8 | `calculates Resto PB1 with exact integer half-up arithmetic` | `tests/Unit/Quality/Ledger/IntegerArithmeticTest.php` | PASS 🟢 | 0.000s |
| R1.8 | `calculates HCM payroll deductions and labor allocation using integer arithmetic` | `tests/Unit/Quality/Ledger/IntegerArithmeticTest.php` | PASS 🟢 | 0.000s |
| R1.8 | `it keeps module code within the arch:scan ratchet baseline` | `tests/Architecture/ArchScanBaselineTest.php` | PASS 🟢 | 8.384s |
| R1.8 | `it does not report decimal idr or double usd columns when remediated by newer alter migration in same module` | `tests/Unit/Quality/ArchScan/Rules/FloatMoneyRuleTest.php` | PASS 🟢 | 0.001s |
| R1.9 | `reconcile handles 10 pow 15 minor units without precision loss` | `tests/Feature/Banking/ReconcilePortableAndCorruptionTest.php` | PASS 🟢 | 0.130s |
| R1.9 | `reconcile detects corrupted entry and reports account name` | `tests/Feature/Banking/ReconcilePortableAndCorruptionTest.php` | PASS 🟢 | 0.093s |
| R1.9 | `incremental reconciliation saves and resumes from checkpoints` | `tests/Feature/Banking/ReconcilePortableAndCorruptionTest.php` | PASS 🟢 | 0.117s |
| R1.9 | `eighteen decimal places survive database round trip via ledger service` | `tests/Feature/Banking/DecimalRoundTripTest.php` | PASS 🟢 | 0.130s |
| R1.9 | `reconcile detects one rupiah discrepancy on idr ledger` | `tests/Feature/Banking/ReconcileDetectsOneUnitTest.php` | PASS 🟢 | 0.136s |
| R1.9 | `reconcile detects one atto unit 1e minus 18 discrepancy on crypto ledger` | `tests/Feature/Banking/ReconcileDetectsOneUnitTest.php` | PASS 🟢 | 0.117s |
| R1.9 | `incremental reconcile detects stale or corrupted checkpoint` | `tests/Feature/Banking/ReconcileDetectsOneUnitTest.php` | PASS 🟢 | 0.126s |

## 4. Ringkasan Arsitektur (`arch:scan`)
- **Status Baseline:** Terpasang (6899 pelanggaran terbaseline).

| Aturan | Deskripsi Singkat | Jumlah Pelanggaran |
|---|---|---|
| `A1` | Import/penggunaan Domain modul lain | 246 |
| `A2` | Tabel berprefiks modul lain / tidak terdaftar | 5254 |
| `A3` | Uang bertipe float / kolom IDR decimal | 319 |
| `A4` | Idempotency key dari nilai acak/waktu | 0 |
| `A5` | Tipe posting ledger > 32 char / di luar registry | 0 |
| `A6` | Parameter bool kontrol di Application/ | 132 |
| `A7` | Hash tanpa kunci untuk data sensitif | 0 |
| `A8` | Carbon/Date::setTestNow di produksi | 3 |
| `A9` | Kernel bergantung pada modul bisnis | 34 |
| `A10` | Model dengan $guarded = [] | 162 |
| `A11` | File PHP tanpa declare(strict_types=1) | 146 |
| `A12` | Kolom *_id di migrasi tanpa FK | 588 |
| `A13` | back()->errors() (HTTP 500 di jalur galat) | 15 |

## 5. Verifikasi
*(Bagian ini wajib diisi oleh verifikator independen sebelum mengubah status fase ke ✅ sesuai P7).*

- **Tanggal:** 2026-10-16
- **Verifikator:** Principal Auditor & Verifikator Independen (Putaran 2 — Pasca-Remediasi M-R1-1 s/d M-R1-8)
- **Commit yang diverifikasi:** 4ff89eb
- **Keputusan:** ✅ TERVERIFIKASI (LULUS C1–C14 & V1–V12)

| # | Hasil | Bukti (command / commit / output) | Catatan |
|---|---|---|---|
| C1 | LULUS 🟢 | `vendor/bin/pest tests/Architecture/ProgressIntegrityTest.php` exit 0 (2 passed, 2 assertions). Ratchet teks docs/PROGRESS.md dan integritas blok Bukti terjaga penuh. | Blok Bukti R1.1–R1.10 valid, tidak ada item tercentang tanpa bukti atau alasan pendek. |
| C2 | LULUS 🟢 | `composer gate` lokal & `php artisan gate:report --fase=R1` exit 0 (2802 tests, 14458 assertions, 0 errors, 0 failures, durasi 608.08s). | Seluruh 9 langkah quality gate P4 berstatus PASS 🟢. |
| C3 | LULUS 🟢 | Item R1.1 s/d R1.10 tercentang [x] dengan kode produksi riil, migration, dan test suite lengkap di blok Bukti. | Tidak ada placeholder atau bypass anti-pola. |
| C4 | LULUS 🟢 | `vendor/bin/pest tests/Architecture/RouteAuthorizationMatrixTest.php` exit 0 (7 passed, 537 assertions). Rute refunds `POST /bookings/{booking}/refund` diproteksi role granular (finance, management, owner). Unauthorized roles (customer, mekanik) terbukti 403 Forbidden di `RefundEntityTest`. | Matriks otorisasi rute terlindungi ketat. |
| C5 | LULUS 🟢 | `vendor/bin/pest tests/Architecture/LedgerNormalBalanceTest.php tests/Architecture/LedgerAccountRegistryTest.php tests/Architecture/TestHygieneTest.php` exit 0. Baseline `ledger-normal-balance.json` = 0 akun, `ledger-accounts.json` = 0, T2 hygiene = 0 lokasi `LedgerAccount::create`. | Seluruh akun dan arah saldo double-entry terkunci di kode produksi (M-R0-5, M-R0-6, M-R0-8 tuntas). |
| C6 | LULUS 🟢 | `php artisan bank:reconcile` exit 0 (Semua akun seimbang dan total global per aset = 0). Uji sabotase empiris $10^{-18}$ pada akun BTC membuktikan deteksi kegagalan rekonsiliasi seketika (exit 1) dan kembali normal saat di-restore. `ReconcileDetectsOneUnitTest` (3 tests) & `ReconcilePortableAndCorruptionTest` (3 tests) PASS 🟢. | Kontrak deteksi korupsi ledger terbukti sampai presisi terkecil $10^{-18}$. |
| C7 | LULUS 🟢 | `vendor/bin/pest tests/Architecture/MutationFenceContractTest.php` exit 0 (4 passed, 8 assertions). Pagar mutasi CI workflow dan ambang minimal ≥ 60% terlindungi. | Kontrak uji mutasi Pest aktif. |
| C8 | LULUS 🟢 | `tests/Architecture/baselines/arch-scan.json` A3 = 319 (100% berada di `modules/Integration`, 0 di modul non-Integration). `IntegrationFreezeTest` PASS 🟢. `FloatMoneyRuleTest` PASS 🟢 (12 passed). Tidak ada kenaikan baseline. | Ratchet arsitektur terjaga, freeze modul Integration dipatuhi. |
| C9 | LULUS 🟢 | `git diff` membuktikan deskripsi teks item R1.1 s/d R1.10 tidak diubah/dilemahkan (terkunci `progress-item-texts.json`). | Integritas kontrak teks DoR terjaga (P10 / X18). |
| C10 | LULUS 🟢 | `vendor/bin/pest tests/Architecture/MysqlSchemaCompatibilityTest.php` exit 0 (12 passed, 13 assertions). Seluruh migrasi moneter dan skema integer portabel ke MySQL 8.4. | Portabilitas skema database terverifikasi penuh. |
| C11 | LULUS 🟢 | Seluruh minus P0/P1 Fase R1 tuntas dan dicoret: `~~M-R1-2~~` (A3 non-integration), `~~M-R1-3~~` (LedgerConcurrencyTest), `~~M-R1-4~~` (IdempotentPostingTest & TopUpAction), `~~M-R1-5~~` (RefundEntityTest), `~~M-R1-6~~` (Presisi 18 desimal & sabotase empiris), `~~M-R1-7~~` (Bukti R1.2/R1.3). Minus P2 `M-R1-1` dialihkan sah ke R4.4. Tidak ada minus P0/P1 terbuka. | Kriteria penutupan fase P9 dan P7 terpenuhi tanpa minus kritis terbuka. |
| C12 | LULUS 🟢 | Dokumentasi keputusan formal D1–D9 di `docs/DECISIONS.md` valid (`r1-d1-non-idr-minor-units`, `r1-d4-test-tanda-salah`, `r1-d9-a3-integration-freeze`). Blocker B-05 ditutup. | Audit trail keputusan arsitektur dan finansial lengkap. |
| C13 | LULUS 🟢 | Riwayat commit atomik, granular per deliverable, pesan commit konvensional, seluruh hash terlacak di blok Bukti. | Riwayat git log rapi dan terstruktur. |
| C14 | LULUS 🟢 | Tidak ada tanda bahaya P12: tidak ada `assertTrue(true)` palsu, tidak ada `allow_negative = true` liar, tidak ada pembulatan `round()` pada float, tidak ada floating-point drift. | Red flags P12 bersih 100%. |

**Minus yang ditambahkan verifikator:** Tidak ada pada Putaran 2 (M-R1-2 s/d M-R1-8 tuntas dan dicoret; M-R1-1 P2 dialihkan ke R4.4).
**Alasan 🔁 (bila ada):** - (Putaran 2 LULUS penuh pasca-remediasi).

