# Quality Gate Report: Fase r3

- **Commit:** `c730522dc8d783c0f8e73e3b3579a7a7d7a12b6c` (c730522)
- **Tanggal:** 2026-10-15T19:02:08+00:00
- **PHP:** 8.4.26
- **Database:** sqlite (v3.53.4)
- **Status Keseluruhan:** PASS 🟢

## 1. Ringkasan Test Suite (Pest / JUnit)
- **Total Test:** 2755
- **Total Assertion:** 13694
- **Durasi:** 950.74s
- **Gagal / Error:** 0 failures, 0 errors

## 2. Hasil Perintah Protokol P4
| Perintah | Deskripsi | Exit Code | Status |
|---|---|---|---|
| `composer validate --strict` | Langkah gate: composer validate --strict (0.24s) | 0 | PASS 🟢 |
| `vendor/bin/pest --parallel --log-junit=storage/logs/pest-junit.xml` | Langkah gate: pest (107.42s) | 0 | PASS 🟢 |
| `vendor/bin/pint --test` | Langkah gate: pint --test (0.74s) | 0 | PASS 🟢 |
| `php artisan arch:scan --json` | Langkah gate: arch:scan --json (2.70s) | 0 | PASS 🟢 |
| `php artisan migrate:fresh --seed --force` | Langkah gate: migrate:fresh --seed (27.46s) | 0 | PASS 🟢 |
| `php artisan bank:reconcile` | Langkah gate: bank:reconcile (0.32s) | 0 | PASS 🟢 |
| `php artisan chain:audit-all` | Langkah gate: chain:audit-all (0.29s) | 0 | PASS 🟢 |
| `php artisan super:health-check` | Langkah gate: super:health-check (0.22s) | 0 | PASS 🟢 |
| `npm run build` | Langkah gate: npm run build (1.06s) | 0 | PASS 🟢 |

## 3. Status Test Blok Bukti Fase r3
| Item | Nama Test | File | Status (JUnit) | Durasi |
|---|---|---|---|---|
| R3.1 | `it maps every registered route in route-roles.php` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 0.027s |
| R3.1 | `it confirms authorized roles are not forbidden (not 403) on role-protected routes` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 10.966s |
| R3.1 | `it enforces that unauthorized roles receive 403 on role-protected routes` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 1.393s |
| R3.1 | `all 37 write routes forbid auditor role` | `tests/Feature/Authorization/AuditorReadOnlyPolicyTest.php` | PASS 🟢 | 0.164s |
| R3.1 | `admin without query receives 200 with distributor list selector` | `tests/Feature/Authorization/DistributorPortalSelectorTest.php` | PASS 🟢 | 4.491s |
| R3.1 | `admin with distributor query receives 200 with data and audit log` | `tests/Feature/Authorization/DistributorPortalSelectorTest.php` | PASS 🟢 | 3.260s |
| R3.1 | `non admin distributor attempting to select distributor receives 403` | `tests/Feature/Authorization/DistributorPortalSelectorTest.php` | PASS 🟢 | 4.110s |
| R3.1 | `storage local upload without valid signature returns 403` | `tests/Feature/Authorization/SignedStorageUploadTest.php` | PASS 🟢 | 0.050s |
| R3.1 | `storage local upload with valid signature is not forbidden` | `tests/Feature/Authorization/SignedStorageUploadTest.php` | PASS 🟢 | 0.065s |
| R3.2 | `deterministic generation with fixed vector` | `tests/Unit/Security/BlindIndexTest.php` | PASS 🟢 | 0.132s |
| R3.2 | `employee nik is encrypted and searchable via blind index` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.123s |
| R3.2 | `mall tenant npwp is encrypted and searchable via blind index` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.025s |
| R3.2 | `party and legal entity pii encryption and blind index` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.027s |
| R3.2 | `plm lab notebook formula is encrypted` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.027s |
| R3.2 | `webhook secrets are encrypted in database` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.048s |
| R3.2 | `developer and partner api keys are one way hashed in database` | `tests/Feature/Security/SensitiveDataEncryptionTest.php` | PASS 🟢 | 0.064s |
| R3.2 | `admin can view all salaries and payrolls` | `tests/Feature/Authorization/SalaryAuthorizationPolicyTest.php` | PASS 🟢 | 0.081s |
| R3.2 | `hcm manager can view all salaries and payrolls` | `tests/Feature/Authorization/SalaryAuthorizationPolicyTest.php` | PASS 🟢 | 0.126s |
| R3.2 | `staff cannot view other employees salaries or payrolls` | `tests/Feature/Authorization/SalaryAuthorizationPolicyTest.php` | PASS 🟢 | 0.236s |
| R3.2 | `staff can view own salary and payroll slip` | `tests/Feature/Authorization/SalaryAuthorizationPolicyTest.php` | PASS 🟢 | 0.419s |
| R3.2 | `it enforces that every Eloquent model with sensitive columns declares encrypted or hashed cast` | `tests/Architecture/SensitiveDataCastingTest.php` | PASS 🟢 | 0.252s |
| R3.3 | `payout rejected when hold window has not passed` | `tests/Feature/Venue/CreatorRoyaltyPayoutGuardTest.php` | PASS 🟢 | 0.105s |
| R3.3 | `payout succeeds when hold window has passed` | `tests/Feature/Venue/CreatorRoyaltyPayoutGuardTest.php` | PASS 🟢 | 0.040s |
| R3.3 | `payout rejected when contract is disputed` | `tests/Feature/Venue/CreatorRoyaltyPayoutGuardTest.php` | PASS 🟢 | 0.023s |
| R3.3 | `payout returns zero when no held royalties` | `tests/Feature/Venue/CreatorRoyaltyPayoutGuardTest.php` | PASS 🟢 | 0.019s |
| R3.4 | `non admin cannot access integration dashboard` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.124s |
| R3.4 | `admin can access integration dashboard` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.078s |
| R3.4 | `unauthenticated user redirected from integration dashboard` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.084s |
| R3.4 | `api client rate limiting enforced by api key` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.052s |
| R3.4 | `api client rate limiting enforced by client id header` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.037s |
| R3.4 | `api client rate limiter resets after clearing` | `tests/Feature/Integration/ApiClientRateLimitingTest.php` | PASS 🟢 | 0.053s |
| R3.5 | `enforces proof block integrity and phase verification in docs/PROGRESS.md` | `tests/Architecture/ProgressIntegrityTest.php` | PASS 🟢 | 1.477s |
| R3.5 | `it maps every registered route in route-roles.php` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 0.027s |
| R3.5 | `rotate blind index dry run does not mutate database` | `tests/Feature/Security/RotateBlindIndexCommandTest.php` | PASS 🟢 | 0.048s |
| R3.5 | `rotate blind index with force updates records idempotently` | `tests/Feature/Security/RotateBlindIndexCommandTest.php` | PASS 🟢 | 0.048s |
| R3.5 | `black box client rate limiting and isolation` | `tests/Feature/Authorization/ApiClientRateLimitBlackBoxProofTest.php` | PASS 🟢 | 2.445s |

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

- **Tanggal:** 2026-11-03
- **Verifikator:** sesi verifikator 2026-11-03
- **Commit yang diverifikasi:** 86321fbf (CI master: SQLite ✅, MySQL ✅, Sharded Matrix ✅, Regresi ✅)
- **Keputusan:** ✅ TERVERIFIKASI (LULUS C1–C14)

| # | Hasil | Bukti (command / commit / output) | Catatan |
|---|---|---|---|
| C1 | LULUS 🟢 | `vendor/bin/pest tests/Architecture/ProgressIntegrityTest.php` exit 0 (2 passed, 2 assertions). Format PROGRESS.md dan blok Bukti valid. | Integritas format progress dan validasi bukti teruji. |
| C2 | LULUS 🟢 | CI master 86321fbf hijau penuh (runs: 41585985407, 41585985438, 41585985445). Lokal: `arch:scan` Baru=0 Basi=0, `bank:reconcile` exit 0 (0 selisih), `chain:audit-all` exit 0 (12/12 audit PASS). | Suite penuh divalidasi CI master. |
| C3 | LULUS 🟢 | Seluruh klausul R3.1–R3.5 terbukti via test otorisasi, enkripsi PII, rate limit, dan mitigasi IDOR (S-01 s/d S-08). Baseline dynamic pending routes terkontrol. | Tidak ada klaim fiktif. |
| C4 | LULUS 🟢 | `vendor/bin/pest tests/Architecture/RouteAuthorizationMatrixTest.php` exit 0 (7 passed, 4829 assertions); 37 rute tulis POST Manufacturing/Pricing/Partner 403 untuk auditor (`AuditorReadOnlyPolicyTest`); test kepemilikan resource customer 403 untuk pengguna lain (`CustomerResourceOwnershipTest`, `IdorVulnerabilityTest`). | Otorisasi RBAC granular dan isolasi data kepemilikan terbukti. |
| C5 | LULUS 🟢 | `php artisan bank:reconcile` exit 0 (0 akun selisih); pencairan royalti venue menolak bypass boolean jika hold window belum lewat (`CreatorRoyaltyPayoutGuardTest`). | Ledger balance & invarian terpenuhi. |
| C6 | LULUS 🟢 | `php artisan chain:audit-all` exit 0 (12/12 audit modul lulus). Korupsi data sumber memicu kegagalan audit. | Audit operasional lulus. |
| C7 | LULUS 🟢 | 3 sabotase kritis empiris terbukti mendeteksi: R3-A (IDOR DistributorPolicy::view dibypass) gagal exit 1 (200 bukan 403), R3-B (Matriks rute route-roles.php diubah ke 'auth') gagal exit 1, R3-C (Security headers SecurityHeadersMiddleware dinonaktifkan) gagal exit 1. Seluruhnya pulih hijau exit 0. | Test mengeksekusi kode nyata dan aktif mendeteksi pelanggaran keamanan. |
| C8 | LULUS 🟢 | `arch-scan.json` A7=0 (bersih); A6 modul non-Integration = 0; route-auth-entries hanya rute identitas core; baseline tidak bertambah. | Baseline tidak bocor; approved_additions utuh. |
| C9 | LULUS 🟢 | Teks item R3.1–R3.5 100% utuh dan identik karakter per karakter. | Integritas kontrak teks DoR terjaga (P10). |
| C10 | LULUS 🟢 | SQLite `migrate:fresh --seed` exit 0; CI MySQL 8 `portability-mysql` hijau penuh pada commit 86321fbf. Kolom sensitif terenkripsi dan blind index ber-pepper. | Portabilitas skema SQLite & MySQL terverifikasi. |
| C11 | LULUS 🟢 | Register Minus Fase R3 lengkap: M-R3-1 s/d M-R3-17 seluruhnya tuntas dicoret sah; M-R3-18 berstatus P2 (konsistensi ad-hoc controller di luar zona B) tidak memblokir rilis. 0 minus P0/P1 terbuka. | Kriteria P9/K-P9 terpenuhi. |
| C12 | LULUS 🟢 | `docs/DECISIONS.md` memuat entri & anchor D1 s/d D6 lengkap (`#2026-10-15-fase-r3-d1-d6`). Command `app:rotate-blind-index` terimplementasi dan teruji di `RotateBlindIndexCommandTest`. | Jejak keputusan arsitektur & runbook sinkron. |
| C13 | LULUS 🟢 | Riwayat commit terdistribusi rapi, atomik, commit verifikasi status diawali `docs(verifikasi-r3):`. | Tata kelola git bersih. |
| C14 | LULUS 🟢 | Test suite kredibel, mengeksekusi fungsionalitas nyata, bebas skip dan bebas pemalsuan asersi. | Red flags P12 bersih 100%. |

**Status Minus:** Seluruh minus P0/P1 (M-R3-1 s/d M-R3-17) tuntas diremediasi (0 minus P0/P1 terbuka; M-R3-18 adalah P2 yang didelegasikan ke pemeliharaan rutin zona terkait).
**Alasan ✅:** Seluruh kriteria C1 s/d C14 lulus tanpa catatan kritis terbuka; suite penuh terbukti hijau di CI master commit 86321fbf; otorisasi RBAC granular, proteksi IDOR, enkripsi data sensitif ber-blind index, dan kuota API terbukti aktif dan teruji empiris lewat 3 sabotase kritis.

