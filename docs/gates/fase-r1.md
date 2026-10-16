# Quality Gate Report: Fase R1

- **Commit:** `6469823243ad5bf9c4cfdc831ec56a9b9dd9b89e` (6469823)
- **Tanggal:** 2026-10-16T09:02:33+00:00
- **PHP:** 8.4.26
- **Database:** sqlite (v3.53.4)
- **Status Keseluruhan:** PASS 🟢

## 1. Ringkasan Test Suite (Pest / JUnit)
- **Total Test:** 2778
- **Total Assertion:** 14341
- **Durasi:** 806.88s
- **Gagal / Error:** 0 failures, 0 errors

## 2. Hasil Perintah Protokol P4
| Perintah | Deskripsi | Exit Code | Status |
|---|---|---|---|
| `composer validate --strict` | Langkah gate: composer validate --strict (0.23s) | 0 | PASS 🟢 |
| `vendor/bin/pest --parallel --log-junit=storage/logs/pest-junit.xml` | Langkah gate: pest (92.91s) | 0 | PASS 🟢 |
| `vendor/bin/pint --test` | Langkah gate: pint --test (0.71s) | 0 | PASS 🟢 |
| `php artisan arch:scan --json` | Langkah gate: arch:scan --json (2.68s) | 0 | PASS 🟢 |
| `php artisan migrate:fresh --seed --force` | Langkah gate: migrate:fresh --seed (27.01s) | 0 | PASS 🟢 |
| `php artisan bank:reconcile` | Langkah gate: bank:reconcile (0.34s) | 0 | PASS 🟢 |
| `php artisan chain:audit-all` | Langkah gate: chain:audit-all (0.25s) | 0 | PASS 🟢 |
| `php artisan super:health-check` | Langkah gate: super:health-check (0.26s) | 0 | PASS 🟢 |
| `npm run build` | Langkah gate: npm run build (1.25s) | 0 | PASS 🟢 |

## 3. Status Test Blok Bukti Fase R1
| Item | Nama Test | File | Status (JUnit) | Durasi |
|---|---|---|---|---|
| R1.1 | `it maps every AccountKind to its correct normal side` | `tests/Unit/Quality/Ledger/PostingHelperTest.php` | PASS 🟢 | 0.001s |
| R1.1 | `it enforces single sign convention with debit as negative and credit as positive` | `tests/Unit/Quality/Ledger/PostingHelperTest.php` | PASS 🟢 | 0.000s |
| R1.2 | `it keeps accounts with inverted normal balances within the ratchet baseline` | `tests/Architecture/LedgerNormalBalanceTest.php` | PASS 🟢 | 3.407s |
| R1.3 | `it keeps unseeded ledger accounts within the ratchet baseline` | `tests/Architecture/LedgerAccountRegistryTest.php` | PASS 🟢 | 3.575s |
| R1.3 | `it keeps tests within the hygiene ratchet baseline` | `tests/Architecture/TestHygieneTest.php` | PASS 🟢 | 1.304s |
| R1.4 | `posting dengan asset_code entri tidak cocok dengan aset akun melempar AssetMismatchException` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.043s |
| R1.4 | `posting ulang dengan key sama dan payload beda melempar IdempotencyConflictException` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.063s |
| R1.4 | `unique violation saat race condition ditangkap dan mengembalikan transaksi pemenang` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.052s |
| R1.4 | `model LedgerTransaction melempar ImmutableRecordException pada updating dan deleting` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.046s |
| R1.4 | `model LedgerEntry melempar ImmutableRecordException pada updating dan deleting` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.054s |
| R1.4 | `posting dengan tipe transaksi lebih dari 32 karakter melempar InvalidArgumentException` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.046s |
| R1.4 | `posting dengan tipe transaksi tidak terdaftar melempar InvalidArgumentException` | `modules/Banking/tests/Feature/LedgerPostingTest.php` | PASS 🟢 | 0.044s |
| R1.4 | `it ensures all registered transaction type enums implement TransactionTypeRegistry` | `tests/Architecture/TransactionTypeRegistryUniqueTest.php` | PASS 🟢 | 0.001s |
| R1.4 | `it ensures all transaction type values are at most 32 characters` | `tests/Architecture/TransactionTypeRegistryUniqueTest.php` | PASS 🟢 | 0.003s |
| R1.4 | `it ensures all transaction type values are unique across all module registries` | `tests/Architecture/TransactionTypeRegistryUniqueTest.php` | PASS 🟢 | 0.001s |
| R1.5 | `fleet lease amortization idempotency same month one posting next month two` | `modules/Fleet/tests/Feature/FleetTest.php` | PASS 🟢 | 0.129s |
| R1.5 | `it keeps module code within the arch:scan ratchet baseline` | `tests/Architecture/ArchScanBaselineTest.php` | PASS 🟢 | 5.205s |
| R1.6 | `refund berulang dengan id sama menghasilkan tepat satu posting dan entitas pay_refunds` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.064s |
| R1.6 | `refund melebihi sisa tangkapanan ditolak` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.092s |
| R1.7 | `charge dan capture menolak split revenue yang kurang dari nominal` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.071s |
| R1.7 | `charge dan capture menolak split revenue yang lebih dari nominal` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.068s |
| R1.7 | `charge dan capture menolak split revenue bernilai nol` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.087s |
| R1.7 | `charge dan capture menolak split revenue bernilai negatif` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.062s |
| R1.7 | `charge dan capture berhasil bila total split pas sama dengan nominal` | `modules/Payment/tests/Feature/PaymentGatewayTest.php` | PASS 🟢 | 0.055s |

## 4. Ringkasan Arsitektur (`arch:scan`)
- **Status Baseline:** Terpasang (6932 pelanggaran terbaseline).

| Aturan | Deskripsi Singkat | Jumlah Pelanggaran |
|---|---|---|
| `A1` | Import/penggunaan Domain modul lain | 246 |
| `A2` | Tabel berprefiks modul lain / tidak terdaftar | 5254 |
| `A3` | Uang bertipe float / kolom IDR decimal | 352 |
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

