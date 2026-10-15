# Quality Gate Report: Fase R3

- **Commit:** `111d3ec323acc15565efd39f709465e3d3cc5a43` (111d3ec)
- **Tanggal:** 2026-10-15T16:40:48+00:00
- **PHP:** 8.4.26
- **Database:** sqlite (v3.53.4)
- **Status Keseluruhan:** PASS 🟢

## 1. Ringkasan Test Suite (Pest / JUnit)
- **Total Test:** 2744
- **Total Assertion:** 13576
- **Durasi:** 643.98s
- **Gagal / Error:** 0 failures, 0 errors

## 2. Hasil Perintah Protokol P4
| Perintah | Deskripsi | Exit Code | Status |
|---|---|---|---|
| `composer validate --strict` | Langkah gate: composer validate --strict (0.20s) | 0 | PASS 🟢 |
| `vendor/bin/pest --parallel --log-junit=storage/logs/pest-junit.xml` | Langkah gate: pest (75.32s) | 0 | PASS 🟢 |
| `vendor/bin/pint --test` | Langkah gate: pint --test (0.36s) | 0 | PASS 🟢 |
| `php artisan arch:scan --json` | Langkah gate: arch:scan --json (2.62s) | 0 | PASS 🟢 |
| `php artisan migrate:fresh --seed --force` | Langkah gate: migrate:fresh --seed (26.38s) | 0 | PASS 🟢 |
| `php artisan bank:reconcile` | Langkah gate: bank:reconcile (0.35s) | 0 | PASS 🟢 |
| `php artisan chain:audit-all` | Langkah gate: chain:audit-all (0.31s) | 0 | PASS 🟢 |
| `php artisan super:health-check` | Langkah gate: super:health-check (0.27s) | 0 | PASS 🟢 |
| `npm run build` | Langkah gate: npm run build (1.06s) | 0 | PASS 🟢 |

## 3. Status Test Blok Bukti Fase R3
| Item | Nama Test | File | Status (JUnit) | Durasi |
|---|---|---|---|---|
| R3.1 | `it maps every registered route in route-roles.php` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 0.034s |
| R3.1 | `it confirms authorized roles are not forbidden (not 403) on role-protected routes` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 9.650s |
| R3.1 | `it enforces that unauthorized roles receive 403 on role-protected routes` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 1.125s |
| R3.1 | `all 37 write routes forbid auditor role` | `tests/Feature/Authorization/AuditorReadOnlyPolicyTest.php` | PASS 🟢 | 2.104s |
| R3.1 | `admin without query receives 200 with distributor list selector` | `tests/Feature/Authorization/DistributorPortalSelectorTest.php` | PASS 🟢 | 2.609s |
| R3.1 | `admin with distributor query receives 200 with data and audit log` | `tests/Feature/Authorization/DistributorPortalSelectorTest.php` | PASS 🟢 | 2.947s |
| R3.1 | `non admin distributor attempting to select distributor receives 403` | `tests/Feature/Authorization/DistributorPortalSelectorTest.php` | PASS 🟢 | 3.342s |
| R3.1 | `storage local upload without valid signature returns 403` | `tests/Feature/Authorization/SignedStorageUploadTest.php` | PASS 🟢 | 0.019s |
| R3.1 | `storage local upload with valid signature is not forbidden` | `tests/Feature/Authorization/SignedStorageUploadTest.php` | PASS 🟢 | 0.034s |
| R3.2 | `deterministic generation with fixed vector` | `tests/Unit/Security/BlindIndexTest.php` | PASS 🟢 | 0.182s |
| R3.2 | `employee nik is encrypted and searchable via blind index` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.021s |
| R3.2 | `mall tenant npwp is encrypted and searchable via blind index` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.023s |
| R3.2 | `party and legal entity pii encryption and blind index` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.041s |
| R3.2 | `plm lab notebook formula is encrypted` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.033s |
| R3.2 | `webhook secrets are encrypted in database` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.019s |
| R3.2 | `developer and partner api keys are one way hashed in database` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.018s |
| R3.2 | `admin can view all salaries and payrolls` | `tests/Feature/Authorization/SalaryAuthorizationPolicyTest.php` | PASS 🟢 | 0.095s |
| R3.2 | `hcm manager can view all salaries and payrolls` | `tests/Feature/Authorization/SalaryAuthorizationPolicyTest.php` | PASS 🟢 | 0.102s |
| R3.2 | `staff cannot view other employees salaries or payrolls` | `tests/Feature/Authorization/SalaryAuthorizationPolicyTest.php` | PASS 🟢 | 0.118s |
| R3.2 | `staff can view own salary and payroll slip` | `tests/Feature/Authorization/SalaryAuthorizationPolicyTest.php` | PASS 🟢 | 0.092s |
| R3.2 | `it enforces that every Eloquent model with sensitive columns declares encrypted or hashed cast` | `tests/Architecture/SensitiveDataCastingTest.php` | PASS 🟢 | 0.176s |
| R3.3 | `payout rejected when hold window has not passed` | `tests/Feature/Venue/CreatorRoyaltyPayoutGuardTest.php` | PASS 🟢 | 0.054s |
| R3.3 | `payout succeeds when hold window has passed` | `tests/Feature/Venue/CreatorRoyaltyPayoutGuardTest.php` | PASS 🟢 | 0.024s |
| R3.3 | `payout rejected when contract is disputed` | `tests/Feature/Venue/CreatorRoyaltyPayoutGuardTest.php` | PASS 🟢 | 0.027s |
| R3.3 | `payout returns zero when no held royalties` | `tests/Feature/Venue/CreatorRoyaltyPayoutGuardTest.php` | PASS 🟢 | 0.034s |
| R3.4 | `non admin cannot access integration dashboard` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.038s |
| R3.4 | `admin can access integration dashboard` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.036s |
| R3.4 | `unauthenticated user redirected from integration dashboard` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.051s |
| R3.4 | `api client rate limiting enforced by api key` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.040s |
| R3.4 | `api client rate limiting enforced by client id header` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.035s |
| R3.4 | `api client rate limiter resets after clearing` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.033s |

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

- **Tanggal:** 2026-10-16
- **Verifikator:** Antigravity Independent Verifier
- **Commit yang diverifikasi:** `61b1f67534560e07f32c650255117defdb02c8ec` (feature/fase-r3c-a6-api-quota)
- **Keputusan:** 🔁 DIBUKA KEMBALI

| # | Hasil | Bukti (command / commit / output) | Catatan |
|---|---|---|---|
| C1 | lulus | `vendor/bin/pest tests/Architecture/ProgressIntegrityTest.php` exit 0 (2 passed). Sabotase S1–S7 terbukti merah di test harness. | Integritas format progress dan validasi bukti teruji. |
| C2 | gagal | CI push run `38771396609` (r3c) dan PR #14 run `38772879400` gagal (exit 1) pada step `Install Composer dependencies`: `RuntimeException: BLIND_INDEX_PEPPER environment variable is required in production environment.` Lokal `composer gate` exit 0 (9/9 langkah, 2.754 passed). | CI GitHub Actions merah akibat crash `package:discover` saat `composer install` sebelum `.env` dipasang. |
| C3 | gagal | `route-dynamic-pending.json` turun dari 280 ke 159 (-121 rute / -43.21%), belum mencapai target DoR/Prompt ≥ 50% (target ≤ 140 rute). | Minus P2 (M-R3-5). Teks item R3.1–R3.4 utuh tanpa modifikasi. |
| C4 | lulus | `RouteAuthorizationMatrixTest` 7 passed (1.940 asersi); 37 rute tulis POST Manufacturing/Pricing/Partner 403 untuk auditor (`AuditorReadOnlyPolicyTest`); test kepemilikan resource customer (`CustomerResourceOwnershipTest`) 403 untuk customer B. | Otorisasi RBAC granular dan isolasi data kepemilikan terbukti. |
| C5 | lulus | `php artisan bank:reconcile` exit 0 (129 akun balance 0 selisih); `VenueCreatorEconomyServiceTest` dan `CreatorRoyaltyPayoutGuardTest` lulus tanpa menaikkan baseline TestHygiene. | Ledger balance & invarian terpenuhi. |
| C6 | lulus | `php artisan chain:audit-all` exit 0 (18 audit modul lulus). Korupsi data sumber memicu kegagalan audit. | Audit rantai pasok dan operasional lulus. |
| C7 | lulus | Mutasi sabotase S-R3-1 s/d S-R3-6 semuanya berbalik merah dan menghentikan pipeline. Test pembukti mengeksekusi kode nyata tanpa konstanta tiruan (`CustomerResourceOwnershipTest`, `ApiClientRateLimitBlackBoxProofTest`). | Uji verifikasi aktif dan menguji fungsionalitas nyata. |
| C8 | lulus | `arch-scan.json` A6 turun 133→132 (hanya VenueCreatorEconomyService), A7 turun 5→0 (bersih); `route-auth-entries.json` turun 299→20 (tersisa identitas user core); baseline tidak ada yang bertambah. | Baseline tidak bocor; approved_additions utuh. |
| C9 | lulus | `git diff origin/master..HEAD -- docs/PROGRESS.md | grep -E '^[-+]\s*- \[[ x]\]'` hanya mengubah `[ ]` menjadi `[x]`, teks item R3.1–R3.4 100% identik karakter per karakter. | Integritas teks item terjaga. |
| C10 | lulus | SQLite `migrate:fresh --seed` exit 0; `MysqlSchemaCompatibilityTest` (12 passed) & `SchemaIdentifierLengthTest` (4 passed) exit 0. Kolom sensitif terenkripsi dan blind index ber-pepper. | Portabilitas skema SQLite & MySQL 8.4 terverifikasi. |
| C11 | gagal | Pelaksana belum membuat tabel 6 kolom `Register Minus Fase R3` di `docs/PROGRESS.md`. M-R0-2, 12, 13, 18 dicoret sah; M-R0-3 terbuka dengan rincian. Verifikator menambahkan tabel Register Minus R3 (M-R3-1 s/d M-R3-7). | Minus P1 (M-R3-4). |
| C12 | gagal | `docs/DECISIONS.md` tidak memiliki entri/anchor untuk keputusan D1 s/d D6 R3 (`git diff origin/master..HEAD -- docs/DECISIONS.md` kosong). Prosedur `docs/RUNBOOK.md` §8.2 merujuk command fiktif `app:rotate-blind-index` yang belum diimplementasikan. | Minus P1 (M-R3-2, M-R3-3). |
| C13 | lulus | 34 commit terdistribusi rapi per branch (`fase-r3-otorisasi` 19 commit, `fase-r3b-pii` 7 commit, `fase-r3c-a6-api-quota` 8 commit). Basis ter-stack rapi: r3b berpangkal pada r3, r3c berpangkal pada r3b. | Riwayat commit jelas dan terdokumentasi per modul/item. |
| C14 | lulus | `git diff origin/master..HEAD -U0 -- '*Test.php' | grep -nE "assertTrue\(true\)|markTestSkipped|->skip\(|toBeTrue\(\)$"` exit 1 (0 temuan). Tidak ada red flags test palsu. | Test suite kredibel dan bebas pemalsuan asersi. |

**Minus yang ditambahkan verifikator:** M-R3-1 (P0: Blocker CI BLIND_INDEX_PEPPER), M-R3-2 (P1: Entri D1-D6 di DECISIONS.md hilang), M-R3-3 (P1: Command RUNBOOK app:rotate-blind-index belum ada), M-R3-4 (P1: Register Minus R3 belum dibuat), M-R3-5 (P2: Target dynamic pending 43.2% < 50%), M-R3-6 (P2: Test rate limit pelaksana rapuh), M-R3-7 (P2: Push 3 branch tanpa izin pemilik).
**Alasan 🔁 (bila ada):** CI PR/push merah akibat deadlock `BLIND_INDEX_PEPPER` saat `composer install`, keputusan arsitektur D1–D6 belum ditulis di `DECISIONS.md`, command rotasi di `RUNBOOK.md` belum ada di kode, Register Minus R3 belum dibuat pelaksana, dan ratchet pending dynamic routes (43.2%) belum mencapai target ≥ 50%.


