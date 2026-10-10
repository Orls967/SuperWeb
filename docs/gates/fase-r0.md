# Quality Gate Report: Fase R0

- **Commit:** `aaa4827fc2924be863019a5533c39e2a3396a3c5` (aaa4827)
- **Tanggal:** 2026-10-10 10:13:40 UTC
- **PHP:** 8.4.26
- **Database:** sqlite (v3.53.4)
- **Status Keseluruhan:** PASS 🟢

## 1. Ringkasan Test Suite (Pest / JUnit)
- **Total Test:** 2471
- **Total Assertion:** 10631
- **Durasi:** 258.49s
- **Gagal / Error:** 0 failures, 0 errors

## 2. Hasil Perintah Protokol P4
| Perintah | Deskripsi | Exit Code | Status |
|---|---|---|---|
| `composer gate` | Test suite penuh + lint Pint + arch test + npm build | 0 | PASS 🟢 |
| `php artisan arch:scan` | Pemindaian aturan arsitektur A1–A13 (ratchet) | 0 | PASS 🟢 |
| `php artisan bank:reconcile` | Rekonsiliasi double-entry ledger & bank | 0 | PASS 🟢 |
| `php artisan chain:audit-all` | Audit integritas semua rantai transaksi | 0 | PASS 🟢 |
| `php artisan super:health-check` | Pemeriksaan kesehatan sistem pilar | 0 | PASS 🟢 |

## 3. Status Test Blok Bukti Fase R0
| Item | Nama Test | File | Status (JUnit) | Durasi |
|---|---|---|---|---|
| R0.1 | `it ensures composer.json and README.md align on PHP ^8.4 requirement` | `tests/Architecture/DocsVersionConsistencyTest.php` | PASS 🟢 | 0.000s |
| R0.2 | `it generates gate report when test suite passes and matches JUnit fixture data` | `tests/Feature/Console/GateReportCommandTest.php` | PASS 🟢 | 0.034s |
| R0.3 | `it verifies CI workflow configuration exists and satisfies R0.3 specifications` | `tests/Architecture/CiWorkflowContractTest.php` | PASS 🟢 | 0.001s |
| R0.3 | `it preserves 64-bit integer money and 18-decimal precision across database engines` | `tests/Feature/Portability/DatabasePortabilityTest.php` | PASS 🟢 | 0.012s |
| R0.3.a | `it keeps production classes at their case-sensitive PSR-4 path` | `tests/Architecture/Psr4ComplianceTest.php` | PASS 🟢 | 0.253s |
| R0.4 | `it enforces proof block integrity and phase verification in docs/PROGRESS.md` | `tests/Architecture/ProgressIntegrityTest.php` | PASS 🟢 | 0.021s |
| R0.4 | `passes when valid proof block is provided for a checked item` | `tests/Unit/Quality/Progress/ProgressIntegrityScannerTest.php` | PASS 🟢 | 0.013s |
| R0.5 | `it ensures LAPORAN_AUDIT_GELOMBANG_2.md is explicitly marked as archived and invalid` | `tests/Architecture/DocsVersionConsistencyTest.php` | PASS 🟢 | 0.000s |
| R0.5 | `it ensures CODEBASE.md does not reference removed non-existent accounts` | `tests/Architecture/DocsVersionConsistencyTest.php` | PASS 🟢 | 0.001s |
| R0.6 | `it declares every artisan command name exactly once` | `tests/Architecture/CommandSignatureUniqueTest.php` | PASS 🟢 | 0.132s |
| R0.6 | `runs the integration and platform economy audits and passes on consistent data` | `modules/Integration/tests/Feature/ApiAuditCommandTest.php` | PASS 🟢 | 0.017s |
| R0.7 | `it verifies pull request template contains V1-V12 and C1-C14 checklists` | `tests/Architecture/GateTemplateContractTest.php` | PASS 🟢 | 0.001s |
| R0.7 | `it verifies docs/gates/README.md provides guidance on reading gate reports` | `tests/Architecture/GateTemplateContractTest.php` | PASS 🟢 | 0.000s |
| R0.8 | `it keeps module code within the arch:scan ratchet baseline` | `tests/Architecture/ArchScanBaselineTest.php` | PASS 🟢 | 2.228s |
| R0.8 | `fails and names the violation when it is not covered by the baseline` | `tests/Feature/Console/ArchScanCommandTest.php` | PASS 🟢 | 0.020s |
| R0.9 | `it maps every registered route in route-roles.php` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 0.012s |
| R0.9 | `it enforces that route authorization never weakens compared to baseline` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 0.015s |
| R0.9 | `it keeps pending dynamic routes within the ratchet baseline` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 0.010s |
| R0.9 | `it enforces that unauthorized roles receive 403 on role-protected routes` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 0.092s |
| R0.9 | `it confirms authorized roles are not forbidden (not 403) on role-protected routes` | `tests/Architecture/RouteAuthorizationMatrixTest.php` | PASS 🟢 | 0.371s |
| R0.10 | `it confirms bank:reconcile passes on clean state and fails when data is corrupted` | `tests/Architecture/AuditCommandContractTest.php` | PASS 🟢 | 0.015s |
| R0.10 | `it confirms api:audit passes on clean state and fails when signature is corrupted` | `tests/Architecture/AuditCommandContractTest.php` | PASS 🟢 | 0.013s |
| R0.10 | `it keeps audit commands without corruption fixtures within the ratchet baseline` | `tests/Architecture/AuditCommandContractTest.php` | PASS 🟢 | 0.013s |
| R0.10 | `it keeps unseeded ledger accounts within the ratchet baseline` | `tests/Architecture/LedgerAccountRegistryTest.php` | PASS 🟢 | 1.156s |
| R0.10 | `it keeps accounts with inverted normal balances within the ratchet baseline` | `tests/Architecture/LedgerNormalBalanceTest.php` | PASS 🟢 | 1.089s |
| R0.11 | `it keeps modules/Integration frozen except for external adapters and intg_ tables` | `tests/Architecture/IntegrationFreezeTest.php` | PASS 🟢 | 0.040s |
| R0.11 | `it keeps tests within the hygiene ratchet baseline` | `tests/Architecture/TestHygieneTest.php` | PASS 🟢 | 0.691s |
| R0.12 | `it verifies MutationTestCommand is registered and constructs correct Pest CLI invocation` | `tests/Architecture/MutationTestingContractTest.php` | PASS 🟢 | 0.015s |
| R0.12 | `it verifies CI workflow defines mutation testing job with PCOV and 60% minimum threshold` | `tests/Architecture/MutationTestingContractTest.php` | PASS 🟢 | 0.011s |
| R0.12 | `it verifies Pest CLI supports mutation testing options` | `tests/Architecture/MutationTestingContractTest.php` | PASS 🟢 | 0.230s |
| R0.13 | `it enforces proof block integrity and phase verification in docs/PROGRESS.md` | `tests/Architecture/ProgressIntegrityTest.php` | PASS 🟢 | 0.021s |

## 4. Ringkasan Arsitektur (`arch:scan`)
- **Status Baseline:** Terpasang (7273 pelanggaran terbaseline).

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
| `A12` | Kolom *_id di migrasi tanpa FK | 596 |
| `A13` | back()->errors() (HTTP 500 di jalur galat) | 15 |

## 5. Verifikasi
*(Bagian ini wajib diisi oleh verifikator independen sebelum mengubah status fase ke ✅ sesuai P7).*

- **Tanggal Verifikasi:** 
- **Verifikator:** 
- **Checklist Verifikator (C1–C14):**
  - [ ] C1 Kode ada di modul pemilik yang benar
  - [ ] C2 Tidak ada tabel/kolom liar tanpa prefiks registry
  - [ ] C3 Double-entry integer minor unit; saldo normal seimbang
  - [ ] C4 Idempotensi terbukti pada aksi mutasi & posting
  - [ ] C5 Otorisasi per rute (role:/can:) terverifikasi matriks
  - [ ] C6 Audit command memiliki fixture korupsi yang gagal
  - [ ] C7 Tidak ada jalan pintas terlarang X1–X25
- **Catatan Temuan / Rekomendasi:**

