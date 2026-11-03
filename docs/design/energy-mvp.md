# Technical Design: Lini 13 — Energi & Utilitas (Egy MVP)

## 1. Arsitektur Komponen

```mermaid
graph TD
    SM[Smart Meter 15m Telemetry] -->|cumulative_wh, timestamp| IngestAction[IngestSmartGridMeterTelemetryAction]
    IngestAction --> MD[MeterDelta Calculator B0.2]
    MD --> Reading[MeterReading Append-Only Fact]
    Reading --> SpineEvent[Event: egy.meter.read.v1]
    SpineEvent --> ESGConsumer[ESG Scope 2 Emissions Consumer]

    Reading --> BillAggregator[ConsolidatedUtilityBillingService]
    BillAggregator --> Invoice[UtilityConsolidatedInvoice]
    Invoice --> InvLines[UtilityInvoiceLines TOU]
    Invoice --> LedgerPost[LedgerService: AR Property / Utility Revenue]

    PPAAction[SettlePowerPurchaseAgreementBillingAction] --> ICService[IntercompanyService Z9]
    ICService --> MirrorTx[Mirrored Elimination Pair: Net Zero]

    UCAction[GenerateUnitCommitmentScheduleAction] --> UC[UnitCommitment DP B0.1]
    MicrogridAction[DispatchMicrogridIslandingAction] --> PK[PriorityKnapsack B0.2]
    BessAction[OptimizeBatteryArbitrageScheduleAction] --> BA[BatteryArbitrage DP B0.3]
```

---

## 2. Skema Data & Relasi (ERD)

```mermaid
erDiagram
    egy_smart_meters ||--o{ egy_meter_readings : logs
    egy_smart_meters {
        uuid id PK
        string meter_serial_number UK
        string consumer_property_type
        string consumer_property_id
        bigint modulus
        string tariff_category
        string status
        timestamp created_at
    }

    egy_meter_readings {
        uuid id PK
        uuid meter_id FK
        timestamp reading_at
        bigint cumulative_wh
        bigint delta_wh
        string interval_status
        int rate_per_kwh_minor
        boolean is_wbp
        timestamp created_at
    }

    egy_utility_consolidated_invoices ||--|{ egy_utility_invoice_lines : contains
    egy_utility_consolidated_invoices {
        uuid id PK
        string invoice_number UK
        string property_id
        string billing_period
        bigint total_wh
        bigint electricity_charge_minor
        bigint water_charge_minor
        bigint district_cooling_charge_minor
        bigint gas_charge_minor
        bigint total_consolidated_minor
        string status
        string ledger_transaction_id
    }

    egy_utility_invoice_lines {
        uuid id PK
        uuid invoice_id FK
        string window_name
        bigint consumption_wh
        int rate_per_kwh_minor
        bigint subtotal_minor
    }

    egy_ppa_contracts {
        uuid id PK
        string contract_number UK
        string producer_entity_id
        string grid_offtaker_id
        int feed_in_tariff_per_kwh_minor
        string status
    }
```

---

## 3. Alur Intercompany PPA (Z9 / E-4)

```mermaid
sequenceDiagram
    autonumber
    actor Offtaker as Egy Offtaker
    participant PPA as SettlePowerPurchaseAgreementBillingAction
    participant IC as IntercompanyService
    participant Ledger as LedgerService
    actor Producer as Genco Producer

    Offtaker->>PPA: execute(contractId, deliveredKwh, idempotencyKey)
    PPA->>PPA: Compute totalBill = deliveredKwh * feedInTariff
    PPA->>IC: createMirroredTransaction(producerEntity, offtakerEntity, amount, key)
    IC->>Ledger: Post Producer Book (DR AR IC Egy / CR Revenue PPA)
    IC->>Ledger: Post Offtaker Book (DR Power Purchase Expense / CR AP IC Producer)
    PPA-->>Offtaker: Settlement Success (Mirrored Pair Created)
```

---

## 4. Idempotensi & Validasi Fakta Append-Only (PR4, PR5, U-4)
1. **Idempotency Key Format**:
   - Meter Telemetry: `egy:telemetry:{meter_serial}:{timestamp_iso}`.
   - Invoice Generation: `egy:invoice:{property_id}:{billing_period}`.
   - PPA Settlement: `egy:ppa:{contract_number}:{period_or_batch_id}`.
2. **Append-Only Fact Model Guards**:
   Model `MeterReading` meng-override event `updating` dan `deleting` pada Eloquent:
   ```php
   static::updating(function () {
       throw new \RuntimeException('MeterReading is an immutable append-only fact.');
   });
   static::deleting(function () {
       throw new \RuntimeException('MeterReading is an immutable append-only fact.');
   });
   ```
3. **Rollover & Missing Data Handling**:
   - Jika `reading_at` berselisih > 15 menit dari bacaan terakhir, interval yang terlewat dicatat dengan `interval_status = 'MISSING'` dan `delta_wh = 0`.
   - Modulus rollover register (misal dari 99.999.000 ke 15.000 Wh dengan modulus 100.000.000 Wh) dihitung dengan `MeterDelta::calculate(99999000, 15000, 100000000) = 16000 Wh` dan status `'ROLLOVER'`.
   - Event reset manual dicatat dengan status `'RESET'`.

---

## 5. Rencana UI Operator Grid (375px Responsive)
- Header ringkas dengan status operasional jaringan, total konsumsi hari ini (kWh), beban puncak (MW), dan frekuensi/tegangan nominal.
- Tabel meter dengan badge status interval (OK, Rollover, Missing) yang dapat di-tap pada layar mobile.
- Tombol aksi darurat: Islanding Microgrid dengan slider alokasi beban prioritas (RS > Pabrik > Mall).
