# Kamus Data Teknis & Panduan Arsitektur: Airline Network, Codeshare & IRROPS

## 1. Kamus Data Database Komprehensif (Tabel Tambahan Modul Aviation)

Berikut adalah definisi rinci skema basis data yang ditambahkan pada Fase 178 untuk melengkapi kapabilitas jaringan dan penanganan disrupsi:

### 1.1. `avi_schedule_versions` (Tata Kelola Versi Jadwal Penerbangan)
- `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `airline_id`: `BIGINT UNSIGNED NOT NULL`, Foreign Key ke `avi_operators(id)`
- `season_code`: `VARCHAR(10) NOT NULL` (e.g. 'S26', 'W26')
- `version_number`: `INT NOT NULL` (nomor revisi berurutan)
- `status`: `VARCHAR(30) NOT NULL` (draft, review, published, active, superseded, suspended, withdrawn)
- `effective_from`: `DATE NOT NULL`
- `effective_until`: `DATE NOT NULL`
- `published_at`: `DATETIME NULL`
- `approved_by`: `BIGINT UNSIGNED NULL`
- `notes`: `TEXT NULL`
- `created_at`, `updated_at`: `TIMESTAMP`
- Indeks: `UNIQUE(airline_id, season_code, version_number)`

### 1.2. `avi_codeshare_agreements` (Perjanjian Kerjasama Antar-Maskapai)
- `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `operating_airline_id`: `BIGINT UNSIGNED NOT NULL`, Foreign Key ke `avi_operators(id)`
- `marketing_airline_id`: `BIGINT UNSIGNED NOT NULL`, Foreign Key ke `avi_operators(id)`
- `agreement_code`: `VARCHAR(50) NOT NULL UNIQUE` (e.g. 'CS-GA-SQ-2026')
- `agreement_type`: `VARCHAR(30) NOT NULL` (free_sale, block_space_hard, block_space_soft)
- `revenue_split_bps`: `INT NOT NULL` (basis poin pembagian pendapatan, e.g. 8500 = 85%)
- `proration_rule`: `VARCHAR(30) NOT NULL` (straight_rate, mileage_tpm, multilateral_pfm)
- `status`: `VARCHAR(30) NOT NULL` (proposed, negotiating, signed, active, amendment_pending, suspended, expired)
- `valid_from`: `DATE NOT NULL`
- `valid_until`: `DATE NOT NULL`
- `created_at`, `updated_at`: `TIMESTAMP`

### 1.3. `avi_codeshare_flights` (Pemetaan Nomor Penerbangan Mitra)
- `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `codeshare_agreement_id`: `BIGINT UNSIGNED NOT NULL`, Foreign Key ke `avi_codeshare_agreements(id)`
- `operating_flight_id`: `BIGINT UNSIGNED NOT NULL`, Foreign Key ke `avi_flights(id)`
- `marketing_flight_number`: `VARCHAR(20) NOT NULL` (e.g. 'SQ5042')
- `allotted_seats`: `INT NOT NULL DEFAULT 0` (kuota kursi block-space jika ada)
- `status`: `VARCHAR(20) NOT NULL DEFAULT 'active'`
- `created_at`, `updated_at`: `TIMESTAMP`
- Indeks: `UNIQUE(codeshare_agreement_id, operating_flight_id, marketing_flight_number)`

### 1.4. `avi_disruption_cases` (Kasus Insiden Disrupsi)
- `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `flight_id`: `BIGINT UNSIGNED NOT NULL`, Foreign Key ke `avi_flights(id)`
- `case_number`: `VARCHAR(50) NOT NULL UNIQUE` (e.g. 'IRR-20261108-GA402')
- `incident_category`: `VARCHAR(50) NOT NULL` (AOG_TECHNICAL, CREW_FTL, FORCE_MAJEURE_WEATHER, FORCE_MAJEURE_VOLCANIC_ERUPTION, ATC_RESTRICTION, AIRPORT_INFRASTRUCTURE)
- `delay_minutes`: `INT NOT NULL DEFAULT 0`
- `status`: `VARCHAR(30) NOT NULL` (detected, classified, care_dispatched, reaccommodating, rebooked, compensation_settled, closed, disputed)
- `regulatory_category`: `VARCHAR(20) NOT NULL` (CAT1, CAT2, CAT3, CAT4, CAT5, CAT6_CANCELLED)
- `duty_of_care_required`: `BOOLEAN NOT NULL DEFAULT TRUE`
- `cash_compensation_required`: `BOOLEAN NOT NULL DEFAULT FALSE`
- `hotel_required`: `BOOLEAN NOT NULL DEFAULT FALSE`
- `incident_description`: `TEXT NOT NULL`
- `detected_at`: `DATETIME NOT NULL`
- `resolved_at`: `DATETIME NULL`
- `created_at`, `updated_at`: `TIMESTAMP`

### 1.5. `avi_disruption_passengers` (Manifest Penumpang Terdampak)
- `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `disruption_case_id`: `BIGINT UNSIGNED NOT NULL`, Foreign Key ke `avi_disruption_cases(id)`
- `ticket_id`: `BIGINT UNSIGNED NOT NULL`, Foreign Key ke `avi_tickets(id)`
- `passenger_name`: `VARCHAR(150) NOT NULL`
- `priority_score`: `INT NOT NULL DEFAULT 0`
- `loyalty_tier`: `VARCHAR(20) NOT NULL DEFAULT 'classic'`
- `special_service_request`: `VARCHAR(30) NULL` (UMNR, WCHR, INFANT)
- `reaccommodation_status`: `VARCHAR(30) NOT NULL DEFAULT 'pending'` (pending, assigned, confirmed, refunded, self_rerouted)
- `new_flight_id`: `BIGINT UNSIGNED NULL`, Foreign Key ke `avi_flights(id)`
- `new_seat_number`: `VARCHAR(10) NULL`
- `hotel_reservation_ref`: `VARCHAR(100) NULL`
- `created_at`, `updated_at`: `TIMESTAMP`

### 1.6. `avi_care_vouchers` (E-Voucher Hak Penumpang PM 89)
- `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `disruption_passenger_id`: `BIGINT UNSIGNED NOT NULL`, Foreign Key ke `avi_disruption_passengers(id)`
- `voucher_code`: `VARCHAR(64) NOT NULL UNIQUE`
- `voucher_type`: `VARCHAR(30) NOT NULL` (refreshment, snack, meal, cash_compensation, hotel_stay)
- `nominal_amount_minor_unit`: `BIGINT NOT NULL DEFAULT 0`
- `currency`: `VARCHAR(3) NOT NULL DEFAULT 'IDR'`
- `is_redeemed`: `BOOLEAN NOT NULL DEFAULT FALSE`
- `redeemed_at`: `DATETIME NULL`
- `merchant_id`: `VARCHAR(100) NULL`
- `expires_at`: `DATETIME NOT NULL`
- `created_at`, `updated_at`: `TIMESTAMP`

### 1.7. `avi_loyalty_accounts` (Buku Besar Rekening Sering Terbang)
- `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `airline_id`: `BIGINT UNSIGNED NOT NULL`, Foreign Key ke `avi_operators(id)`
- `passenger_id`: `BIGINT UNSIGNED NOT NULL`
- `membership_number`: `VARCHAR(30) NOT NULL UNIQUE`
- `current_tier`: `VARCHAR(20) NOT NULL DEFAULT 'classic'`
- `tier_miles`: `INT NOT NULL DEFAULT 0`
- `redeemable_miles`: `INT NOT NULL DEFAULT 0`
- `status`: `VARCHAR(20) NOT NULL DEFAULT 'active'`
- `tier_expiry_date`: `DATE NOT NULL`
- `created_at`, `updated_at`: `TIMESTAMP`

### 1.8. `avi_loyalty_transactions` (Subledger Transaksi Poin Miles)
- `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `loyalty_account_id`: `BIGINT UNSIGNED NOT NULL`, Foreign Key ke `avi_loyalty_accounts(id)`
- `transaction_reference`: `VARCHAR(64) NOT NULL UNIQUE`
- `transaction_type`: `VARCHAR(40) NOT NULL` (flight_accrual, partner_accrual, tier_bonus, flight_redemption, upgrade, expiry, adjustment)
- `miles_delta`: `INT NOT NULL` (positif untuk akrual, negatif untuk penukaran)
- `tier_miles_delta`: `INT NOT NULL DEFAULT 0`
- `running_balance`: `INT NOT NULL`
- `flight_ticket_id`: `BIGINT UNSIGNED NULL`
- `nonce_claim_hash`: `VARCHAR(64) NOT NULL UNIQUE` (anti double-dipping)
- `created_at`: `TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`

### 1.9. `avi_yield_buckets` (Alokasi Inventaris Tarif Dinamis)
- `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `flight_id`: `BIGINT UNSIGNED NOT NULL`, Foreign Key ke `avi_flights(id)`
- `bucket_code`: `VARCHAR(5) NOT NULL` (Y, B, M, H, Q, V, dll.)
- `cabin_class`: `VARCHAR(20) NOT NULL` (first, business, premium_economy, economy)
- `base_price_minor_unit`: `BIGINT NOT NULL`
- `allocated_capacity`: `INT NOT NULL`
- `sold_count`: `INT NOT NULL DEFAULT 0`
- `protection_level`: `INT NOT NULL DEFAULT 0`
- `booking_limit`: `INT NOT NULL DEFAULT 0`
- `is_closed`: `BOOLEAN NOT NULL DEFAULT FALSE`
- `algorithm_certificate_hash`: `VARCHAR(64) NOT NULL`
- `created_at`, `updated_at`: `TIMESTAMP`
- Indeks: `UNIQUE(flight_id, bucket_code)`

### 1.10. `avi_parametric_claims` (Pencatatan Otomatis Klaim Asuransi)
- `id`: `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `disruption_case_id`: `BIGINT UNSIGNED NOT NULL`, Foreign Key ke `avi_disruption_cases(id)`
- `policy_number`: `VARCHAR(100) NOT NULL`
- `passenger_id`: `BIGINT UNSIGNED NOT NULL`
- `trigger_type`: `VARCHAR(50) NOT NULL`
- `payout_amount_minor_unit`: `BIGINT NOT NULL`
- `claim_status`: `VARCHAR(30) NOT NULL` (pending, approved, disbursed, rejected)
- `external_reference`: `VARCHAR(100) NULL`
- `created_at`, `updated_at`: `TIMESTAMP`

