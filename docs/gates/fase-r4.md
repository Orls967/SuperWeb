# Quality Gate Report: Fase R4

- **Commit:** `1fd5b27cdb8d73f3de02a2d44f7ff327b2f44b76` (1fd5b27)
- **Tanggal:** 2026-10-16 17:54:02 UTC
- **PHP:** 8.4.26
- **Database:** sqlite (v3.53.4)
- **Status Keseluruhan:** FAIL 🔴

## 1. Ringkasan Test Suite (Pest / JUnit)
- **Total Test:** 2907
- **Total Assertion:** 17484
- **Durasi:** 735.44s
- **Gagal / Error:** 1 failures, 0 errors

## 2. Hasil Perintah Protokol P4
| Perintah | Deskripsi | Exit Code | Status |
|---|---|---|---|
| `composer gate` | Test suite penuh + lint Pint + arch test + npm build | 0 | PASS 🟢 |
| `php artisan arch:scan` | Pemindaian aturan arsitektur A1–A13 (ratchet) | 0 | PASS 🟢 |
| `php artisan bank:reconcile` | Rekonsiliasi double-entry ledger & bank | 0 | PASS 🟢 |
| `php artisan chain:audit-all` | Audit integritas semua rantai transaksi | 0 | PASS 🟢 |
| `php artisan super:health-check` | Pemeriksaan kesehatan sistem pilar | 0 | PASS 🟢 |

## 3. Status Test Blok Bukti Fase R4
| Item | Nama Test | File | Status (JUnit) | Durasi |
|---|---|---|---|---|
| R4.1 | `it enforces declare(strict_types=1) across all module PHP files excluding migrations and tests` | `tests/Architecture/StrictTypesComplianceTest.php` | PASS 🟢 | 0.471s |
| R4.1 | `domain does not depend on Http` | `tests/Architecture/ModuleBoundariesTest.php` | PASS 🟢 | 0.302s |
| R4.2 | `it ensures table prefixes are well-formed and unique` | `tests/Architecture/ModulePrefixRegistryTest.php` | PASS 🟢 | 0.002s |
| R4.2 | `it resolves specific disambiguated prefixes correctly` | `tests/Architecture/ModulePrefixRegistryTest.php` | PASS 🟢 | 0.001s |
| R4.2 | `it flags foreign table access via ForeignTablePrefixRule` | `tests/Architecture/ModulePrefixRegistryTest.php` | PASS 🟢 | 0.001s |
| R4.3 | `it enforces that kernel modules (Shared, Core) have zero dependencies on business modules (A9 = 0)` | `tests/Architecture/KernelZeroBusinessDependencyTest.php` | PASS 🟢 | 0.028s |
| R4.4 | `it classifies all 345 services in modules/Integration/Application/Services` | `tests/Architecture/IntegrationServicesClassificationTest.php` | PASS 🟢 | 0.036s |
| R4.4 | `it verifies IntegrationServiceProvider has no unconfigured singletons` | `tests/Architecture/IntegrationServicesClassificationTest.php` | PASS 🟢 | 0.027s |
| R4.4 | `it ensures docs/INTEGRATION_SERVICES_CLASSIFICATION.md exists and documents all 345 services` | `tests/Architecture/IntegrationServicesClassificationTest.php` | PASS 🟢 | 0.021s |
| R4.4 | `it confirms Integration services auto-resolve seamlessly via service container` | `tests/Architecture/IntegrationServicesClassificationTest.php` | PASS 🟢 | 0.018s |
| R4.6 | `it enforces that no module model uses unguarded array` | `tests/Architecture/ModelFillableIntegrityTest.php` | PASS 🟢 | 0.479s |
| R4.6 | `it verifies arch-scan baseline locks A10 at zero` | `tests/Architecture/ModelFillableIntegrityTest.php` | PASS 🟢 | 0.017s |

## 4. Ringkasan Arsitektur (`arch:scan`)
- **Status Baseline:** Terpasang (6501 pelanggaran terbaseline).

| Aturan | Deskripsi Singkat | Jumlah Pelanggaran |
|---|---|---|
| `A1` | Import/penggunaan Domain modul lain | 219 |
| `A2` | Tabel berprefiks modul lain / tidak terdaftar | 5240 |
| `A3` | Uang bertipe float / kolom IDR decimal | 319 |
| `A4` | Idempotency key dari nilai acak/waktu | 0 |
| `A5` | Tipe posting ledger > 32 char / di luar registry | 0 |
| `A6` | Parameter bool kontrol di Application/ | 132 |
| `A7` | Hash tanpa kunci untuk data sensitif | 0 |
| `A8` | Carbon/Date::setTestNow di produksi | 3 |
| `A9` | Kernel bergantung pada modul bisnis | 0 |
| `A10` | Model dengan $guarded = [] | 0 |
| `A11` | File PHP tanpa declare(strict_types=1) | 0 |
| `A12` | Kolom *_id di migrasi tanpa FK | 588 |
| `A13` | back()->errors() (HTTP 500 di jalur galat) | 0 |

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

