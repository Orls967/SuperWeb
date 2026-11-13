# Checklist Audit, Tata Kelola, & Kepatuhan Modul WMS (Fase 41)

Dokumen ini menjadi daftar periksa (*compliance checklist*) sebelum setiap rilis perubahan pada modul `Wms`.

---

## 1. Kepatuhan Arsitektur (`php artisan arch:scan`)
- [x] **A1**: Nol import `Modules\{Lain}\Domain\*` di luar batas kontrak.
- [x] **A2**: Nol query ke tabel berprefiks modul lain tanpa melalui kontrak/adapter resmi (`inv_stock_movements` hanya di `InventoryBalanceProviderAdapter`, `lgx_shipments` hanya di `InTransitShipmentProviderAdapter`).
- [x] **A3**: Nol tipe `float` atau kolom `decimal` baru untuk uang dan kuantitas (seluruhnya menggunakan `int` base unit / IDR minor units via `QuantityNormalizer`).
- [x] **A4**: Nol `Str::uuid()` atau nilai acak pada `idempotency_key` (`IDEM:WMS:{AKSI}:{ID}`).
- [x] **A5**: Seluruh `WmsTransactionTypes` berpanjang `<= 32` karakter dan terdaftar pada `TransactionTypeRegistry`.
- [x] **A6**: Nol parameter `bool` kontrol persetujuan di `Application/` (menggunakan `?int $supervisorUserId`).
- [x] **A8**: Nol pemanggilan `Carbon::setTestNow` di luar `tests/`.
- [x] **A10**: Nol model dengan `$guarded = []`.
- [x] **A11**: Seluruh berkas PHP memiliki `declare(strict_types=1);`.
- [x] **A12**: Seluruh kolom `*_id` pada migrasi baru dilengkapi *foreign key constraint*.
- [x] **A13**: Nol penggunaan `back()->errors()`.

---

## 2. Kepatuhan Antarmuka (`php artisan ui:lint`)
- Seluruh 12 halaman Blade di `modules/Wms/resources/views/wms/` menggunakan `<x-app-layout>` dan komponen `<x-ds.*>`.
- Nol CSS inline atau kelas tidak terdaftar pada *design system*.
- Halaman `/wms/tasks` terverifikasi responsif pada lebar layar `375px` untuk perangkat pemindai handheld.

---

## 3. Kepatuhan Akuntansi & Buku Besar (D41-9)
- Seluruh 12 akun `WmsAccounts` terdaftar di `LedgerAccountsSeeder`.
- Seluruh 12 kejadian uang (`D41-1` s.d. `D41-12`) memposting entri seimbang (`Σ = 0`) ke `LedgerService`.
- Rekonsiliasi `WarehouseValuationReader` menghasilkan `discrepancy_idr === 0` untuk seluruh gudang aktif.
- `php artisan wms:audit` lulus (`exit 0`) pada data bersih dan gagal (`exit 1`) pada skenario sabotase `WmsAuditCorruptionFixture`.

---

## 4. Kepatuhan Pengujian & Dokumentasi
- Acceptance Tests (5 skenario T-10) lulus 100%.
- Unit Tests algoritma (`H1`, `H2`, `H3`, `QuantityNormalizer`, `AbcVelocityClassifier`, `FefoLotAllocator`, `ReplenishmentMinMaxCalculator`, `WaveBatchOptimizer`) lulus 100%.
- Feature Tests 12 kejadian ledger & sabotase audit lulus 100%.
- Dokumentasi DoR, Desain, Konsep, Algoritma, Panduan, Runbook, Galat, dan ADR lengkap dan mutakhir.
