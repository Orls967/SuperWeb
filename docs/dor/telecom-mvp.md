# Definition of Ready (DoR): Lini 14 — Telko & Data Center (Tlx MVP)

## 1. Acuan & Dasar Regulasi / Bisnis
- **KONSEP.md**:
  - Baris 263: Urutan dependensi eksekusi Lini 14 (Telko & DC memanfaatkan port Intercompany yang dibangun Lini 13).
  - Baris 1315–1325: Spesifikasi Minimum (MVP) Lini 14 Telko & Data Center (`tlx_`), peran penyedia konektivitas IoT tunggal grup, colocation rak & daya DC, NOC alarm correlation, larangan jalan pintas (multiple device registries, PUE konstan, alarm tanpa root-cause, rating tanpa round-up 1 KB).
  - Baris 1372–1375: Roadmap Fase 130–132 (Network slices, IoT device registry, DC colocation, CDR rating, NOC topology correlation).
- **PROGRESS.md**:
  - Baris 10030–10075: Fase 130 (5G slicing, tower/pop/dc physical infrastructure, iot device registry, single source of device truth), Fase 131 (Data center colocation, PUE, cloud compute, DR drill), Fase 132 (ISP billing, eSIM mobile plan, interconnect settlement, churn risk).
- **Keputusan Pemilik**:
  - T-1: Registry perangkat IoT tunggal `tlx_iot_devices` + `IotDeviceRegistry` contract di `modules/Tlx/Contracts`.
  - T-2: Aktivasi SIM/eSIM per perangkat; satu SIM aktif per perangkat secara bersamaan.
  - T-3: Pemakaian data dari CDR append-only, unik `(device_id, session_id)`. Rating pembulatan ke atas per 1 KB (1.024 byte).
  - T-4: Tagihan konektivitas bulanan per entitas pemakai lewat Intercompany (Z9). Pro-rata hari aktif: `round_half_up(fee * hari_aktif / hari_bulan)`.
  - T-5: Colocation DC: kontrak dengan rack & daya komit (kW). Tagihan bulanan = rack * tarif + max(0, puncak - komit) * tarif kelebihan. PUE dihitung dari data terukur (energi total fasilitas / energi IT).
  - T-6: NOC: alarm append-only. Supresi alarm turunan topologi (B0″.4 TopologySuppressor). Tiket akar saja. MTTR = resolved_at - opened_at dalam menit.

---

## 2. Entitas & State Machine

### 2.1 IotDevice (`tlx_iot_devices`) — Registry Tunggal Perangkat IoT
- **Atribut**: `id` (UUID), `device_code` (string unik, e.g. IMEI/Serial), `device_type` (TELEMATICS, BUILDING_SENSOR, ENERGY_METER, MINING_SENSOR, PATIENT_MONITOR), `owner_entity_id` (string), `using_module` (Telematics, Proptech, Energy, Mining, Hospital), `active_sim_iccid` (string nullable, unique when active), `monthly_fee_minor` (bigint integer, e.g. 30.000 IDR), `status` (PROVISIONED, ACTIVE, SUSPENDED, RETIRED), `activated_at` (timestamp nullable), `suspended_at` (timestamp nullable), `retired_at` (timestamp nullable).
- **State Machine**:
  `PROVISIONED` ➔ `ACTIVE` ➔ `SUSPENDED` ➔ `ACTIVE` ➔ `RETIRED`.

### 2.2 SimProfile (`tlx_sim_profiles`)
- **Atribut**: `id` (UUID), `iccid` (string unik), `imsi` (string unik), `msisdn` (string unik), `assigned_device_id` (FK nullable), `plan_code` (string), `monthly_quota_mb` (bigint), `status` (AVAILABLE, ALLOCATED, DEACTIVATED).
- **Invarian**: Satu SIM hanya dapat dialokasikan ke tepat satu perangkat aktif pada suatu waktu.

### 2.3 TelecomCdr (`tlx_cdrs`) — Fakta Append-Only
- **Atribut**: `id` (UUID), `device_id` (FK `tlx_iot_devices`), `session_id` (string), `started_at` (datetime UTC), `ended_at` (datetime UTC), `bytes_transferred` (bigint), `rated_bytes` (bigint, ceil kelipatan 1024), `duration_seconds` (int).
- **Invarian**:
  - Unique constraint: `(device_id, session_id)`.
  - Append-only: Dilarang update/delete (ditolak oleh Model/DB).
  - Rating: `UsageRater::rateSessionBytes($bytes, 1024)`.

### 2.4 ConnectivityInvoice (`tlx_connectivity_invoices`)
- **Atribut**: `id` (UUID), `invoice_number` (unik, e.g. `INV-TLX-202610-0001`), `owner_entity_id` (string), `billing_period` (YYYY-MM), `active_device_count` (int), `total_device_fee_minor` (bigint), `total_quota_mb` (bigint), `total_consumed_mb` (bigint), `excess_mb` (bigint), `excess_charge_minor` (bigint), `total_charge_minor` (bigint), `intercompany_transaction_id` (string nullable), `status` (DRAFT, ISSUED, SETTLED).
- **State Machine**:
  `DRAFT` ➔ `ISSUED` ➔ `SETTLED`.

### 2.5 DataCenterFacility (`tlx_data_centers`)
- **Atribut**: `id` (UUID), `dc_code` (unik), `name` (string), `city` (string), `total_racks_capacity` (int), `occupied_racks` (int), `status` (ACTIVE, MAINTENANCE).

### 2.6 ColocationContract (`tlx_colocation_contracts`)
- **Atribut**: `id` (UUID), `contract_number` (unik), `tenant_party_id` (string), `dc_id` (FK), `racks_allocated` (int), `power_commit_kw` (int), `monthly_rack_rate_minor` (bigint, e.g. 5.000.000 IDR), `power_rate_per_kwh_minor` (bigint), `excess_power_rate_per_kwh_minor` (bigint, e.g. 1.500.000 IDR/kW), `status` (ACTIVE, SUSPENDED, TERMINATED).

### 2.7 PowerReading (`tlx_dc_power_readings`) — Fakta Append-Only
- **Atribut**: `id` (UUID), `dc_id` (FK), `reading_at` (datetime UTC), `total_facility_energy_kwh` (bigint), `it_equipment_energy_kwh` (bigint), `peak_power_kw` (int).
- **Invarian**:
  - Append-only.
  - PUE = `total_facility_energy_kwh / it_equipment_energy_kwh` (BigRational / double).

### 2.8 ColocationInvoice (`tlx_colocation_invoices`)
- **Atribut**: `id` (UUID), `invoice_number` (unik), `contract_id` (FK), `tenant_party_id` (string), `billing_period` (YYYY-MM), `rack_fee_minor` (bigint), `committed_power_charge_minor` (bigint), `excess_power_charge_minor` (bigint), `total_minor` (bigint), `status` (ISSUED, SETTLED).

### 2.9 NetworkElement (`tlx_network_elements`) & TopologyGraph
- **Atribut**: `id` (UUID), `element_code` (unik), `element_type` (CORE_ROUTER, AGGREGATION_SWITCH, SITE, LINK, CELL_TOWER), `parent_element_id` (FK nullable), `status` (OPERATIONAL, DEGRADED, DOWN).

### 2.10 NocAlarm (`tlx_noc_alarms`) — Fakta Append-Only
- **Atribut**: `id` (UUID), `alarm_code` (unik), `element_id` (FK), `alarm_type` (LINK_DOWN, POWER_FAIL, BGP_DOWN, HARDWARE_FAIL), `severity` (CRITICAL, MAJOR, MINOR), `is_suppressed` (bool), `suppressed_by_element_id` (FK nullable), `ticket_id` (FK nullable), `raised_at` (datetime UTC).

### 2.11 NocTicket (`tlx_noc_tickets`)
- **Atribut**: `id` (UUID), `ticket_number` (unik, e.g. `TCK-NOC-202610-0001`), `root_element_id` (FK), `severity` (CRITICAL, MAJOR, MINOR), `status` (OPEN, ACKNOWLEDGED, RESOLVED, CLOSED), `mttr_minutes` (int nullable), `sla_target_minutes` (int), `is_sla_breached` (bool), `opened_at` (datetime UTC), `resolved_at` (datetime UTC nullable).

---

## 3. Tabel Posting & Intercompany Settlement

### 3.1 Tagihan Konektivitas IoT Grup (Intercompany)
Contoh: Entitas Logistik (`ENT-LOGISTICS-01`), 100 perangkat aktif @ 30.000 IDR, aktif 10 hari dari 30 hari:
- Fee pro-rata = `round_half_up(100 * 30.000 * 10 / 30) = 1.000.000 IDR`.
- Kelebihan kuota = 0 IDR.
- Total = 1.000.000 IDR.
- **Posting Pembukuan Tlx (Seller)**:
  - DR `ar:ic:tlx:logistics:IDR` 1.000.000
  - CR `tlx:iot_service_revenue:IDR` 1.000.000
- **Posting Pembukuan Logistik (Buyer / Cermin via Z9)**:
  - DR `expense:logistics:telecom:IDR` 1.000.000
  - CR `ap:ic:logistics:tlx:IDR` 1.000.000
- **Eliminasi Konsolidasi Grup**:
  - `ar:ic:tlx:logistics:IDR` + `ap:ic:logistics:tlx:IDR` = 0.

### 3.2 Tagihan Colocation Data Center
Contoh: Tenant FinTech `PARTY-CORP-A`, 2 rack @ 5.000.000 IDR, komit daya 10 kW, puncak terukur 12 kW (kelebihan 2 kW @ 1.500.000 IDR):
- Rack fee = 10.000.000 IDR.
- Excess power = `2 kW * 1.500.000 = 3.000.000 IDR`.
- Total = 13.000.000 IDR.
- **Posting**:
  - DR `ar:tlx:tenant:PARTY-CORP-A:IDR` 13.000.000
  - CR `tlx:colocation_revenue:IDR` 10.000.000
  - CR `tlx:power_revenue:IDR` 3.000.000

---

## 4. Rute & Otorisasi Peran (RBAC)
- `/telecom` ➔ `telecom_admin`, `noc_operator`, `datacenter_ops`.
- `/telecom/devices` ➔ `telecom_admin`, `noc_operator`.
- `/telecom/noc/alarms` ➔ `noc_operator`, `noc_engineer`, `tlx_admin`.
- `/telecom/datacenter/colocation` ➔ `datacenter_ops`, `telecom_admin`.
- `/telecom/billing/connectivity` ➔ `telecom_billing`, `tlx_admin`.
- Anti-IDOR: Entitas A hanya dapat melihat tagihan konektivitas miliknya; tenant B hanya melihat tagihan colocation miliknya.
- Mobile viewport: NOC on-call dashboard dioptimalkan untuk lebar 375 px.

---

## 5. Perencanaan Audit Dua-Sumber (`tlx:audit`)
Audit command `tlx:audit` diperkuat untuk memeriksa integritas dua-sumber:
1. **Perangkat Aktif vs Tagihan**: Perangkat aktif tertagih = perangkat aktif di registry per hari.
2. **CDR vs Tagihan**: Σ CDR terating (1 KB ceil) = Σ baris tagihan konektivitas.
3. **Colocation vs Bacaan Daya**: Tagihan colocation = kontrak aktif + kelebihan daya dari pembacaan puncak nyata.
4. **Korelasi Alarm Topologi**: Setiap alarm tersupresi terbukti memiliki leluhur (ancestor) berstatus down di graf topologi DAG (`TopologySuppressorVerifier`).
5. **PUE Faktual**: PUE dihitung dari bacaan daya terukur, bukan konstanta.
6. **Intercompany Mirror Equilibrium**: `ar:ic:tlx:{buyer}:IDR` + `ap:ic:{buyer}:tlx:IDR` = 0.
7. **Kompatibilitas Fixture**: `TlxAuditCorruptionFixture` tetap tertangkap dengan status exit 1.

---

## 6. Usulan DECISIONS & Register Minus
- **Usulan DECISIONS**:
  - Rating CDR dibulatkan ke atas per 1 KB (1.024 bytes) per sesi individual, dan kelebihan kuota bulanan diagregasi dan dibulatkan ke MB integer.
  - Pro-rata biaya langganan perangkat dihitung berdasarkan hari aktif dalam bulan kalender `round_half_up(fee * active_days / days_in_month)`.
- **Register Minus Usulan**:
  - M-TLX-01: Migrasi otomatis perangkat legacy dari `oto_telematics_devices` ke `tlx_iot_devices` disiapkan sebagai PATCH (Z13), tidak diterapkan langsung di modul Telematics.
  - M-TLX-02: Integrasi provisioning 5G core network slice simulator disederhanakan pada alokasi bandwidth matematis tautan (link capacity).
