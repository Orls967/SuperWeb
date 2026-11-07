# Desain Teknis — Fase 93: Mine Planning, Fleet Dispatch & Fuel Management

Dokumen desain arsitektur teknis vertikal slice untuk Fase 93 mencakup perincian logika perancangan tambang, penugasan armada deterministik, telemetri IoT alat berat, saga perawatan prediktif, serta deteksi anomali bahan bakar.

---

## 1. Diagram State Machine (D1)

### 1.1 State Machine Perencanaan Tambang (`MinePlan`)
State machine 8 status untuk mengontrol siklus hidup rencana bulanan pit:

```mermaid
stateDiagram-v2
    [*] --> draft: Buat rencana pit
    draft --> submitted: Ajukan rencana bulanan
    submitted --> reviewed: Verifikasi teknis mine_planner
    reviewed --> approved: Persetujuan mine_manager
    approved --> active: Efektif awal bulan operasi
    active --> revised: Penyesuaian jam hujan / target
    revised --> active: Pengesahan versi baru
    active --> closed: Selesai periode operasi
    draft --> cancelled: Batal pengajuan
    submitted --> cancelled: Penolakan rencana
    closed --> [*]
    cancelled --> [*]
```

### 1.2 State Machine Siklus Angkut (`HaulCycle`)
State machine 8 status untuk pergerakan truk dalam satu siklus:

```mermaid
stateDiagram-v2
    [*] --> queued: Truk masuk antrean papan dispatch
    queued --> assigned: Penugasan M2 ke shovel
    assigned --> travelling_empty: Perjalanan menuju pit
    travelling_empty --> loading: Pemuatan material oleh excavator
    loading --> hauling: Perjalanan bermuatan ke stockpile/crusher
    hauling --> dumping: Pembongkaran muatan
    dumping --> completed: Muatan tercatat & truk bebas
    travelling_empty --> aborted: Kerusakan alat / kendala rute
    loading --> aborted: Insiden pemuatan
    hauling --> aborted: Insiden jalan tambang
    completed --> [*]
    aborted --> [*]
```

### 1.3 State Machine Peralatan Tambang (`Equipment`)
State machine 8 status untuk siklus ketersediaan alat berat:

```mermaid
stateDiagram-v2
    [*] --> available: Unit siap operasi di site
    available --> assigned: Ditugaskan ke shift
    assigned --> operating: Mesin aktif & bergerak
    operating --> standby: Idle / antre / pergantian shift
    standby --> operating: Lanjut siklus kerja
    operating --> breakdown: Malfungsi mendadak
    standby --> breakdown: Gagal starter / kebocoran
    breakdown --> under_maintenance: Masuk bengkel (Saga WO)
    under_maintenance --> returned_to_service: Inspeksi pasca perbaikan lolos
    returned_to_service --> available: Masuk pool unit siap
    under_maintenance --> retired: Unit habis umur teknis (decommission)
    retired --> [*]
```

### 1.4 State Machine Anomali Bahan Bakar Kontraktor (`FuelAnomalyCase`)
State machine 8 status untuk penanganan kasus deviasi BBM kontraktor:

```mermaid
stateDiagram-v2
    [*] --> detected: Deviasi M3 melebihi ambang batas
    detected --> verifying: Validasi telematika sensor BBM
    verifying --> confirmed: Anomali terbukti valid
    confirmed --> payment_held: Pembekuan tagihan kontraktor (Ledger)
    payment_held --> disputed: Kontraktor mengajukan sanggahan
    disputed --> resolved_deducted: Sanggahan ditolak (Potongan permanen)
    disputed --> resolved_released: Sanggahan diterima (Rilis dana hold)
    payment_held --> resolved_deducted: Tanpa sanggahan dalam batas waktu
    confirmed --> written_off: Biaya diikhlaskan / ambang mikro
    resolved_deducted --> [*]
    resolved_released --> [*]
    written_off --> [*]
```

---

## 2. Diagram Alur Utama & Saga Kompensasi (D2)

### 2.1 Alur Utama Dispatch Truk Berbasis Waktu Mulai Muat
```mermaid
flowchart TD
    D["Dispatcher (dispatcher_mine)"] --> UI["UI /mining/dispatch"]
    UI --> A["DispatchHaulTruckAction"]
    A --> E["min_equipment: Truk & Shovel Aktif"]
    A --> M2["NeedTimeTruckDispatcher (Algoritma M2)"]
    M2 --> RES["Hasil: Pasangan Truk-Shovel & Rasio MF"]
    RES --> HC["min_haul_cycles: Insert Cycle (ASSIGNED)"]
    HC --> EQ["min_equipment: Transisi ke ASSIGNED"]
    EQ --> OUT["Output UI & Visualisasi Gantt"]
```

### 2.2 Saga Perawatan Prediktif & Suku Cadang (dengan Kompensasi)
```mermaid
flowchart TD
    T["IngestTelemetryCommand: Getaran/Suhu > Ambang"] --> SAGA["PredictiveMaintenanceWorkOrderSaga"]
    SAGA --> S1["Langkah 1: Request Maintenance Work Order (K9)"]
    S1 --> CHK1{"WO Diterima?"}
    CHK1 -- Ya --> S2["Langkah 2: Propose MRP Requisition Suku Cadang"]
    CHK1 -- Tidak --> FAIL1["Saga Gagal (Tanpa Kompensasi)"]
    S2 --> CHK2{"Suku Cadang Tersedia?"}
    CHK2 -- Ya --> S3["Langkah 3: Re-Plan Target Shift (Kurangi Jam Downtime)"]
    CHK2 -- Tidak --> COMP1["KOMPENSASI 1: Batalkan WO Perawatan Terbit"]
    COMP1 --> FAIL2["Status Saga: COMPENSATED_FAILED"]
    S3 --> SUCC["Status Saga: COMPLETED"]
```
    else Work Order Ditolak
        Saga-->>Telemetry: Saga dibatalkan di awal
    end
```

---

## 3. Entity-Relationship Diagram (D3)

```mermaid
erDiagram
    min_sites ||--o{ min_pits : "memiliki"
    min_sites ||--o{ min_equipment : "lokasi pool"
    min_sites ||--o{ min_mine_plans : "rencana produksi"
    min_mine_plans ||--o{ min_mine_plan_shifts : "alokasi shift"
    min_pits ||--o{ min_mine_plan_shifts : "target pit"
    min_equipment ||--o{ min_equipment_telemetries : "data telemetri"
    min_equipment ||--o{ min_haul_cycles : "sebagai truk / shovel"
    min_haul_cycles ||--o{ min_haul_cycle_histories : "riwayat status"
    min_equipment ||--o{ min_fuel_anomaly_cases : "objek deviasi"
    min_fuel_anomaly_cases ||--o{ min_fuel_anomaly_histories : "riwayat kasus"
    min_fuel_anomaly_cases ||--o{ min_contractor_payment_holds : "dokumen penahanan"

    min_mine_plans {
        uuid id PK
        uuid site_id FK
        string plan_code UK
        string period_month
        bigint target_ore_kg
        string status
        int version
    }

    min_mine_plan_shifts {
        uuid id PK
        uuid mine_plan_id FK
        uuid pit_id FK
        date shift_date
        string shift_code
        bigint target_tonnage_kg
        bigint actual_tonnage_kg
        int effective_hours_adjusted
    }

    min_haul_cycles {
        uuid id PK
        uuid truck_id FK
        uuid shovel_id FK
        date shift_date
        string shift_code
        bigint cycle_seq
        string status
        bigint target_payload_kg
        bigint actual_payload_kg
        int distance_meters
        bigint fuel_consumed_ml
    }

    min_fuel_anomaly_cases {
        uuid id PK
        string case_number UK
        string contractor_party_id
        uuid equipment_id FK
        bigint actual_fuel_ml
        bigint baseline_fuel_ml
        int excess_bps
        bigint held_amount_idr
        string status
    }

    min_contractor_payment_holds {
        uuid id PK
        string hold_reference UK
        string contractor_party_id
        uuid case_id FK
        bigint hold_amount_idr
        string status
        string idempotency_key UK
    }
```

---

## 4. Flowchart Jurnal Akuntansi Buku Besar (D4)

```mermaid
flowchart TD
    A["Tutup Shift Operasional"] --> B{"Ada Tagihan Kontraktor?"}
    B -- Ya --> C["Posting Tagihan Hauling"]
    C --> C1["Debit: expense:min:hauling_contractor:IDR"]
    C --> C2["Kredit: ap:min:contractor:{party}:IDR"]

    D["Deteksi Anomali BBM (M3 > 1.500 bps)"] --> E["Kasus Anomali Dikonfirmasi"]
    E --> F["Eksekusi Hold Bayaran"]
    F --> F1["Debit: ap:min:contractor:{party}:IDR"]
    F --> F2["Kredit: min:contractor_payment_hold:IDR"]

    G{"Hasil Sanggahan / Investigasi"}
    G -- "Ditolak / Terbukti Boros" --> H["Eksekusi Potongan Definitif"]
    H --> H1["Debit: min:contractor_payment_hold:IDR"]
    H --> H2["Kredit: min:fuel_variance_recovery:IDR"]

    G -- "Diterima / Tidak Terbukti" --> I["Rilis Penahanan Dana"]
    I --> I1["Debit: min:contractor_payment_hold:IDR"]
    I --> I2["Kredit: ap:min:contractor:{party}:IDR"]
```

---

## 5. Flowchart Konteks Modul & Batas Integrasi (D5)

```mermaid
flowchart LR
    subgraph Mining["Modul Mining (min_)"]
        Planning["Mine Planning Engine"]
        Dispatch["Fleet Dispatch Controller"]
        Telemetry["Telemetry Ingest Action"]
        Fuel["Fuel Management Service"]
        Audit["AuditMiningCommand"]
    end

    subgraph Core["Modul Core & Shared"]
        Rbac["RBAC & Policies"]
        Clock["ClockInterface"]
        Alg["Algorithms: M1, M2, M3"]
    end

    subgraph AssetMod["Modul Asset"]
        AssetLookup["AssetRegistryLookupInterface"]
        WO["MaintenanceWorkOrderRequester"]
    end

    subgraph ProcMod["Modul Procurement"]
        MRP["MrpRequisitionProposer"]
    end

    subgraph BankMod["Modul Banking & Ledger"]
        Ledger["LedgerService (Double-Entry)"]
    end

    Planning --> Alg
    Planning --> Clock
    Dispatch --> Alg
    Telemetry --> WO
    WO --> MRP
    Fuel --> Ledger
    Dispatch --> Rbac
    Audit --> Ledger
```
