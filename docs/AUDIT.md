# Codebase Audit — AutoServe Superwebsite
**Date:** 2026-09-29

## Database (SQLite)

### Tables & Schema

| Table | Columns (key) | Notes |
|-------|--------------|-------|
| `users` | id, name, email, phone, role (enum: admin/mekanik/customer), email_verified_at, password, timestamps | Role stored as DB enum. No UUID. |
| `password_reset_tokens` | email (PK), token, created_at | Standard Breeze |
| `sessions` | id (PK), user_id, ip_address, user_agent, payload, last_activity | Standard |
| `cache` | key (PK), value, expiration | Standard |
| `cache_locks` | key (PK), owner, expiration | Standard |
| `jobs` | id, queue, payload, attempts, reserved_at, available_at, created_at | Standard |
| `job_batches` | id, name, total_jobs, etc. | Standard |
| `failed_jobs` | id, uuid, connection, queue, payload, etc. | Standard |
| `services` | id, name, description, price (decimal 12,2), is_active (bool), timestamps | AutoServe master data |
| `spareparts` | id, name, code (unique), stock (int), price (decimal 12,2), unit, is_active, timestamps | AutoServe master data |
| `bookings` | id, booking_code (unique), customer_id FK→users, mechanic_id FK→users (nullable), service_id FK→services, plate_number, vehicle_brand, vehicle_model, vehicle_year, complaint, mechanic_notes, booking_date, booking_time, status (enum: pending/confirmed/in_progress/completed/invoiced), service_cost, sparepart_cost, grand_total, timestamps | Core AutoServe table |
| `booking_sparepart` | id, booking_id FK, sparepart_id FK, quantity, unit_price, subtotal, timestamps | Pivot |
| `brands` | id, name, slug (unique), country, category (enum: jdm/usdm/euro/korean/chinese/ev/other), logo_url, description, is_active, timestamps | AutoDex |
| `cars` | id, brand_id FK, model, slug (unique), year_start, year_end, body_type, fuel_type (enum), engine, horsepower, torque_nm, transmission, drivetrain, top_speed_kmh, zero_to_100, range_km, battery_kwh, price_idr (decimal 15,2), image_url, description, is_active, timestamps | AutoDex |
| `garages` | id, user_id FK, car_id FK, plate_number, color, year_bought, nickname, notes, timestamps | Pivot: user owns a car catalog entry |
| `wishlists` | id, user_id FK, car_id FK, priority, notes, timestamps | Pivot: user wishlists a car |

### Relationships
- User → hasMany Booking (as customer_id)
- User → hasMany Booking (as mechanic_id)
- User → belongsToMany Car via `garages` (garageCars)
- User → belongsToMany Car via `wishlists` (wishlistCars)
- Service → hasMany Booking
- Sparepart → belongsToMany Booking via `booking_sparepart`
- Brand → hasMany Car
- Car → belongsTo Brand
- Car → belongsToMany User via garages/wishlists

## Routes (39 total)

| Method | URI | Name | Controller | Auth/Role |
|--------|-----|------|------------|-----------|
| GET | / | — | Closure (redirect or welcome) | Public |
| GET | /dashboard | dashboard | DashboardController@index | auth, verified |
| GET | /profile | profile.edit | ProfileController@edit | auth, verified |
| PATCH | /profile | profile.update | ProfileController@update | auth, verified |
| DELETE | /profile | profile.destroy | ProfileController@destroy | auth, verified |
| GET | /bookings/create | bookings.create | BookingController@create | auth, verified |
| POST | /bookings | bookings.store | BookingController@store | auth, verified |
| GET | /bookings/{booking} | bookings.show | BookingController@show | auth, verified |
| GET | /bookings/{booking}/invoice | bookings.invoice | BookingController@invoice | auth, verified |
| PATCH | /bookings/{booking}/status | bookings.updateStatus | BookingController@updateStatus | role:admin,mekanik |
| PATCH | /bookings/{booking}/assign | bookings.assign | BookingController@assignMechanic | role:admin,mekanik |
| POST | /bookings/{booking}/spareparts | bookings.addSparepart | BookingController@addSparepart | role:admin,mekanik |
| DELETE | /bookings/{booking}/spareparts/{sparepart} | bookings.removeSparepart | BookingController@removeSparepart | role:admin,mekanik |
| GET | /services | services.index | ServiceController@index | role:admin |
| POST | /services | services.store | ServiceController@store | role:admin |
| PUT | /services/{service} | services.update | ServiceController@update | role:admin |
| DELETE | /services/{service} | services.destroy | ServiceController@destroy | role:admin |
| GET | /spareparts | spareparts.index | SparepartController@index | role:admin |
| POST | /spareparts | spareparts.store | SparepartController@store | role:admin |
| PUT | /spareparts/{sparepart} | spareparts.update | SparepartController@update | role:admin |
| DELETE | /spareparts/{sparepart} | spareparts.destroy | SparepartController@destroy | role:admin |
| GET | /autodex | autodex.index | CarCatalogController@index | auth, verified |
| GET | /autodex/car/{car:slug} | autodex.show | CarCatalogController@show | auth, verified |
| GET | /autodex/garage | autodex.garage.index | GarageController@index | auth, verified |
| POST | /autodex/garage/{car} | autodex.garage.toggle | GarageController@toggleGarage | auth, verified |
| POST | /autodex/wishlist/{car} | autodex.wishlist.toggle | GarageController@toggleWishlist | auth, verified |
| + Standard Breeze auth routes (login, register, password reset, etc.) | | | | |

## Role Implementation
- Stored as DB `enum('admin', 'mekanik', 'customer')` on `users.role`, default `customer`.
- `CheckRole` middleware aliased as `role` in `bootstrap/app.php`.
- User model has helpers: `isAdmin()`, `isMekanik()`, `isCustomer()`, `isStaff()`.
- Registration always sets `role = 'customer'`.

## Auth
- Laravel Breeze (Blade stack).
- Layout: `resources/views/layouts/app.blade.php` (custom dark sidebar).
- Guest layout: `resources/views/layouts/guest.blade.php`.

## Frontend
- Tailwind CSS (compiled via Vite + also CDN on welcome page).
- Alpine.js (via CDN in app layout).
- Dark glassmorphism theme with sidebar navigation.
- Views: dashboard, bookings (create/show/invoice), services/index, spareparts/index, autodex (index/show/garage), welcome.

## Testing
- PHPUnit (not Pest yet). Using SQLite in-memory for tests.
- Existing tests: Breeze auth tests, ProfileTest, ExampleTest.
- No characterization tests for AutoServe or AutoDex.

## Key Observations
1. No modular structure — all code in `app/`.
2. No UUIDs on any model.
3. Status is string-based, not Enum.
4. No FormRequest classes for booking operations.
5. Booking stores vehicle info as flat strings (plate_number, vehicle_brand, etc.) — no FK to a vehicles table.
6. Stock deduction uses raw DB::table decrement — no inventory service.
7. No domain events, no action classes.
8. Garage pivot stores vehicle ownership data (plate_number, color) — needs migration to core_vehicles.
9. Money stored as decimal(12,2) — needs migration to integer (rupiah) per spec.

---

## Baseline sebelum Fase 7 — 2026-09-30

- **Waktu Audit:** 2026-09-30 08:07 WIB
- **Total Test:** 183 tests (semua PASS)
- **Total Assertion:** 737 assertions
- **Status Build Frontend (Vite):** Sukses (built in ~595ms)
- **Status Standar Kode (Pint):** Passed (`{"tool":"pint","result":"passed"}`)
- **Total Akun Ledger:** 28 akun (`bank:reconcile` bersih, 0 selisih, total global per aset = 0)
- **Integritas Paspor Kendaraan:** `core:verify-passports` bersih, seluruh rantai valid
- **Modul Terdaftar di `bootstrap/providers.php`:**
  1. `App\Providers\AppServiceProvider`
  2. `Modules\Shared\SharedServiceProvider`
  3. `Modules\Core\CoreServiceProvider`
  4. `Modules\AutoServe\AutoServeServiceProvider`
  5. `Modules\AutoDex\AutoDexServiceProvider`
  6. `Modules\Banking\BankingServiceProvider`
  7. `Modules\Payment\PaymentServiceProvider`
  8. `Modules\Inventory\InventoryServiceProvider`
  9. `Modules\Store\StoreServiceProvider`
  10. `Modules\Crypto\CryptoServiceProvider`
  11. `Modules\Finance\FinanceServiceProvider`
  12. `Modules\Resto\RestoServiceProvider`
  13. `Modules\Mall\MallServiceProvider`

---

## Baseline sebelum Fase 19–25 — 2026-09-30

- **Waktu Audit:** 2026-09-30 20:46 WITA (12:46 UTC)
- **Git Commit:** `7e133c8` (`docs: complete phase 18 documentation, runbook, architecture, and final project sign-off`)
- **Total Test:** **297 tests** (100% PASS, 0 failure, 0 skipped)
- **Total Assertion:** **1317 assertions**
- **Durasi Eksekusi Test Suite:** 21.18s
- **Status Build Frontend (Vite):** Sukses (`built in 557ms`)
- **Status Standar Kode (Pint):** Passed (`{"tool":"pint","result":"passed"}`)
- **Hasil Quality Gates:**
  - `php artisan bank:reconcile`: 64 akun ledger seimbang, 0 selisih, total per aset = 0
  - `php artisan core:verify-passports`: 2 kendaraan valid, hash-chain utuh
  - `php artisan resto:close-day --check`: lolos penutupan 3 outlet
  - `php artisan mall:audit-billing`: 3 invoice Rp 178.400.000, 0 selisih ledger
  - `php artisan super:health-check`: 7/7 sub-sistem HEALTHY (durasi 28.75 ms, Audit Log ID #2)
- **Modul Aktif:**
  `Shared`, `Core`, `AutoServe`, `AutoDex`, `Banking`, `Payment`, `Inventory`, `Store`, `Crypto`, `Finance`, `Resto`, `Mall` (12 modul).

---

## Quality Gate Fase 19 — 2026-09-30

- **Waktu Audit:** 2026-09-30 21:10 WITA (13:10 UTC)
- **Cakupan Fase:** Fase 19 — Penutupan Utang Fase 18 (Tasks 19.1–19.9)
- **Total Test:** **305 tests** (100% PASS, 0 failure, 0 skipped)
- **Total Assertion:** **1569 assertions**
- **Durasi Eksekusi Test Suite:** 27.84s
- **Status Build Frontend (Vite):** Sukses (`built in 554ms`)
- **Status Standar Kode (Pint):** Passed (`{"tool":"pint","result":"passed"}`)
- **Hasil Quality Gates:**
  - `php artisan migrate:fresh --seed`: Sukses (DatabaseSeeder: 20 customer ber-PIN & bersaldo, 25 kendaraan berpaspor, 59 booking bengkel, 3 pinjaman HODL-to-Drive aktif, 5 order resto terbayar & close day, partial invoice mall Rp 20m)
  - `php artisan bank:reconcile`: 89 akun ledger seimbang, 0 selisih, total global per aset = 0
  - `php artisan core:verify-passports`: 30 kendaraan valid, rantai SHA-256 utuh tanpa kompromi
  - `php artisan resto:close-day --check`: 5 transaksi POS terbayar, omzet kotor/bersih Rp 170.000 cocok sempurna dengan ledger
  - `php artisan mall:audit-billing`: 3 invoice mall (total Rp 178.400.000), Rp 20.000.000 penerimaan tercatat di ledger, 0 selisih
  - `php artisan super:health-check`: 7/7 pilar sistem status HEALTHY (durasi 46.28 ms, Audit Log ID #1)
- **Modul Aktif:**
  `Shared`, `Core`, `AutoServe`, `AutoDex`, `Banking`, `Payment`, `Inventory`, `Store`, `Crypto`, `Finance`, `Resto`, `Mall` (12 modul).

## Quality Gate Fase 20 — 2026-09-30

- **Waktu Audit:** 2026-09-30 21:32 WITA (13:32 UTC)
- **Cakupan Fase:** Fase 20 — Fondasi Logistik: Modul, Jaringan, Armada, Kru & Seeder (Tasks 20.1–20.6)
- **Total Test:** **333 tests** (100% PASS, 0 failure, 0 skipped)
- **Total Assertion:** **1969 assertions**
- **Durasi Eksekusi Test Suite:** 30.12s
- **Status Build Frontend (Vite):** Sukses (`built in 558ms`)
- **Status Standar Kode (Pint):** Passed
- **Hasil Quality Gates:**
  - `php artisan migrate:fresh --seed`: Sukses (~9.5s) (DatabaseSeeder + LogisticsSeeder: 30 truk terhubung Vehicle Passport DA plate, 8 trailer, 4 kapal ber-IMO, 2 freighter, 300 kontainer ISO 6346, 12 driver dengan limit jam kerja UU 22/2009, 3 dispatcher, 4 hub operator, 5 shipper, 1 admin logistik)
  - `php artisan bank:reconcile`: 114 akun ledger seimbang, 0 selisih, total global per aset = 0
  - `php artisan core:verify-passports`: 60 kendaraan valid (30 customer/platform + 30 logistik), rantai SHA-256 utuh tanpa kompromi
  - `php artisan resto:close-day --check`: 5 transaksi POS terbayar, omzet kotor/bersih Rp 170.000 cocok sempurna dengan ledger
  - `php artisan mall:audit-billing`: 3 invoice mall (total Rp 178.400.000), Rp 20.000.000 penerimaan tercatat di ledger, 0 selisih
  - `php artisan super:health-check`: 7/7 pilar sistem status HEALTHY (durasi 64.06 ms)
- **Modul Aktif:**
  `Shared`, `Core`, `AutoServe`, `AutoDex`, `Banking`, `Payment`, `Inventory`, `Store`, `Crypto`, `Finance`, `Resto`, `Mall`, `Logistics` (13 modul).

## Quality Gate Fase 21 — 2026-09-30

- **Waktu Audit:** 2026-09-30 21:58 WITA (13:58 UTC)
- **Cakupan Fase:** Fase 21 — Shipment, Tarif, Booking & Portal Shipper (Tasks 21.1–21.10)
- **Total Test:** **377 tests** (100% PASS, 0 failure, 0 skipped)
- **Total Assertion:** **2256 assertions**
- **Durasi Eksekusi Test Suite:** 32.77s
- **Status Build Frontend (Vite):** Sukses (`built in 590ms`)
- **Status Standar Kode (Pint):** Passed
- **Hasil Quality Gates:**
  - `php artisan migrate:fresh --seed`: Sukses (DatabaseSeeder + LogisticsSeeder: 30 truk terhubung Vehicle Passport DA plate, 8 trailer, 4 kapal ber-IMO, 2 freighter, 300 kontainer ISO 6346, 12 driver dengan limit jam kerja UU 22/2009, 3 dispatcher, 4 hub operator, 5 shipper, 1 admin logistik)
  - `php artisan bank:reconcile`: 114 akun ledger seimbang, 0 selisih, total global per aset = 0
  - `php artisan core:verify-passports`: 60 kendaraan valid (30 customer/platform + 30 logistik), rantai SHA-256 utuh tanpa kompromi
  - `php artisan resto:close-day --check`: 5 transaksi POS terbayar, omzet kotor/bersih Rp 170.000 cocok sempurna dengan ledger
  - `php artisan mall:audit-billing`: 3 invoice mall (total Rp 178.400.000), Rp 20.000.000 penerimaan tercatat di ledger, 0 selisih
  - `php artisan super:health-check`: 7/7 pilar sistem status HEALTHY (durasi 60.67 ms)
- **Pencapaian Fitur Fase 21:**
  - `lgx_shipments` dengan nomor resi `SRX` + 10 digit + check digit Luhn & state machine transisi
  - `lgx_packages` dengan berat aktual, dimensi, HS code, DG UN number, rentang suhu reefer
  - Perhitungan berat tertagih (chargeable weight) murni `BigDecimal` per moda transportasi
  - Sistem tarif fleksibel `lgx_rate_cards`, `lgx_rate_brackets`, `lgx_surcharges` dengan validasi anti overlap
  - Mesin penawaran harga `QuoteShipmentAction` dengan timelock 15 menit dan verifikasi cryptographic payload hash
  - Alur booking prabayar via `PaymentGateway::charge` dengan verifikasi PIN dompet & alokasi `lgx:unearned_freight`
  - Akun postpaid B2B dengan limit plafon kredit, penerbitan invoice bulanan idempotent (`lgx:invoice-shippers`), dan bayar invoice via dompet
  - Pembatalan pengiriman pre-pickup dengan potongan biaya pembatalan terkonfigurasi & proteksi pembatalan post-pickup
  - Portal shipper mobile-responsive: form pengiriman multi-paket, bulk upload CSV s/d 5.000 baris dengan queued job, pelaporan baris error, dan cetak label thermal/HTML dengan QR Code SVG
  - Pelacakan kargo publik `/track/{tracking_number}` tanpa login, pembatasan laju 30 req/menit per IP, penyamaran PII data pribadi penerima (`B*** S***`, `0812****7890`), dan visual timeline status kargo
  - Bebas artefak debug (`dd`, `dump`, `TODO`, `FIXME`)
- **Modul Aktif:**
  `Shared`, `Core`, `AutoServe`, `AutoDex`, `Banking`, `Payment`, `Inventory`, `Store`, `Crypto`, `Finance`, `Resto`, `Mall`, `Logistics` (13 modul).

## Quality Gate Fase 22 — 2026-10-02

- **Cakupan Fase:** Fase 22 — Operasi Jaringan Multimoda: Kapasitas, Rute, Konsolidasi, Hub, Last-Mile (Tasks 22.1–22.10)
- **Total Test:** **457 tests** (100% PASS, 0 failure, 0 skipped), naik dari baseline 411 pada awal sesi
- **Total Assertion:** **2637 assertions**
- **Status Build Frontend (Vite):** Sukses
- **Status Standar Kode (Pint):** Passed (`vendor/bin/pint --test`)
- **Arch Tests (batas modul):** 9 passed
- **Hasil Quality Gates (`migrate:fresh --seed`):**
  - `php artisan bank:reconcile`: 114 akun ledger seimbang, 0 selisih
  - `php artisan core:verify-passports`: seluruh paspor kendaraan valid
  - `php artisan lgx:verify-custody`: lulus. Pada seed default 0 pengiriman, sehingga gate bermakna dibuktikan oleh `Phase22IntegrationTest` (1 pengiriman, 8 event rantai kustodi valid)
  - `php artisan lgx:capacity-check`: lulus. Seed default tidak memiliki jadwal, dan `Phase22IntegrationTest` mengaudit jadwal berisi reservasi aktif
  - `php artisan lgx:detect-late`: berjalan idempoten (run kedua menghasilkan 0 exception baru)
  - `php artisan mall:audit-billing` dan `php artisan super:health-check`: SEIMBANG / 7 pilar HEALTHY
- **Pencapaian Fitur Fase 22.7–22.10:**
  - Papan dispatch: validasi SIM (masa berlaku pada tanggal trip + kelas), batas jam UU 22/2009 Pasal 90 (termasuk beban trip lain di hari yang sama), status maintenance, tabrakan jadwal, dan integritas Vehicle Passport sebelum penugasan truk + driver; reassign/release dengan riwayat `lgx_dispatch_assignments`; penugasan resi pickup/last-mile
  - Aplikasi driver mobile: daftar stop, scan pickup, OTP 6 digit (hash, rate limit 5/jam), Proof of Delivery (nama penerima, foto, tanda tangan canvas PNG tervalidasi), 3x gagal otomatis ReturnToSender
  - Exception terstruktur (8 tipe, 4 tingkat keparahan) dengan dedupe key, penyelesaian dengan status lanjutan, penangkapan otomatis missort dan pengantaran gagal
  - Monitoring SLA per service level (melewati SLA / berisiko), command `lgx:detect-late` terjadwal tiap 15 menit
- **Catatan Lingkungan:** `composer.lock` mensyaratkan PHP >= 8.4 (Symfony 8.1) sedangkan sandbox hanya PHP 8.3. Dependensi diselesaikan sementara dengan `platform.php=8.3.6` untuk menjalankan test, lalu `composer.json`/`composer.lock` dikembalikan tanpa perubahan.

