# Sari Ranah Express — API v1 & Webhooks Documentation

Dokumentasi resmi API v1 dan Webhook Outbox untuk platform logistik multimoda **Sari Ranah Express (SRX)**.

---

## 1. Ikhtisar & Arsitektur

API v1 menyediakan integrasi terprogram bagi mitra B2B shipper, marketplace, dan sistem pergudangan eksternal:
- **Format**: JSON RESTful API.
- **Base URL**: `/api/v1/logistics`
- **Keamanan**: Autentikasi token bearer **Laravel Sanctum** dengan sistem perizinan berbasis granular abilities.
- **Idempotensi**: Proteksi double-charge dan duplikasi pengiriman melalui header `Idempotency-Key`.
- **Ketahanan Jaringan**: Webhook outbox pattern dengan penandatanganan HMAC-SHA256, exponential backoff retry hingga 8 kali, dan antrean dead-letter.

---

## 2. Autentikasi & Otorisasi

Permintaan terotentikasi wajib menyertakan token bearer di header HTTP:

```http
Authorization: Bearer <sanctum_personal_access_token>
```

### Token Abilities (Hak Akses)
| Ability | Deskripsi | Endpoint Terkait |
|---|---|---|
| `quote:create` | Menghitung simulasi tarif & menerbitkan quote | `POST /quotes` |
| `shipment:create` | Melakukan pemesanan shipment baru | `POST /shipments` |
| `shipment:read` | Membaca daftar dan riwayat pengiriman | `GET /shipments`, `GET /shipments/{tracking_number}` |
| `*` | Hak akses penuh (super token) | Semua endpoint |

---

## 3. Rate Limiting (Pembatasan Laju)

Sistem menerapkan rate limiting bertingkat menggunakan token bucket algorithm:
- **Authenticated Endpoints**: `60 requests / minute` per token.
- **Public Tracking Endpoint**: `30 requests / minute` per IP address.

Header respons HTTP yang disertakan:
- `X-RateLimit-Limit`: Batas maksimum permintaan per jendela waktu.
- `X-RateLimit-Remaining`: Jumlah permintaan tersisa dalam jendela waktu aktif.
- `Retry-After`: Jumlah detik tunggu bila batas terlampaui (HTTP `429 Too Many Requests`).

---

## 4. Idempotensi Pengiriman

Untuk mencegah pemesanan ganda akibat gangguan koneksi atau retry otomatis dari klien, endpoint `POST /shipments` mendukung header idempotensi:

```http
Idempotency-Key: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d
```

Jika permintaan dengan `Idempotency-Key` yang sama dikirim ulang:
- Server tidak membuat transaksi baru atau mendebit saldo/kredit kembali.
- Mengembalikan data shipment yang telah dibuat sebelumnya dengan status HTTP `200 OK`.
- Menyertakan flag metadata `meta.idempotent_replay: true`.

---

## 5. Katalog Endpoint

### 5.1 Hitung Tarif (Create Quote)
Menerbitkan penawaran harga terkunci selama 15 menit dengan verifikasi hash anti-manipulasi.

- **Metode & URL**: `POST /api/v1/logistics/quotes`
- **Ability**: `quote:create`
- **Request Body**:
```json
{
  "origin_location_id": 1,
  "destination_location_id": 2,
  "service_level": "regular",
  "packages": [
    {
      "weight_g": 2500,
      "length_mm": 300,
      "width_mm": 200,
      "height_mm": 150,
      "description": "Sparepart Otomotif"
    }
  ],
  "declared_value_idr": 1500000,
  "insured": true,
  "cod_amount_idr": 0
}
```

- **Response (201 Created)**:
```json
{
  "data": {
    "quote_id": 42,
    "total_amount_idr": 78000,
    "chargeable_weight_g": 2500,
    "breakdown": {
      "base_rate": 60000,
      "fuel_surcharge": 9000,
      "insurance_fee": 3000,
      "vat": 6000
    },
    "valid_until": "2026-10-03T04:30:00Z",
    "hash": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
  }
}
```

---

### 5.2 Buat Pengiriman (Create Shipment)
Mengeksekusi pemesanan resmi berdasarkan quote yang valid.

- **Metode & URL**: `POST /api/v1/logistics/shipments`
- **Header Opsional**: `Idempotency-Key: <UUID>`
- **Ability**: `shipment:create`
- **Request Body**:
```json
{
  "quote_id": 42,
  "consignee_name": "Budi Santoso",
  "consignee_phone": "081234567890",
  "consignee_address": {
    "street": "Jl. Lambung Mangkurat No. 45",
    "city": "Banjarmasin",
    "postal_code": "70111"
  },
  "pin": "123456"
}
```

- **Response (201 Created)**:
```json
{
  "data": {
    "tracking_number": "SRX10000000018",
    "status": "booked",
    "status_label": "Menunggu Pickup",
    "service_level": "regular",
    "mode": "road",
    "total_amount_idr": 78000,
    "consignee_name": "Budi Santoso",
    "origin_location_id": 1,
    "destination_location_id": 2,
    "booked_at": "2026-10-03T04:15:00Z"
  }
}
```

---

### 5.3 Detail Pengiriman (Show Shipment)
- **Metode & URL**: `GET /api/v1/logistics/shipments/{tracking_number}`
- **Ability**: `shipment:read`

---

### 5.4 Daftar Pengiriman (List Shipments)
- **Metode & URL**: `GET /api/v1/logistics/shipments?status=in_transit`
- **Ability**: `shipment:read`
- **Pagination**: Kecepatan tinggi berbasis cursor pagination (`next_cursor`).

---

### 5.5 Pelacakan Publik (Public Tracking)
Endpoint publik tanpa autentikasi, menyembunyikan identitas sensitif (PII) penerima.

- **Metode & URL**: `GET /api/v1/logistics/tracking/{tracking_number}`
- **Rate Limit**: `30 req/min`
- **Response (200 OK)**:
```json
{
  "data": {
    "tracking_number": "SRX10000000018",
    "status": "in_transit",
    "status_label": "Dalam Perjalanan",
    "origin": "Hub Banjarmasin",
    "destination": "Depot Banjarbaru",
    "booked_at": "2026-10-03T04:15:00Z",
    "events": [
      {
        "event_type": "DEPARTED_HUB",
        "description": "Muatan diberangkatkan dari Hub Banjarmasin",
        "location": "Hub Banjarmasin",
        "occurred_at": "2026-10-03T06:00:00Z"
      }
    ]
  }
}
```

---

## 6. Webhook Outbox & Notifikasi Real-time

Untuk mengintegrasikan pembaruan status ke sistem shipper/mitra secara otomatis:

### 6.1 Format Payload & Signature
Setiap notifikasi webhook dikirimkan via HTTP POST dengan header keamanan:
- `X-Webhook-Signature`: HMAC SHA-256 dari seluruh payload mentah menggunakan endpoint secret yang terdaftar:
  ```php
  $signature = hash_hmac('sha256', $rawPayload, $endpointSecret);
  ```
- `X-Webhook-Event`: Nama tipe event (e.g. `shipment.delivered`).
- `X-Idempotency-Key`: UUID unik pengiriman untuk deduplikasi penerima.

### 6.2 Kebijakan Percobaan Ulang (Retry Policy)
Bila server penerima merespons di luar rentang `2xx` atau koneksi timeout (10 detik):
1. Pengiriman dialihkan ke status `failed`.
2. Penjadwalan retry otomatis dengan **exponential backoff** bertingkat (maksimal 8 percobaan):
   - Percobaan 1: 1 menit
   - Percobaan 2: 2 menit
   - Percobaan 3: 4 menit
   - Percobaan 4: 8 menit
   - Percobaan 5: 16 menit
   - Percobaan 6: 32 menit
   - Percobaan 7: 64 menit
   - Percobaan 8: 128 menit (~2,1 jam)
3. Setelah 8 kali kegagalan, pengiriman ditandai sebagai **`dead_letter`**.
4. Administrator atau dispatcher dapat memicu pengiriman ulang secara manual via CLI:
   ```bash
   php artisan lgx:retry-webhooks
   ```
   Atau memanggil action `DispatchWebhookAction::replay($delivery)`.

---

## 7. Enterprise API v3 (Multi-Tenant & Konglomerasi 12 Lini)

API v3 dirancang untuk portal eksekutif multi-tenant holding, integrasi webhook mitra berkecepatan tinggi, serta sinkronisasi perangkat seluler offline-first.

### 7.1 Standar Format Galat RFC 7807 (Problem Details)
Semua galat level API v3 mengembalikan response header `Content-Type: application/problem+json` dengan struktur baku:
```json
{
  "type": "https://api.autoserve.internal/v3/errors/ERR_RATE_LIMIT_EXCEEDED",
  "title": "Too Many Requests",
  "status": 429,
  "detail": "API rate limit exceeded for tenant.",
  "instance": "/api/v3/executive/kpis",
  "code": "ERR_RATE_LIMIT_EXCEEDED",
  "timestamp": "2026-10-22T08:15:00+08:00"
}
```

### 7.2 Endpoint Eksekutif Multi-Tenant
- `GET /api/v3/executive/kpis`: Mengembalikan metrik ringkasan eksekutif (omzet harian, modul aktif, status operasional, kepatuhan RFC 7807).
  - Header: `X-Tenant-ID: <tenant_uuid>`
- `GET /api/v3/executive/snapshot`: Mengembalikan status DR quorum multi-wilayah, RPO/RTO real-time, dan status kesiapan post-quantum.

### 7.3 Manajemen Webhook Mitra & Replay Dead-Letter Queue (DLQ)
- `GET /api/v3/partner/webhooks`: Daftar webhook terdaftar per tenant.
- `POST /api/v3/partner/webhooks`: Mendaftarkan endpoint webhook baru dengan secret signing key HMAC-SHA256.
- `POST /api/v3/partner/webhooks/{id}/replay`: Memutar ulang pesan yang terparkir di antrean DLQ setelah mitra memulihkan server tujuan.

### 7.4 Sinkronisasi Seluler Offline-First (Mobile Delta Sync)
- `POST /api/v3/mobile/sync/pull`: Menarik perubahan data delta sejak sinkronisasi terakhir menggunakan perbandingan jam vektor (*vector clocks*).
- `POST /api/v3/mobile/sync/push`: Mengunggah mutasi batch luring dari tablet/smartphone petugas lapangan.
- `GET /api/v3/mobile/sync/conflicts`: Riwayat audit ledger resolusi konflik versi (otoritas klinis & rekonsiliasi stok).

---

## 8. Konglomerasi 12 Lini & AI Enterprise Endpoints (Fase 96–100)

Ekosistem konglomerasi 12 lini bisnis terintegrasi menyediakan API spesifik per lini untuk orkestrasi lintas industri, tokenisasi RWA, dynamic pricing, anti-abuse, dan tata kelola keamanan.

### 8.1 FinTech, Tokenisasi RWA & Distribusi Dividen (Fase 96)
Mendistribusikan hasil dividen dari yield aset riil (misal armada truk logistik, sewa tenan mall, hasil panen agri) kepada para pemegang token RWA secara atomik.

- **Distribusi Dividen Lintas Lini**: `POST /api/v1/rwa/distributions`
- **Model Invariant**: $\text{Total Distributed} + \text{Residual Dust} \equiv \text{Net Distributable Revenue}$.
- **Request Body**:
```json
{
  "asset_mapping_id": "rwa-ast-logistics-truck-001",
  "gross_revenue_idr": 150000000,
  "operational_deduction_idr": 30000000,
  "total_token_supply": 1000000,
  "currency": "USDC"
}
```
- **Response (200 OK)**:
```json
{
  "status": "success",
  "distribution_id": "div-rwa-20261022-0091",
  "net_dividend_idr": 120000000,
  "dividend_per_token_idr": 120,
  "dust_residual_idr": 0,
  "dust_account_credited": "220101-RWA-DUST-ESCROW",
  "clearing_status": "COMPLETED",
  "timestamp": "2026-10-22T08:00:00Z"
}
```

---

### 8.2 Talent Gig Interoperability & Tugas Lintas Lini (Fase 97)
Bursa tenaga kerja fleksibel (*gig worker*) lintas lini holding (kurir logistik, event crew venue, teknisi mall, enumerator RS).

- **Klaim Tugas Gig**: `POST /api/v1/gig/tasks/{task_id}/claim`
  - Memvalidasi pembatasan beban kerja sesuai UU 13/2003 (maksimal 40 jam kerja per 7 hari kalender).
- **Pengiriman Bukti Kerja (Proof of Work)**: `POST /api/v1/gig/tasks/{task_id}/submit`
  - Menyertakan data telemetri GPS dan timestamp terverifikasi.
- **Persetujuan & Auto-Disbursement**: `POST /api/v1/gig/tasks/{task_id}/approve`
  - Memicu posting ledger instan ke rekening dompet mitra gig worker via `GigEconomyService`.

---

### 8.3 Unified Dynamic Pricing Engine (Fase 99)
Engine penetapan harga terpadu lintas 12 lini bisnis dengan multi-faktor (lonjakan permintaan, kapasitas armada, cuaca, jam sibuk).

- **Kalkulasi Kuotasi Dinamis**: `POST /api/v1/ai/pricing/calculate`
- **Invarian Perlindungan**: Prioritas kontrak B2B (`CONTRACT_PRIORITY`), margin lantai regulatori, dan batasan deviasi prediksi (MAPE $\le 12.5\%$).
- **Request Body**:
```json
{
  "service_line": "logistics",
  "contract_id": "ctr-corporate-golden-01",
  "base_rate_idr": 100000,
  "demand_surge_factor": 1.45,
  "fleet_utilization_pct": 88.5,
  "weather_severity_index": 0.20
}
```
- **Response (200 OK)**:
```json
{
  "status": "success",
  "quote_id": "prc-ai-891024",
  "applied_strategy": "CONTRACT_GUARANTEED_CAP",
  "base_rate_idr": 100000,
  "surge_multiplier": 1.15,
  "final_price_idr": 115000,
  "margin_floor_cleared": true,
  "valid_seconds": 900
}
```

---

### 8.4 Anti-Abuse Rate Limiter & Token Rotasi Telematika (Fase 100)
Melindungi seluruh antarmuka telematika IoT dan terminal IGD rumah sakit dari serangan bruteforce, scraping, dan manipulasi token.

- **Rotasi Token Klien/Perangkat**: `POST /api/v1/telematics/token/rotate`
- **Header Keamanan**: `X-Device-Fingerprint`, `X-Timestamp`, `X-HMAC-Signature`
- **Respons Karantina Otomatis (HTTP 423 Locked / 429 Too Many Requests)**:
  Bila terjadi anomali lebih dari 5 kali gagal dalam jendela 60 detik, IP/perangkat dialihkan ke karantina otomatis 30 menit.

---

### 8.5 Health-Check Eksekutif 12 Pilar Platform
- **CLI Health-Check**: `php artisan super:health-check`
- Menguji secara holistik 12 pilar platform:
  1. Multi-Tenant Ledger Balance Invariant ($\sum = 0$).
  2. RWA Token Distribution & Dust Reserve.
  3. Dynamic Pricing Bounds & Algorithmic Guardrails.
  4. Vector Clock Causality Engine.
  5. AES-256-GCM Medical Encryption Vault.
  6. Universal Talent Gig Allocation Limits.
  7. Active-Active Disaster Recovery Quorum (3/3).
  8. Post-Quantum Cryptographic Readiness.
  9. Partner Webhook DLQ Ingestion Integrity.
  10. Anti-Abuse Rate Limiter & Quarantine Table.
  11. Corporate Action Event Outbox Dispatcher.
  12. Regulatory Compliance Calendar Schedulers.
