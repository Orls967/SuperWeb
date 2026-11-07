# Pedoman Arsitektur Data Keanggotaan Global & Skema Database — Fase 112

## 1. Ikhtisar Arsitektur Data
Arsitektur data program Global Travel Pass dirancang untuk menangani jutaan transaksi poin per hari dengan isolasi partisi tinggi, kepatuhan batas modul, dan pencegahan utang arsitektur A12 (seluruh relasi foreign key terdefinisi secara eksplisit atau menggunakan nama kolom semantik yang diizinkan).

```
+-------------------------------------------------------------+
|                     htl_travel_passes                       |
+-------------------------------------------------------------+
| id: uuid (PK)                                               |
| pass_number: string (unique, format: TP-XXXX-XXXX)          |
| user_reference: string (encrypted/semantic external user id)|
| tier: enum (SILVER, GOLD, PLATINUM, BLACK)                  |
| cumulative_nights: integer                                  |
| cumulative_spend_idr: bigint                                |
| pts_balance: bigint                                         |
| is_active: boolean                                          |
| metadata: json                                              |
| created_at, updated_at                                      |
+-------------------------------------------------------------+
                               | 1
                               |
                               | *
+-------------------------------------------------------------+
|               htl_travel_pass_point_batches                 |
+-------------------------------------------------------------+
| id: uuid (PK)                                               |
| travel_pass_id: uuid (FK -> htl_travel_passes.id)           |
| batch_number: string (unique, format: BTH-YYYYMMDD-XXXX)    |
| originating_line: string (e.g., HTL_ROOM, VEN_TICKET)       |
| initial_pts: bigint                                         |
| remaining_pts: bigint                                       |
| liability_per_pt_idr: integer (default: 100)                |
| total_liability_idr: bigint                                 |
| state: enum (ACTIVE, PARTIALLY_REDEEMED, FULLY_REDEEMED,    |
|              EXPIRED, BREAKAGE_WRITTEN_OFF)                 |
| earned_at: timestamp                                        |
| expires_at: timestamp (earned_at + 730 days)                |
| breakage_recognized_at: timestamp (nullable)                |
+-------------------------------------------------------------+
```

---

## 2. Tabel Pendukung Kemitraan Aliansi & Log Transaksi

### 2.1 Tabel `htl_alliance_partners`
Menyimpan konfigurasi mitra aliansi resmi yang terhubung dengan gateway penukaran:
- `id`: uuid (PK)
- `partner_code`: string (unique, e.g., `GARUDA_AIR`, `SINGAPORE_AIR`, `KAI_EXECUTIVE`)
- `partner_name`: string
- `category`: string (`AIRLINE`, `RAILWAY`, `RIDE_HAILING`)
- `base_exchange_ratio`: decimal(8, 4) (misal 10.0000 = 10 PTS : 1 Mile)
- `transfer_fee_percentage`: decimal(5, 4) (misal 0.0350 = 3,5%)
- `min_points_transfer`: integer (default: 1.000)
- `max_points_transfer_daily`: integer (default: 100.000)
- `is_active`: boolean
- `api_endpoint`: string

### 2.2 Tabel `htl_alliance_transfer_logs`
Menyimpan jejak audit seluruh transaksi transfer poin ke mitra:
- `id`: uuid (PK)
- `travel_pass_id`: uuid (FK -> `htl_travel_passes.id`)
- `partner_code`: string
- `member_partner_account`: string (encrypted)
- `points_debited`: bigint
- `partner_units_credited`: bigint
- `transfer_fee_idr`: bigint
- `net_payable_idr`: bigint
- `status`: enum (`PENDING`, `PROCESSING`, `SUCCESS`, `FAILED`, `COMPENSATED`)
- `saga_id`: string
- `idempotency_key`: string (unique)
- `certificate_hash`: string
- `created_at`, `updated_at`

---

## 3. Tabel Penetapan Harga Kamar Reward & Kampanye Personalisasi

### 3.1 Tabel `htl_award_night_pricing_rules`
- `id`: uuid (PK)
- `hotel_property_id`: uuid (FK -> `htl_properties.id`)
- `room_type_id`: uuid (FK -> `htl_room_types.id`)
- `base_points_required`: integer
- `marginal_cost_idr`: bigint
- `floor_multiplier`: decimal(4, 2) (default: 1.15)
- `seasonality_multiplier`: decimal(4, 2) (default: 1.00)
- `is_active`: boolean

### 3.2 Tabel `htl_personalized_travel_campaigns`
- `id`: uuid (PK)
- `travel_pass_id`: uuid (FK -> `htl_travel_passes.id`)
- `campaign_code`: string
- `offer_title`: string
- `destination_property_id`: uuid (FK -> `htl_properties.id`)
- `featured_package_title`: string
- `points_required_discounted`: integer
- `valid_until`: timestamp
- `affinity_score`: decimal(5, 4)
- `interaction_status`: enum (`DELIVERED`, `VIEWED`, `CONVERTED`, `OPTED_OUT`)
- `created_at`, `updated_at`

