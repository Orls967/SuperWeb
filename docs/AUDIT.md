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

## Quality Gate Fase 23 — 2026-10-02

- **Cakupan Fase:** Fase 23 — Uang Logistik: Pendapatan, COD, Carrier, Klaim, D&D, Bea Cukai (Tasks 23.1–23.9)
- **Total Test:** **521 tests** (100% PASS, 0 failure, 0 skipped), naik dari 457 pada gate Fase 22
- **Total Assertion:** **3070 assertions**
- **Durasi Eksekusi Test Suite:** 91.85s
- **Status Build Frontend (Vite):** Sukses
- **Status Standar Kode (Pint):** Passed (`vendor/bin/pint --test`)
- **Arch Tests (batas modul):** 9 passed
- **Hasil Quality Gates (`migrate:fresh --seed`):**
  - `php artisan bank:reconcile`: 128 akun ledger seimbang, 0 selisih
  - `php artisan lgx:audit-billing`: 15 pemeriksaan, 29 dokumen non-nol, 0 selisih (unearned, pendapatan Delivered, invoice freight/D&D, piutang shipper, bea cukai, COD, carrier, klaim, BBM)
  - `php artisan lgx:verify-custody`: 6 pengiriman (9 event) valid
  - `php artisan lgx:capacity-check`: 1 jadwal valid
  - `php artisan core:verify-passports`, `mall:audit-billing`, `super:health-check`: lulus; `lgx:detect-late`, `lgx:settle-cod`, `lgx:accrue-dd`, `lgx:pay-carriers` berjalan idempoten
- **Pencapaian Fitur Fase 23:**
  - 23.1 Pengakuan pendapatan saat Delivered (prabayar via unearned_freight, pascabayar via piutang), idempoten
  - 23.2 COD: collect oleh driver, setoran hub persis, `lgx:settle-cod` D+N dengan fee ke `cod_fee_revenue`, dashboard COD
  - 23.3 Carrier subkontrak: akrual biaya leg, `lgx:pay-carriers` mingguan, laporan margin per resi
  - 23.4 Klaim: workflow pembuat/pengaju/penyetuju (4 mata), batas ganti rugi asuransi/non-asuransi, anti bayar ganda berlapis
  - 23.5 Demurrage & Detention: free time, tarif bertingkat, `lgx:accrue-dd` per zona waktu lokasi, invoice D&D
  - 23.6 Bea cukai: HS tariff, PIB/PEB simulasi BM/PPN/PPh 22, jalur merah → CustomsHold, bayar dan loloskan
  - 23.7 BBM: log isi penuh integer, konsumsi km/l, anomali > 30%
  - 23.8 `lgx:audit-billing` (15 pemeriksaan) dan `LogisticsFinanceSeeder`
  - Setiap alur uang memiliki test (a)–(e) (lihat DECISIONS 2026-10-02)
- **Perubahan lintas fase yang perlu diketahui:** quote kini menyimpan nilai deklarasi/asuransi/COD; surcharge COD_FEE tidak lagi diterapkan di quote (fee dipotong saat settlement); `lgx_invoices` memiliki kolom `kind`.
- **Catatan Lingkungan:** `composer.lock` mensyaratkan PHP >= 8.4 sedangkan sandbox PHP 8.3; test dijalankan dengan resolusi dependensi sementara (`platform.php=8.3.6`), `composer.json`/`composer.lock` tidak diubah.

## Quality Gate Fase 24 — 2026-10-02

- **Cakupan Fase:** Fase 24 — Integrasi Lintas Lini (Tasks 24.1–24.7)
- **Total Test:** **532 tests** (100% PASS, 0 failure, 0 skipped), naik dari 521 pada gate Fase 23
- **Total Assertion:** **3119 assertions**
- **Durasi Eksekusi Test Suite:** 39.53s
- **Status Build Frontend (Vite):** Sukses (built in 577ms)
- **Status Standar Kode (Pint):** Passed (`vendor/bin/pint --test`)
- **Arch Tests (batas modul):** 9 passed (seluruh batas modul terjaga ketat)
- **Hasil Quality Gates:**
  - `php artisan bank:reconcile`: 128 akun ledger seimbang, 0 selisih
  - `php artisan lgx:audit-billing`: 15 pemeriksaan, 29 dokumen non-nol, 0 selisih
  - `php artisan lgx:verify-custody`: 6 pengiriman (9 event) valid
  - `php artisan lgx:capacity-check`: 1 jadwal valid
  - `php artisan super:health-check`: 8 pilar HEALTHY (termasuk pilar Logistik baru)
- **Pencapaian Fitur Fase 24:**
  - 24.1 Store -> Logistik: event `OrderPaid` memicu booking pengiriman otomatis via kontrak `ShipmentBooking`, ongkir dicatat ke `unearned_freight`, nomor resi disimpan di order; idempotensi dan refund teruji dengan pembalikan unearned freight ke kas bank secara seimbang.
  - 24.2 Pengiriman Mobil: aksi `DeliverVehicleByCarrierAction` menambahkan event `DELIVERED_BY_CARRIER` ke rantai kriptografis Vehicle Passport; integritas hash-chain terverifikasi penuh (`VerifyPassportAction`).
  - 24.3 Perawatan Armada -> AutoServe: event `FleetServiceDue` memicu booking bengkel via kontrak `FleetMaintenanceBooking`; armada bertransisi ke status `MAINTENANCE` dan ditolak oleh `AssignScheduleResourcesAction`; selesai perawatan via `CompleteFleetMaintenanceAction` mengembalikan armada ke status `AVAILABLE`.
  - 24.4 Resto -> Logistik (cold-chain): pencatatan suhu via `RecordTemperatureAction` memicu exception keparahan tinggi (`ShipmentException`) secara idempoten saat deviasi suhu; aksi `ReceiveReeferReplenishmentAction` menerima stok bahan baku ke modul Inventory secara presisi dan idempoten.
  - 24.5 Mall -> Logistik (loading dock): model `DockAppointment` mengunci slot dock mall tanpa tumpang tindih waktu (dijamin transaksi database dan unique constraint); alur check-in dan check-out satpam dijaga dengan validasi status wajib check-in sebelum check-out.
  - 24.6 Finance & Observabilitas: `ConsolidatedPlQuery` mengagregasi pendapatan dan beban pilar Logistik tanpa menaikkan budget query dashboard (tetap 1 agregasi query); `SystemHealthService` dan `super:health-check` memverifikasi pilar ke-8 (Logistik: Billing & Rantai Kustodi).
  - 24.7 Quality Gate End-to-End: alur penuh Order Store terbayar -> Pengiriman -> Terkirim -> Pengakuan pendapatan freight -> `bank:reconcile` menghasilkan 0 diskrepansi saldo.



## Quality Gate Fase 25 — 2026-10-03

- **Cakupan Fase:** Fase 25 — Skala, API, Hardening, Control Tower (Tasks 25.1–25.9)
- **Total Test:** **538 tests** (100% PASS, 0 failure, 0 skipped), naik dari 532 pada gate Fase 24
- **Total Assertion:** **3189 assertions**
- **Status Build Frontend (Vite):** Sukses
- **Status Standar Kode (Pint):** Passed (`vendor/bin/pint --test`)
- **Arch Tests (batas modul):** 9 passed
- **Hasil Quality Gates (`migrate:fresh --seed`):**
  - `php artisan bank:reconcile`: 128 akun ledger seimbang, 0 selisih
  - `php artisan lgx:audit-billing`: 15 pemeriksaan, 29 dokumen non-nol, 0 selisih
  - `php artisan lgx:verify-custody`: 6 pengiriman (9 event) valid
  - `php artisan lgx:capacity-check`: 1 jadwal valid
  - `php artisan core:verify-passports`, `mall:audit-billing`, `super:health-check` (8 pilar): lulus
- **Pencapaian Fitur Fase 25:**
  - 25.1 `LogisticsLargeSeeder`: >= 200.000 resi, >= 2.000.000 event pelacakan, >= 5.000 kontainer, >= 20 kapal, >= 300 truk, riwayat 12 bulan
  - 25.2 `QueryBudgetTest`: lookup resi <= 3 query, papan dispatch <= 10 query, control tower <= 12 query, `lgx:accrue-dd` < 30 detik
  - 25.3 API v1 (Sanctum): quotes, shipments (header `Idempotency-Key`), tracking, ability token, rate limit, `docs/API.md`
  - 25.4 Webhook outbox: `lgx_webhook_endpoints` + `lgx_webhook_deliveries`, HMAC-SHA256, exponential backoff maks 8 retry, dead-letter, replay manual
  - 25.5 Queue & scheduler: job idempoten `ShouldBeUnique`, seluruh command terdaftar di `routes/console.php`
  - 25.6 Matriks otorisasi: `SecurityTest` & `RouteSmokeTest` mencakup seluruh rute dan role logistik
  - 25.7 Control Tower (logistics_admin): KPI OTIF, chart status, armada, dwell time, COD, margin per lane
  - 25.8 Dokumentasi final: ARCHITECTURE, RUNBOOK, README, DECISIONS mutakhir
  - 25.9 Quality gate final: seluruh test & large seeder lolos, semua gate hijau

## Laporan Temuan 26.3 — Sweep Kebenaran Seluruh Action — 2026-10-03

**Cakupan:** seluruh 143 kelas Action di `modules/{AutoServe,Banking,Core,Finance,Logistics,Mall,Resto,Store}/Application/Actions`.

**Metode:** audit dua putaran — (a) pemindaian mekanis seluruh Action terhadap 4 konvensi CODEBASE §14, (b) verifikasi manual per temuan (setiap klaim dicek langsung ke file + migrasi yang bersangkutan sebelum dilaporkan). Klarifikasi penting: 2 klaim dari putaran awal terbukti **SALAH** dan dibuang — `AcquireVehicleAction` & `RecordVehicleEventAction` **sudah** bertransaksi (klaim "tanpa transaksi" tidak benar), dan "penurunan stok AutoServe di luar transaksi" juga keliru (yang benar adalah transaksi terbelah di `ReceiveBackorderAction`).

**Hasil: 110 temuan nyata → 100 diperbaiki, 10 terbukti aman / tidak jadi cacat.**

### A. Klasifikasi temuan

| Kategori | Jumlah | Contoh dampak |
|---|---:|---|
| Kunci idempotensi tidak deterministik (`Str::random`/`uuid`/`now()`) | 38 | retry menimbulkan posting ledger ganda |
| Check-then-write tanpa lock (TOCTOU) | 41 | limit kredit, stok, alokasi, status ganda |
| Multi-tabel tanpa pembungkus transaksi | 18 | status terlanjur tersimpan tanpa aset/uang |
| Event di dalam transaksi (melanggar aturan afterCommit) | 8 | listener melihat state yang belum commit |
| Non-atomic read-modify-write | 5 | counter kunci salah baca, kuantitas kolateral hilang |

### B. Temuan paling berdampak (HIGH) yang diperbaiki

1. **`PaymentGatewayService` — partial refund membalik pendapatan penuh.** `refund()` membalik *seluruh* `revenueSplits()` sementara kredit ke dompet hanya sebagian → `UnbalancedTransactionException` (dibuktikan empiris sebelum diperbaiki). Kini pembalikan pendapatan diskalakan proporsional dengan pecahan refund, `refunded_amount` dicatat (kolom baru), dan total refund kumulatif ditolak bila melebihi tangkapanan.
2. **`PaymentGatewayService` — seluruh operasi non-atomik.** `charge/hold/capture/release/refund` sebelumnya: posting ledger (ter-commit) lalu tulis intent sebagai pernyataan terpisah tanpa lock, dengan fallback key `Str::random`. Kini seluruhnya dalam satu `DB::transaction` + `lockForUpdate` pada baris intent + kunci deterministik (`tx_cap_/tx_rel_/tx_ref_` + id intent) + penanganan `UniqueConstraintViolationException` untuk first-write bersaing.
3. **`VerifyPinAction` — bypass brute-force PIN.** Counter `failed_attempts` di-read-modify-write tanpa lock: N permintaan salah bersaan sama-sama membaca `0`, sama-sama menulis `1` → counter tak pernah mencapai 5 → **brute-force PIN dompet tak terbatas** (PIN ini mengunci transfer, pembelian C2C, dan approve estimasi). Kini di bawah `lockForUpdate` dengan inkrementasi atomik.
4. **`BookPostpaidShipmentAction` — limit kredit B2B bisa dilewati.** Pemeriksaan `canAccommodate()` berjalan *sebelum* transaksi tanpa lock akun → dua booking bersaan sama-sama lolos limit yang sama. Kini: lock akun shipper + lock quote + re-check di dalam transaksi.
5. **`CancelShipmentAction` — refund ganda saat dibatalkan dua kali.** Guard status dibaca dari model basi sebelum transaksi → dua request bersaan sama-sama lolos pre-check dan sama-sama refund. Kini: lock baris shipment + re-check status di dalam transaksi.
6. **`AddCollateralAction` / `LiquidateLoanAction` — kripto pengguna bisa tersangkut.** Keduanya membaca `collateral_qty` *di luar* transaksi tanpa lock: penambahan kolateral yang commit di celah itu didebet dari dompet tetapi tidak pernah ikut dihitung saat likuidasi → kuantitas hilang selamanya dari akun kolateral. Kini keduanya lock `fin_loans` dan membaca ulang di dalam transaksi.
7. **`UpdateOrderStatusAction` — order COMPLETED tanpa kendaraan, permanen.** Pemenuhan mobil berjalan di transaksi terpisah dari penulisan status, dan `complete()` langsung return bila status sudah COMPLETED → kegagalan di tengah tak pernah bisa diperbaiki oleh retry. Kini dibungkus satu transaksi dengan logika perbaikan (repair-on-retry).
8. **`Store` C2C — dana tertahan tanpa kompensasi.** `PurchaseC2cVehicleAction` menutup escrow lalu menulis status di luar transaksi dan di luar blok kompensasi → crash menyisakan dana tertahan + unit ter-reserve dengan status `PENDING_PAYMENT` yang tak bisa dibatalkan. `CancelC2cOrderAction` kebalikannya: sudah release escrow lalu gagal, dan retry-nya justru melempar error sehingga pesanan tidak bisa dibatalkan selamanya.

### C. Perbaikan lintas modul

- **Event `afterCommit` (8 titik):** `VehicleAcquired`, `VehicleOwnershipTransferred`, `BookingCompleted`, `ShipmentDelivered` (2 titik), `PaymentCaptured/Held/Released/Refunded` — listener kini hanya berjalan bila transaksi benar-benar commit, tidak pernah membaca state belum-commit, dan tidak berjalan setelah rollback.
- **Serialisasi sumber daya bersama:** `ReserveCapacityAction` kunci baris driver/aset sebelum mendeteksi bentrok jadwal (kunci baris jadwal sendiri ternyata tidak cukup — dua jadwal berbeda dengan driver sama lolos bersamaan).
- **Kunci deterministik di 38 titik** diganti dari `Str::random`/`uuid`/`now()` menjadi turunan sumber bisnis (id intent, id resi, nomor struk, id batch urut), dengan guard replay (return awal bila key sudah tercatat).
- **Idempotensi lewat formulir:** transfer, top-up, penyesuaian manual, bayar PO, setoran kas, bayar tagihan mall, redeem voucher, collateral top-up, dan buka pinjaman kini membawa `idempotency_key` tersembunyi sehingga double-submit tidak menggandakan pencatatan.

### D. Test regresi

`tests/Feature/ActionConcurrencyRegressionTest.php` (5 test) meniru race dengan menjalankan permintaan identik dua kali berurutan (SQLite in-memory tidak memuat dua koneksi): release retry, refund replay + over-refund, pembatalan ganda, quote ganda, alokasi kapasitas ganda.
`modules/Payment/tests/Feature/PaymentGatewayTest.php` (+4): partial refund seimbang, replay refund, penolakan over-refund, retry capture/release.

### E. Angka gate 26.3

- **Test: 554 passed / 3233 assertions / 0 skipped** (naik dari 549 setelah 26.1–26.2; baseline Fase 25 = 538/3189)
- `vendor/bin/pint --test`: clean
- `php artisan bank:reconcile`: 0 selisih · `lgx:audit-billing`: 0 selisih (29 dokumen) · `mall:audit-billing`: 0 selisih
- `lgx:capacity-check`, `core:verify-passports`, `super:health-check`: lulus

### F. Tidak diperbaiki (dengan alasan)

- `ClaimReceiptPointsAction` duplikat struk — sudah dijaga unique index nomor struk; hanya key acak yang diperbaiki.
- `ShipStockTransferAction` kekurangan stok (klaim awal bertanda UNVERIFIED) — `InventoryService` terbukti sudah mengunci baris produk dan mengecek saldo cukup di bawah lock, jadi **bukan cacat**.
- `Store/ResolveC2cDisputeAction`, `Banking/SetPinAction`, `Banking/FreezeAccountAction`, `Core/RecordVehicleEventAction`, `AutoServe/CancelBookingAction` — diverifikasi aman, tidak diubah.

## Laporan Temuan 26.4 — Sweep Performa, Agregasi SQL & Query Budget — 2026-10-04

**Cakupan:** optimasi command berat, eliminasi query N+1 pada audit dan verifikasi, konversi ke agregasi SQL, serta penambahan batasan query budget.

### A. Perubahan & Optimasi Utama
1. **`BillingAuditor` (Logistics)**:
   - Eliminasi loop N query pada audit Demurrage & Detention: satu query agregat `GROUP BY invoice_id` menggantikan puluhan query `sum('accrued_amount_idr')` per invoice.
   - Eliminasi loop N query pada audit pembayaran carrier: satu query agregat `GROUP BY carrier_payment_id` menggantikan pemindaian berulang per transaksi pembayaran.
2. **`ReconcileBankLedgerCommand` (Banking)**:
   - Eliminasi N query per akun: digantikan satu query agregat `GROUP BY account_id` menggunakan `group_concat(amount, '§')` yang dijumlahkan secara presisi menggunakan `BigDecimal` di PHP (menjaga kepatuhan tanpa risiko pembulatan float di SQLite).
   - Verifikasi total global per aset digantikan satu query agregat `GROUP BY asset_code`.
3. **Chunking & Memory Safety Command Skala Besar**:
   - `VerifyPassportsCommand`: eager loading relasi `car.brand` untuk mencegah N+1 nama mobil, diproses per `chunkById(200)` agar stabil pada puluhan ribu kendaraan.
   - `ExpireDisplayTraysCommand`: diproses per `chunkById(200)` untuk etalase skala besar.
   - `CancelStaleOrdersCommand`: diproses per `chunkById(200)` dengan eager-load `items`.
4. **Penambahan Query Budget Test (`QueryBudgetTest.php`)**:
   - `test_bank_reconcile_query_budget`: memastikan eksekusi `bank:reconcile` selesai dalam $\le 10$ query SQL.
   - `test_logistics_billing_audit_query_budget`: memastikan eksekusi `lgx:audit-billing` (15 titik pemeriksaan) selesai dalam $\le 60$ query SQL.

### B. Angka Gate 26.4
- **Test Suite:** **554 passed / 3233 assertions / 0 skipped** (100% lulus)
- `vendor/bin/pint --test`: passed
- `npm run build`: sukses
- Seluruh gate audit operasional seimbang dan 0 diskrepansi: `bank:reconcile`, `core:verify-passports`, `resto:close-day`, `mall:audit-billing`, `lgx:audit-billing`, `lgx:verify-custody`, `lgx:capacity-check`, `super:health-check` (8 pilar HEALTHY).

## Quality Gate Fase 26.5 — RBAC Granular — 2026-10-04

**Cakupan:** Model RBAC (`Role`, `Permission`), tabel migrasi (`roles`, `permissions`, `role_permission`, `user_role` dengan scope entitas), service `RbacService`, trait `HasRbacRoles`, middleware `CheckRole` terintegrasi, seeder `RbacSeeder` dengan backfill legacy otomatis, Gate::before resolver, dan konsol web admin RBAC (`/admin/rbac`).

### Hasil Quality Gate:
- **Test Suite:** **577 passed / 3286 assertions / 0 skipped** (23 test baru di `RbacTest.php` mencakup 53 assertions, 0 gagal, 0 skipped).
- **Pint:** `vendor/bin/pint --test` lulus tanpa error formatting.
- **Ledger Reconcile:** `php artisan bank:reconcile` 0 selisih pada seluruh 128 akun (global sum = 0).
- **Health Check:** `php artisan super:health-check` 8 pilar HEALTHY.
## Quality Gate Fase 26.6 — Audit Trail Generik — 2026-10-04

**Cakupan:** Kolom baru `correlation_id` dan `impact_type` pada tabel `core_audit_logs`, kontrak `AuditTrailInterface` & layanan `AuditTrailService`, proteksi mutlak append-only (melempar `RuntimeException` pada usaha update atau delete), helper `audit()` pada `BaseAction`, penerapan pencatatan audit log pada aksi bernilai tinggi (`TransferAction`, `TransferVehicleOwnershipAction`, `AcquireVehicleAction`, `UpdateOrderStatusAction`, dan `RbacService`), serta konsol pencarian dan detail admin di `/admin/audit-logs`.

### Hasil Quality Gate:
- **Test Suite:** **583 passed / 3319 assertions / 0 skipped** (6 test baru di `AuditTrailTest.php` mencakup 33 assertions, 0 gagal, 0 skipped).
- **Pint:** `vendor/bin/pint --test` lulus tanpa error.
- **Ledger Reconcile:** `php artisan bank:reconcile` 0 selisih pada seluruh 128 akun (global sum = 0).
- **Health Check:** `php artisan super:health-check` 8 pilar HEALTHY.
- **Architecture Boundaries:** 9 arch test lulus tanpa pelanggaran batas modul.

## Quality Gate Fase 26.7 — Outbox & Event Bus Generik — 2026-10-04

**Cakupan:** Tabel `core_outbox`, `core_outbox_subscriptions`, dan `core_outbox_dispatches`, model `OutboxMessage`, `OutboxSubscription`, dan `OutboxDispatch`, antarmuka `OutboxBusInterface` & layanan `OutboxBusService`, helper `outbox()` pada `BaseAction`, integrasi domain event outbox pada `DispatchWebhookAction` Logistics, command pengiriman terjadwal `core:process-outbox`, penanganan dead-letter setelah 5 kegagalan, dan mekanisme replay.

### Hasil Quality Gate:
- **Test Suite:** **587 passed / 3342 assertions / 0 skipped** (4 test baru di `OutboxBusTest.php` mencakup 23 assertions, 0 gagal, 0 skipped).
- **Pint:** `vendor/bin/pint --test` lulus tanpa error.
- **Ledger Reconcile:** `php artisan bank:reconcile` 0 selisih pada seluruh 128 akun (global sum = 0).
- **Health Check:** `php artisan super:health-check` 8 pilar HEALTHY.
- **Architecture Boundaries:** 9 arch test lulus tanpa pelanggaran batas modul.
- **Security Tests:** 16 security test (termasuk verifikasi tanda tangan HMAC-SHA256) lulus.





