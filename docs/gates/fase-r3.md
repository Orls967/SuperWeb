# Quality Gate Report: Fase R3

- **Commit:** `c26e50ea0d2675528d7294d860e66fa257264144` (c26e50e)
- **Tanggal:** 2026-10-15T11:25:58+00:00
- **PHP:** 8.4.26
- **Database:** sqlite (v3.53.4)
- **Status Keseluruhan:** PASS 🟢

## 1. Ringkasan Test Suite (Pest / JUnit)
- **Total Test:** 2716
- **Total Assertion:** 13448
- **Durasi:** 1,231.60s
- **Gagal / Error:** 0 failures, 0 errors

## 2. Hasil Perintah Protokol P4
| Perintah | Deskripsi | Exit Code | Status |
|---|---|---|---|
| `composer validate --strict` | Langkah gate: composer validate --strict (0.20s) | 0 | PASS 🟢 |
| `vendor/bin/pest --parallel --log-junit=storage/logs/pest-junit.xml` | Langkah gate: pest (135.32s) | 0 | PASS 🟢 |
| `vendor/bin/pint --test` | Langkah gate: pint --test (3.74s) | 0 | PASS 🟢 |
| `php artisan arch:scan --json` | Langkah gate: arch:scan --json (2.67s) | 0 | PASS 🟢 |
| `php artisan migrate:fresh --seed --force` | Langkah gate: migrate:fresh --seed (27.44s) | 0 | PASS 🟢 |
| `php artisan bank:reconcile` | Langkah gate: bank:reconcile (0.31s) | 0 | PASS 🟢 |
| `php artisan chain:audit-all` | Langkah gate: chain:audit-all (0.29s) | 0 | PASS 🟢 |
| `php artisan super:health-check` | Langkah gate: super:health-check (0.22s) | 0 | PASS 🟢 |
| `npm run build` | Langkah gate: npm run build (1.18s) | 0 | PASS 🟢 |

## 3. Status Test Blok Bukti Fase R3
| Item | Nama Test | File | Status (JUnit) | Durasi |
|---|---|---|---|---|
| R3.1 | `it maps every registered route in route-roles.php` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 0.030s |
| R3.1 | `it confirms authorized roles are not forbidden (not 403) on role-protected routes` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 11.104s |
| R3.1 | `it enforces that unauthorized roles receive 403 on role-protected routes` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 1.088s |
| R3.1 | `all 37 write routes forbid auditor role` | `tests/Feature/Authorization/AuditorReadOnlyPolicyTest.php` | PASS 🟢 | 2.608s |
| R3.1 | `admin without query receives 200 with distributor list selector` | `tests/Feature/Authorization/DistributorPortalSelectorTest.php` | PASS 🟢 | 5.443s |
| R3.1 | `admin with distributor query receives 200 with data and audit log` | `tests/Feature/Authorization/DistributorPortalSelectorTest.php` | PASS 🟢 | 5.472s |
| R3.1 | `non admin distributor attempting to select distributor receives 403` | `tests/Feature/Authorization/DistributorPortalSelectorTest.php` | PASS 🟢 | 5.328s |
| R3.1 | `storage local upload without valid signature returns 403` | `tests/Feature/Authorization/SignedStorageUploadTest.php` | PASS 🟢 | 0.045s |
| R3.1 | `storage local upload with valid signature is not forbidden` | `tests/Feature/Authorization/SignedStorageUploadTest.php` | PASS 🟢 | 0.062s |

## 4. Ringkasan Arsitektur (`arch:scan`)
- **Status Baseline:** Terpasang (7265 pelanggaran terbaseline).

| Aturan | Deskripsi Singkat | Jumlah Pelanggaran |
|---|---|---|
| `A1` | Import/penggunaan Domain modul lain | 428 |
| `A2` | Tabel berprefiks modul lain / tidak terdaftar | 5254 |
| `A3` | Uang bertipe float / kolom IDR decimal | 352 |
| `A4` | Idempotency key dari nilai acak/waktu | 44 |
| `A5` | Tipe posting ledger > 32 char / di luar registry | 101 |
| `A6` | Parameter bool kontrol di Application/ | 133 |
| `A7` | Hash tanpa kunci untuk data sensitif | 5 |
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

