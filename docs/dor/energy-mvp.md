# Definition of Ready (DoR): Lini 13 — Energi & Utilitas (Egy MVP)

## 1. Acuan & Dasar Regulasi / Bisnis
- **KONSEP.md**:
  - Baris 173: Event spine `prop.meter.read.v1` / `egy.meter.read.v1` ke ESG (Scope 2).
  - Baris 263: Urutan dependensi lintas lini (Egy sebagai pemasok energi dan port Intercompany).
  - Baris 1304–1313: Spesifikasi Minimum (MVP) Lini 13 Energi & Utilitas (`egy_`), peran pasokan energi properti grup, time-of-use tariff, konsolidasi tagihan per properti (DR `ar:egy:property:{id}:IDR` / CR `egy:utility_revenue:IDR`), PPA antar-entitas tereliminasi di Intercompany, larangan jalan pintas (tagihan tanpa bacaan meter nyata, PPA tanpa eliminasi, pendapatan terbalik).
  - Baris 1367–1370: Roadmap Fase 126–129.
- **PROGRESS.md**:
  - Baris 9975–10025: Fase 126 (Smart metering, TOU, unit commitment, PPA net metering), Fase 127 (Utility billing consolidation, district utilities), Fase 128 (REC, carbon, green lease), Fase 129 (Microgrid islanding, battery storage arbitrage).
- **Keputusan Pemilik**:
  - U-1 s/d U-6: Uang IDR integer rupiah, half-up rounding, energi dalam Wh integer, batas waktu UTC dengan konversi zona waktu lokasi (Asia/Jakarta), akun ledger idempoten `allow_negative=false`, idempotency key mandatory, RBAC seeder idempoten.
  - E-1 s/d E-8: Egy pemasok energi properti grup terkonsolidasi (bukan penagih tenant); register kumulatif Wh 15-menit append-only dengan modulus/reset handling; TOU LWBP/WBP; PPA cermin Intercompany Z9; unit commitment B0″.1; microgrid islanding B0″.2; arbitrase baterai B0″.3; event `egy.meter.read.v1` untuk ESG Scope 2.

---

## 2. Entitas & State Machine

### 2.1 SmartMeter (`egy_smart_meters`)
- **Atribut**: `id` (ULID/UUID), `meter_serial_number` (string unik), `consumer_property_type` (MALL, FACTORY, HOSPITAL, HOTEL, VENUE, OFFICE), `consumer_property_id` (string), `modulus` (bigint nullable, default 100.000.000 Wh), `status` (PROVISIONED, ACTIVE, RETIRED).
- **State Machine**:
  `PROVISIONED` ➔ `ACTIVE` ➔ `RETIRED`.

### 2.2 MeterReading (`egy_meter_readings`) — Fakta Append-Only
- **Atribut**: `id` (ULID/UUID), `meter_id` (FK), `reading_at` (datetime UTC), `cumulative_wh` (bigint integer), `delta_wh` (bigint integer), `interval_status` (OK, ROLLOVER, RESET, MISSING), `rate_per_kwh_minor` (int), `is_wbp` (bool).
- **Invarian**:
  - Unique constraint: `(meter_id, reading_at)`.
  - Append-only: Dilarang update/delete (ditolak oleh Model/DB).
  - Konsumsi interval = `MeterDelta::calculate($prevWh, $currWh, $modulus)`.

### 2.3 UtilityConsolidatedInvoice (`egy_utility_consolidated_invoices`)
- **Atribut**: `id` (ULID/UUID), `invoice_number` (unik, e.g. `INV-EGY-202610-0001`), `property_id` (string), `billing_period` (YYYY-MM), `total_wh` (bigint), `electricity_charge_minor` (bigint), `water_charge_minor` (bigint default 0), `district_cooling_charge_minor` (bigint default 0), `gas_charge_minor` (bigint default 0), `total_consolidated_minor` (bigint), `status` (DRAFT, ISSUED, SETTLED), `ledger_transaction_id` (nullable).
- **State Machine**:
  `DRAFT` ➔ `ISSUED` ➔ `SETTLED`.

### 2.4 UtilityInvoiceLine (`egy_utility_invoice_lines`)
- **Atribut**: `id` (ULID), `invoice_id` (FK), `window_name` (LWBP, WBP), `consumption_wh` (bigint), `rate_per_kwh_minor` (int), `subtotal_minor` (bigint).
- **Aturan Pembulatan**: `round_half_up(consumption_wh * rate_per_kwh_minor / 1000)`.

### 2.5 PpaContract (`egy_ppa_contracts`)
- **Atribut**: `id` (ULID), `contract_number` (unik), `producer_entity_id` (string), `grid_offtaker_id` (string), `feed_in_tariff_per_kwh_minor` (int), `status` (DRAFT, ACTIVE, TERMINATED).
- **State Machine**:
  `DRAFT` ➔ `ACTIVE` ➔ `TERMINATED`.

---

## 3. Tabel Posting & Intercompany Settlement

### 3.1 Tagihan Listrik Properti (Consolidated Invoice Issuance)
Contoh: Mall Grand Indonesia, Periode Oktober 2026.
Konsumsi LWBP: 100.000 Wh (100 kWh) @ Rp 1.450 = Rp 145.000.
Konsumsi WBP: 50.000 Wh (50 kWh) @ Rp 2.100 = Rp 105.000.
Total Tagihan: Rp 250.000.

| Ledger Account | Debit (IDR) | Credit (IDR) | Deskripsi |
| :--- | :--- | :--- | :--- |
| `ar:egy:property:MALL-GRAND-01:IDR` | 250.000 | 0 | Piutang utilitas dari properti mall |
| `egy:utility_revenue:IDR` | 0 | 250.000 | Pendapatan distribusi energi |

### 3.2 PPA Antar-Entitas (Intercompany Mirrored Posting — Z9 & E-4)
Contoh: Solar Farm Cirata (PT Energi Hijau Nusantara) offtake ke Grid Egy (PT Utilitas Grup).
Energi terkirim: 1.000 kWh @ Feed-in Tariff Rp 1.200 = Rp 1.200.000.

**Buku Produsen (`PT Energi Hijau Nusantara`):**
| Ledger Account | Debit (IDR) | Credit (IDR) | Deskripsi |
| :--- | :--- | :--- | :--- |
| `ar:ic:producer:egy:IDR` | 1.200.000 | 0 | Piutang antar-entitas ke Egy |
| `revenue:energy:ppa:IDR` | 0 | 1.200.000 | Pendapatan penjualan energi PPA |

**Buku Offtaker (`Egy Group Utility`):**
| Ledger Account | Debit (IDR) | Credit (IDR) | Deskripsi |
| :--- | :--- | :--- | :--- |
| `egy:power_purchase_expense:IDR` | 1.200.000 | 0 | Beban pembelian listrik PPA |
| `ap:ic:egy:producer:IDR` | 0 | 1.200.000 | Utang antar-entitas ke Produsen |

**Saat Konsolidasi Grup:**
Piutang `ar:ic:producer:egy:IDR` dan Utang `ap:ic:egy:producer:IDR` saling mengeliminasi ke 0.
Pendapatan `revenue:energy:ppa:IDR` dan Beban `egy:power_purchase_expense:IDR` saling mengeliminasi ke 0.

---

## 4. Alur Algoritma Optimasi (Pustaka BAGIAN 0″)
1. **Unit Commitment & Merit Order Dispatch** (B0″.1): Menghitung jadwal komitmen harian unit pembangkit (solar, gas, battery, thermal) meminimalkan startup dan marginal fuel cost untuk memenuhi beban harian.
2. **Microgrid Islanding** (B0″.2): Menyelesaikan alokasi kapasitas terbatas saat darurat jaringan: RS (prioritas 1) > Pabrik (prioritas 2) > Mall (prioritas 3), menggunakan 0/1 knapsack integer per kelompok.
3. **Battery Arbitrage** (B0″.3): Dynamic programming optimasi jadwal charge saat tarif LWBP dan discharge saat WBP dengan round-trip efficiency bps.

---

## 5. Event Spine
- Event: `egy.meter.read.v1`
  - Payload: `{meter_id, property_id, reading_at, interval_wh, cumulative_wh, is_wbp, co2_kg}`
  - Konsumen:
    1. **Esg** (Aktif - Scope 2 greenhouse gas emissions accounting berdasarkan emisi grid gCO2/kWh).
    2. **Mall** (Patch - rujukan kesiapan billing tenant mall).

---

## 6. Rute & Otorisasi RBAC
- `/energy`: Dashboard monitoring grid & ringkasan operasional (`energy_manager`, `grid_operator`, `admin`).
- `/energy/meters`: Monitoring smart meter & register telemetri.
- `/energy/invoices`: Tagihan utilitas terkonsolidasi per properti.
- `/energy/dispatch`: Unit commitment generator & dispatch order.
- `/energy/microgrid`: Islanding priority controller & battery arbitrage schedule.
- `/energy/ppa`: Kontrak PPA & settlement antar-entitas.
- Responsif mobile minimum 375px untuk operator on-call.

---

## 7. Rencana Audit Dua Sumber (`egy:audit`)
Audit memeriksa:
1. **Integritas Bacaan Meter vs Tagihan**:
   `Σ(delta_wh × rate_per_kwh_minor / 1000) untuk seluruh meter properti P` == `Σ baris tagihan pada UtilityConsolidatedInvoice properti P`.
2. **Integritas Tagihan vs Buku Besar (Ledger)**:
   `Σ total_consolidated_minor pada invoice berstatus ISSUED/SETTLED` == Saldo kredit pada `egy:utility_revenue:IDR` dan debit pada `ar:egy:property:*:IDR`.
3. **Konsistensi PPA Intercompany**:
   Setiap transaksi PPA di sisi Produsen memiliki transaksi cermin persis di sisi Egy dengan selisih saldo antar-entitas = 0.
4. **Validasi Model Fakta Append-Only**:
   Memverifikasi model `MeterReading` menolak operasi update dan delete.

---

## 8. Asumsi Konservatif
1. Zona waktu default adalah `Asia/Jakarta` (WIB) dengan jendela LWBP: 22:00–17:00 dan WBP: 17:00–22:00 (kelipatan 15 menit).
2. Faktor emisi CO2 grid Jawa-Madura-Bali default: 800 gCO2/kWh (integer bps 8.000 per 1.000 Wh).
3. Modulus register kumulatif default: 100.000.000 Wh (100.000 kWh).
