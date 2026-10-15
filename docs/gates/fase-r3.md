# Quality Gate Report: Fase R3

- **Commit:** `1d1b75fa8b8849b293125e68b556f3f03b5722f4` (1d1b75f)
- **Tanggal:** 2026-10-15T16:06:02+00:00
- **PHP:** 8.4.26
- **Database:** sqlite (v3.53.4)
- **Status Keseluruhan:** PASS 🟢

## 1. Ringkasan Test Suite (Pest / JUnit)
- **Total Test:** 2744
- **Total Assertion:** 13578
- **Durasi:** 612.53s
- **Gagal / Error:** 0 failures, 0 errors

## 2. Hasil Perintah Protokol P4
| Perintah | Deskripsi | Exit Code | Status |
|---|---|---|---|
| `composer validate --strict` | Langkah gate: composer validate --strict (0.19s) | 0 | PASS 🟢 |
| `vendor/bin/pest --parallel --log-junit=storage/logs/pest-junit.xml` | Langkah gate: pest (72.21s) | 0 | PASS 🟢 |
| `vendor/bin/pint --test` | Langkah gate: pint --test (0.87s) | 0 | PASS 🟢 |
| `php artisan arch:scan --json` | Langkah gate: arch:scan --json (2.69s) | 0 | PASS 🟢 |
| `php artisan migrate:fresh --seed --force` | Langkah gate: migrate:fresh --seed (27.01s) | 0 | PASS 🟢 |
| `php artisan bank:reconcile` | Langkah gate: bank:reconcile (0.30s) | 0 | PASS 🟢 |
| `php artisan chain:audit-all` | Langkah gate: chain:audit-all (0.22s) | 0 | PASS 🟢 |
| `php artisan super:health-check` | Langkah gate: super:health-check (0.22s) | 0 | PASS 🟢 |
| `npm run build` | Langkah gate: npm run build (1.00s) | 0 | PASS 🟢 |

## 3. Status Test Blok Bukti Fase R3
| Item | Nama Test | File | Status (JUnit) | Durasi |
|---|---|---|---|---|
| R3.1 | `it maps every registered route in route-roles.php` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 0.023s |
| R3.1 | `it confirms authorized roles are not forbidden (not 403) on role-protected routes` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 9.825s |
| R3.1 | `it enforces that unauthorized roles receive 403 on role-protected routes` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 1.181s |
| R3.1 | `all 37 write routes forbid auditor role` | `tests/Feature/Authorization/AuditorReadOnlyPolicyTest.php` | PASS 🟢 | 2.222s |
| R3.1 | `admin without query receives 200 with distributor list selector` | `tests/Feature/Authorization/DistributorPortalSelectorTest.php` | PASS 🟢 | 2.821s |
| R3.1 | `admin with distributor query receives 200 with data and audit log` | `tests/Feature/Authorization/DistributorPortalSelectorTest.php` | PASS 🟢 | 2.550s |
| R3.1 | `non admin distributor attempting to select distributor receives 403` | `tests/Feature/Authorization/DistributorPortalSelectorTest.php` | PASS 🟢 | 4.000s |
| R3.1 | `storage local upload without valid signature returns 403` | `tests/Feature/Authorization/SignedStorageUploadTest.php` | PASS 🟢 | 0.027s |
| R3.1 | `storage local upload with valid signature is not forbidden` | `tests/Feature/Authorization/SignedStorageUploadTest.php` | PASS 🟢 | 0.023s |
| R3.2 | `deterministic generation with fixed vector` | `tests/Unit/Security/BlindIndexTest.php` | PASS 🟢 | 0.172s |
| R3.2 | `employee nik is encrypted and searchable via blind index` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.020s |
| R3.2 | `mall tenant npwp is encrypted and searchable via blind index` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.017s |
| R3.2 | `party and legal entity pii encryption and blind index` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.028s |
| R3.2 | `plm lab notebook formula is encrypted` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.017s |
| R3.2 | `webhook secrets are encrypted in database` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.016s |
| R3.2 | `developer and partner api keys are one way hashed in database` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.016s |
| R3.2 | `admin can view all salaries and payrolls` | `tests/Feature/Authorization/SalaryAuthorizationPolicyTest.php` | PASS 🟢 | 0.097s |
| R3.2 | `hcm manager can view all salaries and payrolls` | `tests/Feature/Authorization/SalaryAuthorizationPolicyTest.php` | PASS 🟢 | 0.103s |
| R3.2 | `staff cannot view other employees salaries or payrolls` | `tests/Feature/Authorization/SalaryAuthorizationPolicyTest.php` | PASS 🟢 | 0.105s |
| R3.2 | `staff can view own salary and payroll slip` | `tests/Feature/Authorization/SalaryAuthorizationPolicyTest.php` | PASS 🟢 | 0.182s |
| R3.2 | `it enforces that every Eloquent model with sensitive columns declares encrypted or hashed cast` | `tests/Architecture/SensitiveDataCastingTest.php` | PASS 🟢 | 0.185s |
| R3.3 | `payout rejected when hold window has not passed` | `tests/Feature/Venue/CreatorRoyaltyPayoutGuardTest.php` | PASS 🟢 | 0.020s |
| R3.3 | `payout succeeds when hold window has passed` | `tests/Feature/Venue/CreatorRoyaltyPayoutGuardTest.php` | PASS 🟢 | 0.020s |
| R3.3 | `payout rejected when contract is disputed` | `tests/Feature/Venue/CreatorRoyaltyPayoutGuardTest.php` | PASS 🟢 | 0.020s |
| R3.3 | `payout returns zero when no held royalties` | `tests/Feature/Venue/CreatorRoyaltyPayoutGuardTest.php` | PASS 🟢 | 0.020s |
| R3.4 | `non admin cannot access integration dashboard` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.020s |
| R3.4 | `admin can access integration dashboard` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.020s |
| R3.4 | `unauthenticated user redirected from integration dashboard` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.020s |
| R3.4 | `api client rate limiting enforced by api key` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.020s |
| R3.4 | `api client rate limiting enforced by client id header` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.020s |
| R3.4 | `api client rate limiter resets after clearing` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.020s |

## 4. Ringkasan Arsitektur (`arch:scan`)
- **Status Baseline:** Terpasang (7259 pelanggaran terbaseline).

| Aturan | Deskripsi Singkat | Jumlah Pelanggaran |
|---|---|---|
| `A1` | Import/penggunaan Domain modul lain | 428 |
| `A2` | Tabel berprefiks modul lain / tidak terdaftar | 5254 |
| `A3` | Uang bertipe float / kolom IDR decimal | 352 |
| `A4` | Idempotency key dari nilai acak/waktu | 44 |
| `A5` | Tipe posting ledger > 32 char / di luar registry | 101 |
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

