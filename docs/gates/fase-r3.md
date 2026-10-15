# Quality Gate Report: Fase r3

- **Commit:** `adec8876402eaec0c5ecf000b09da708c9035293` (adec887)
- **Tanggal:** 2026-10-15T17:59:29+00:00
- **PHP:** 8.4.26
- **Database:** sqlite (v3.53.4)
- **Status Keseluruhan:** PASS 🟢

## 1. Ringkasan Test Suite (Pest / JUnit)
- **Total Test:** 2755
- **Total Assertion:** 13694
- **Durasi:** 688.31s
- **Gagal / Error:** 0 failures, 0 errors

## 2. Hasil Perintah Protokol P4
| Perintah | Deskripsi | Exit Code | Status |
|---|---|---|---|
| `composer validate --strict` | Langkah gate: composer validate --strict (0.20s) | 0 | PASS 🟢 |
| `vendor/bin/pest --parallel --log-junit=storage/logs/pest-junit.xml` | Langkah gate: pest (79.89s) | 0 | PASS 🟢 |
| `vendor/bin/pint --test` | Langkah gate: pint --test (1.14s) | 0 | PASS 🟢 |
| `php artisan arch:scan --json` | Langkah gate: arch:scan --json (2.61s) | 0 | PASS 🟢 |
| `php artisan migrate:fresh --seed --force` | Langkah gate: migrate:fresh --seed (26.90s) | 0 | PASS 🟢 |
| `php artisan bank:reconcile` | Langkah gate: bank:reconcile (0.30s) | 0 | PASS 🟢 |
| `php artisan chain:audit-all` | Langkah gate: chain:audit-all (0.21s) | 0 | PASS 🟢 |
| `php artisan super:health-check` | Langkah gate: super:health-check (0.22s) | 0 | PASS 🟢 |
| `npm run build` | Langkah gate: npm run build (1.00s) | 0 | PASS 🟢 |

## 3. Status Test Blok Bukti Fase r3
| Item | Nama Test | File | Status (JUnit) | Durasi |
|---|---|---|---|---|
| R3.1 | `it maps every registered route in route-roles.php` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 0.021s |
| R3.1 | `it confirms authorized roles are not forbidden (not 403) on role-protected routes` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 9.492s |
| R3.1 | `it enforces that unauthorized roles receive 403 on role-protected routes` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 1.290s |
| R3.1 | `all 37 write routes forbid auditor role` | `tests/Feature/Authorization/AuditorReadOnlyPolicyTest.php` | PASS 🟢 | 0.107s |
| R3.1 | `admin without query receives 200 with distributor list selector` | `tests/Feature/Authorization/DistributorPortalSelectorTest.php` | PASS 🟢 | 2.879s |
| R3.1 | `admin with distributor query receives 200 with data and audit log` | `tests/Feature/Authorization/DistributorPortalSelectorTest.php` | PASS 🟢 | 2.678s |
| R3.1 | `non admin distributor attempting to select distributor receives 403` | `tests/Feature/Authorization/DistributorPortalSelectorTest.php` | PASS 🟢 | 2.718s |
| R3.1 | `storage local upload without valid signature returns 403` | `tests/Feature/Authorization/SignedStorageUploadTest.php` | PASS 🟢 | 0.022s |
| R3.1 | `storage local upload with valid signature is not forbidden` | `tests/Feature/Authorization/SignedStorageUploadTest.php` | PASS 🟢 | 0.028s |
| R3.2 | `deterministic generation with fixed vector` | `tests/Unit/Security/BlindIndexTest.php` | PASS 🟢 | 0.180s |
| R3.2 | `employee nik is encrypted and searchable via blind index` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.022s |
| R3.2 | `mall tenant npwp is encrypted and searchable via blind index` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.015s |
| R3.2 | `party and legal entity pii encryption and blind index` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.016s |
| R3.2 | `plm lab notebook formula is encrypted` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.019s |
| R3.2 | `webhook secrets are encrypted in database` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.016s |
| R3.2 | `developer and partner api keys are one way hashed in database` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.027s |
| R3.2 | `admin can view all salaries and payrolls` | `tests/Feature/Authorization/SalaryAuthorizationPolicyTest.php` | PASS 🟢 | 0.117s |
| R3.2 | `hcm manager can view all salaries and payrolls` | `tests/Feature/Authorization/SalaryAuthorizationPolicyTest.php` | PASS 🟢 | 0.097s |
| R3.2 | `staff cannot view other employees salaries or payrolls` | `tests/Feature/Authorization/SalaryAuthorizationPolicyTest.php` | PASS 🟢 | 0.098s |
| R3.2 | `staff can view own salary and payroll slip` | `tests/Feature/Authorization/SalaryAuthorizationPolicyTest.php` | PASS 🟢 | 0.105s |
| R3.2 | `it enforces that every Eloquent model with sensitive columns declares encrypted or hashed cast` | `tests/Architecture/SensitiveDataCastingTest.php` | PASS 🟢 | 0.191s |
| R3.3 | `payout rejected when hold window has not passed` | `tests/Feature/Venue/CreatorRoyaltyPayoutGuardTest.php` | PASS 🟢 | 0.036s |
| R3.3 | `payout succeeds when hold window has passed` | `tests/Feature/Venue/CreatorRoyaltyPayoutGuardTest.php` | PASS 🟢 | 0.024s |
| R3.3 | `payout rejected when contract is disputed` | `tests/Feature/Venue/CreatorRoyaltyPayoutGuardTest.php` | PASS 🟢 | 0.022s |
| R3.3 | `payout returns zero when no held royalties` | `tests/Feature/Venue/CreatorRoyaltyPayoutGuardTest.php` | PASS 🟢 | 0.030s |
| R3.4 | `non admin cannot access integration dashboard` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.031s |
| R3.4 | `admin can access integration dashboard` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.035s |
| R3.4 | `unauthenticated user redirected from integration dashboard` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.016s |
| R3.4 | `api client rate limiting enforced by api key` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.037s |
| R3.4 | `api client rate limiting enforced by client id header` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.025s |
| R3.4 | `api client rate limiter resets after clearing` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.028s |
| R3.5 | `enforces proof block integrity and phase verification in docs/PROGRESS.md` | `tests/Architecture/ProgressIntegrityTest.php` | PASS 🟢 | 1.373s |
| R3.5 | `it maps every registered route in route-roles.php` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 0.021s |
| R3.5 | `rotate blind index dry run does not mutate database` | `tests/Feature/Security/RotateBlindIndexCommandTest.php` | PASS 🟢 | 0.027s |
| R3.5 | `rotate blind index with force updates records idempotently` | `tests/Feature/Security/RotateBlindIndexCommandTest.php` | PASS 🟢 | 0.029s |
| R3.5 | `black box client rate limiting and isolation` | `tests/Feature/Authorization/ApiClientRateLimitBlackBoxProofTest.php` | PASS 🟢 | 2.470s |

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
- **Commit yang diverifikasi:** `ea89d37575ab9ff6bb9d49b47b2272068f143ad6` (verify/fase-r3)
- **Keputusan:** ✅ TERVERIFIKASI

| # | Hasil | Bukti (command / commit / output) | Catatan |
|---|---|---|---|
| C1 | lulus | `vendor/bin/pest tests/Architecture/ProgressIntegrityTest.php` exit 0 (2 passed). Sabotase S1–S7 terbukti merah di test harness. | Integritas format progress dan validasi bukti teruji. |
| C2 | lulus | CI workflow `.github/workflows/ci.yml` diperbaiki memasang `.env` dan `BLIND_INDEX_PEPPER` sebelum `composer install`; `composer gate` lokal lulus 9/9 langkah (2.755 passed, 13.694 assertions). M-R3-1 tuntas. | CI pipeline siap hijau di SQLite, MySQL, dan mutation testing. |
| C3 | lulus | `route-dynamic-pending.json` turun dari 280 ke 136 rute (-144 rute / -51.43%), melampaui target DoR/Prompt ≥ 50% (target ≤ 140 rute). M-R3-5 tuntas. Teks item R3.1–R3.5 utuh. | Ratchet dynamic pending routes terpenuhi. |
| C4 | lulus | `RouteAuthorizationMatrixTest` 7 passed (1.940 asersi); 37 rute tulis POST Manufacturing/Pricing/Partner 403 untuk auditor (`AuditorReadOnlyPolicyTest`); test kepemilikan resource customer (`CustomerResourceOwnershipTest`) 403 untuk customer B. | Otorisasi RBAC granular dan isolasi data kepemilikan terbukti. |
| C5 | lulus | `php artisan bank:reconcile` exit 0 (129 akun balance 0 selisih); `VenueCreatorEconomyServiceTest` dan `CreatorRoyaltyPayoutGuardTest` lulus tanpa menaikkan baseline TestHygiene. | Ledger balance & invarian terpenuhi. |
| C6 | lulus | `php artisan chain:audit-all` exit 0 (18 audit modul lulus). Korupsi data sumber memicu kegagalan audit. | Audit rantai pasok dan operasional lulus. |
| C7 | lulus | Mutasi sabotase S-R3-1 s/d S-R3-6 semuanya berbalik merah dan menghentikan pipeline. Test pembukti mengeksekusi kode nyata tanpa konstanta tiruan (`CustomerResourceOwnershipTest`, `ApiClientRateLimitBlackBoxProofTest`, `RotateBlindIndexCommandTest`). | Uji verifikasi aktif dan menguji fungsionalitas nyata. |
| C8 | lulus | `arch-scan.json` A6 turun 133→132 (hanya VenueCreatorEconomyService), A7 turun 5→0 (bersih); `route-auth-entries.json` turun 299→20 (tersisa identitas user core); baseline tidak ada yang bertambah. | Baseline tidak bocor; approved_additions utuh. |
| C9 | lulus | `git diff origin/master..HEAD -- docs/PROGRESS.md | grep -E '^[-+]\s*- \[[ x]\]'` hanya mengubah `[ ]` menjadi `[x]`, teks item R3.1–R3.5 100% identik karakter per karakter. | Integritas teks item terjaga. |
| C10 | lulus | SQLite `migrate:fresh --seed` exit 0; `MysqlSchemaCompatibilityTest` (12 passed) & `SchemaIdentifierLengthTest` (4 passed) exit 0. Kolom sensitif terenkripsi dan blind index ber-pepper. | Portabilitas skema SQLite & MySQL 8.4 terverifikasi. |
| C11 | lulus | Tabel 6 kolom `Register Minus Fase R3` di `docs/PROGRESS.md` lengkap dan terverifikasi. M-R3-1 s/d M-R3-7 seluruhnya telah diselesaikan dan dicoret sah beserta ringkasan tindak lanjutnya. M-R0-2, 12, 13, 18 tuntas ditutup. | Minus P0/P1/P2 terselesaikan. |
| C12 | lulus | `docs/DECISIONS.md` memuat entri & anchor D1 s/d D6 lengkap (`#2026-10-15-fase-r3-d1-d6`). Command `app:rotate-blind-index` terimplementasi dan teruji di `RotateBlindIndexCommandTest`. M-R3-2 dan M-R3-3 tuntas. | Dokumentasi arsitektur & operational runbook sinkron. |
| C13 | lulus | Commit terdistribusi rapi, atomik, dan terdokumentasi per modul dan tahap remediasi. Branch `verify/fase-r3` terstruktur. | Tata kelola git bersih. |
| C14 | lulus | `git diff origin/master..HEAD -U0 -- '*Test.php' | grep -nE "assertTrue\(true\)|markTestSkipped|->skip\(|toBeTrue\(\)$"` exit 1 (0 temuan). Tidak ada red flags test palsu. | Test suite kredibel dan bebas pemalsuan asersi. |

**Status Minus:** Seluruh minus M-R3-1 s/d M-R3-7 tuntas diremediasi (0 minus terbuka).
**Alasan ✅:** Seluruh kriteria C1 s/d C14 lulus tanpa catatan terbuka; 9/9 langkah quality gate lulus pada commit bersih; CI fix BLIND_INDEX_PEPPER terpasang; target ratchet dynamic route pending terpenuhi (-51.43%); keputusan arsitektur D1–D6 dan command rotasi pepper terdokumentasi dan teruji.

