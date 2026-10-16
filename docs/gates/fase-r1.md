# Quality Gate Report: Fase R1

- **Commit:** `f17b6f157f667f50b24f3f48d6eb0311d2234b94` (f17b6f1)
- **Tanggal:** 2026-10-16T10:07:42+00:00
- **PHP:** 8.4.26
- **Database:** sqlite (v3.53.4)
- **Status Keseluruhan:** PASS 🟢

## 1. Ringkasan Test Suite (Pest / JUnit)
- **Total Test:** 2787
- **Total Assertion:** 14382
- **Durasi:** 853.93s
- **Gagal / Error:** 0 failures, 0 errors

## 2. Hasil Perintah Protokol P4
| Perintah | Deskripsi | Exit Code | Status |
|---|---|---|---|
| `composer validate --strict` | Langkah gate: composer validate --strict (0.22s) | 0 | PASS 🟢 |
| `vendor/bin/pest --parallel --log-junit=storage/logs/pest-junit.xml` | Langkah gate: pest (96.75s) | 0 | PASS 🟢 |
| `vendor/bin/pint --test` | Langkah gate: pint --test (0.38s) | 0 | PASS 🟢 |
| `php artisan arch:scan --json` | Langkah gate: arch:scan --json (2.77s) | 0 | PASS 🟢 |
| `php artisan migrate:fresh --seed --force` | Langkah gate: migrate:fresh --seed (27.22s) | 0 | PASS 🟢 |
| `php artisan bank:reconcile` | Langkah gate: bank:reconcile (0.31s) | 0 | PASS 🟢 |
| `php artisan chain:audit-all` | Langkah gate: chain:audit-all (0.87s) | 0 | PASS 🟢 |
| `php artisan super:health-check` | Langkah gate: super:health-check (0.36s) | 0 | PASS 🟢 |
| `npm run build` | Langkah gate: npm run build (1.13s) | 0 | PASS 🟢 |

## 3. Status Test Blok Bukti Fase R1
| Item | Nama Test | File | Status (JUnit) | Durasi |
|---|---|---|---|---|
| R1.1 | `it maps every AccountKind to its correct normal side` | `tests/Unit/Quality/Ledger/PostingHelperTest.php` | PASS 🟢 | 0.001s |
| R1.1 | `it enforces single sign convention with debit as negative and credit as positive` | `tests/Unit/Quality/Ledger/PostingHelperTest.php` | PASS 🟢 | 0.001s |
| R1.2 | `it keeps accounts with inverted normal balances within the ratchet baseline` | `tests/Architecture/LedgerNormalBalanceTest.php` | PASS 🟢 | 4.801s |
| R1.3 | `it keeps unseeded ledger accounts within the ratchet baseline` | `tests/Architecture/LedgerAccountRegistryTest.php` | PASS 🟢 | 5.314s |
| R1.3 | `it keeps tests within the hygiene ratchet baseline` | `tests/Architecture/TestHygieneTest.php` | PASS 🟢 | 1.890s |
| R1.4 | `posting dengan asset_code entri tidak cocok dengan aset akun melempar AssetMismatchException` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.039s |
| R1.4 | `posting ulang dengan key sama dan payload beda melempar IdempotencyConflictException` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.033s |
| R1.4 | `unique violation saat race condition ditangkap dan mengembalikan transaksi pemenang` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.058s |
| R1.4 | `model LedgerTransaction melempar ImmutableRecordException pada updating dan deleting` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.056s |
| R1.4 | `model LedgerEntry melempar ImmutableRecordException pada updating dan deleting` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.058s |
| R1.4 | `posting dengan tipe transaksi lebih dari 32 karakter melempar InvalidArgumentException` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.054s |
| R1.4 | `posting dengan tipe transaksi tidak terdaftar melempar InvalidArgumentException` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.043s |
| R1.4 | `it ensures all registered transaction type enums implement TransactionTypeRegistry` | `tests/Architecture/TransactionTypeRegistryUniqueTest.php` | PASS 🟢 | 0.002s |
| R1.4 | `it ensures all transaction type values are at most 32 characters` | `tests/Architecture/TransactionTypeRegistryUniqueTest.php` | PASS 🟢 | 0.002s |
| R1.4 | `it ensures all transaction type values are unique across all module registries` | `tests/Architecture/TransactionTypeRegistryUniqueTest.php` | PASS 🟢 | 0.001s |
| R1.5 | `fleet lease amortization idempotency same month one posting next month two` | `modules/Fleet/tests/Feature/FleetTest.php` | PASS 🟢 | 0.126s |
| R1.5 | `it keeps module code within the arch:scan ratchet baseline` | `tests/Architecture/ArchScanBaselineTest.php` | PASS 🟢 | 6.784s |
| R1.6 | `refund berulang dengan id sama menghasilkan tepat satu posting dan entitas pay_refunds` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.081s |
| R1.6 | `refund melebihi sisa tangkapanan ditolak` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.067s |
| R1.7 | `charge dan capture menolak split revenue yang kurang dari nominal` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.076s |
| R1.7 | `charge dan capture menolak split revenue yang lebih dari nominal` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.088s |
| R1.7 | `charge dan capture menolak split revenue bernilai nol` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.066s |
| R1.7 | `charge dan capture menolak split revenue bernilai negatif` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.047s |
| R1.7 | `charge dan capture berhasil bila total split pas sama dengan nominal` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.076s |
| R1.8 | `Money rejects float values` | `tests/Unit/Quality/Ledger/MoneyIntegerOnlyTest.php` | PASS 🟢 | 0.001s |
| R1.8 | `calculates Resto PB1 with exact integer half-up arithmetic` | `tests/Unit/Quality/Ledger/IntegerArithmeticTest.php` | PASS 🟢 | 0.000s |
| R1.8 | `calculates HCM payroll deductions and labor allocation using integer arithmetic` | `tests/Unit/Quality/Ledger/IntegerArithmeticTest.php` | PASS 🟢 | 0.001s |
| R1.8 | `it keeps module code within the arch:scan ratchet baseline` | `tests/Architecture/ArchScanBaselineTest.php` | PASS 🟢 | 6.784s |
| R1.9 | `reconcile handles 10 pow 15 minor units without precision loss` | `tests/Feature/Banking/ReconcilePortableAndCorruptionTest.php` | PASS 🟢 | 0.125s |
| R1.9 | `reconcile detects corrupted entry and reports account name` | `tests/Feature/Banking/ReconcilePortableAndCorruptionTest.php` | PASS 🟢 | 0.126s |
| R1.9 | `incremental reconciliation saves and resumes from checkpoints` | `tests/Feature/Banking/ReconcilePortableAndCorruptionTest.php` | PASS 🟢 | 0.133s |

## 4. Ringkasan Arsitektur (`arch:scan`)
- **Status Baseline:** Terpasang (6915 pelanggaran terbaseline).

| Aturan | Deskripsi Singkat | Jumlah Pelanggaran |
|---|---|---|
| `A1` | Import/penggunaan Domain modul lain | 246 |
| `A2` | Tabel berprefiks modul lain / tidak terdaftar | 5254 |
| `A3` | Uang bertipe float / kolom IDR decimal | 335 |
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

- **Tanggal:** 
- **Verifikator:** 
- **Commit yang diverifikasi:** 
- **Keputusan:** BELUM DIVERIFIKASI

| # | Hasil | Bukti (command / commit / output) | Catatan |
|---|---|---|---|
| C1 | BELUM DIVERIFIKASI | | |
| C2 | BELUM DIVERIFIKASI | | |
| C3 | BELUM DIVERIFIKASI | | |
| C4 | BELUM DIVERIFIKASI | | |
| C5 | BELUM DIVERIFIKASI | | |
| C6 | BELUM DIVERIFIKASI | | |
| C7 | BELUM DIVERIFIKASI | | |
| C8 | BELUM DIVERIFIKASI | | |
| C9 | BELUM DIVERIFIKASI | | |
| C10 | BELUM DIVERIFIKASI | | |
| C11 | BELUM DIVERIFIKASI | | |
| C12 | BELUM DIVERIFIKASI | | |
| C13 | BELUM DIVERIFIKASI | | |
| C14 | BELUM DIVERIFIKASI | | |

**Minus yang ditambahkan verifikator:** 
**Alasan 🔁 (bila ada):**

