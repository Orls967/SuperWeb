# Dokumen Desain Teknis — Fase 177: Aviation Core (Lini 26)

## 1. Arsitektur Komponen & Konteks Modul
Modul `Aviation` dibangun sebagai vertical slice mandiri di bawah namespace `Modules\Aviation`, menggantikan rancangan purwarupa lama di `Modules\Integration\Application\Services\AviationService`. Modul ini mengendalikan operasional bandara terkoordinasi, armada pesawat udara komersial, perizinan slot musiman, dispatch kelaikan terbang, inventori kursi dan reservasi tiket, serta penerimaan kargo udara regulasi IATA DG.

```mermaid
flowchart TD
    subgraph CorePlatform ["Core Platform & External Modules"]
        CoreAuth["Core RBAC & Auth"]
        AssetMod["Asset (ast_) Maintenance WO"]
        LgxMod["Logistics (lgx_) Custody & Multimodal"]
        PtyMod["Party (pty_) KYC & Sanctions"]
        HcmMod["Hcm Employee Directory (Crew)"]
        LedgerMod["Banking / Ledger Posting"]
        ExtGateway["AviationExternalFactors (avi_external_factors)"]
    end

    subgraph AviationModule ["Aviation Module (avi_)"]
        MasterAviation["Master: Aircraft, Airports, Routes, Operators"]
        SlotMgr["A1: Slot Coordination & Season Allocator"]
        DispatchGate["A2: Airworthiness Dispatch & A3: W&B Engine"]
        SeatInv["G7-K26: Seat Inventory & Reserver Engine"]
        TicketBooking["Passenger Booking & A4: Refund Engine"]
        AirCargo["Air Cargo Reception & A5: DG Eligibility Engine"]
        MaintTracker["Aircraft Maintenance & Airworthiness Tracker"]
        SagaCoordinator["Aviation Distributed Saga Coordinator"]
        AuditEngine["avi:audit Double-Source Reconciler"]
    end

    CoreAuth --> AviationModule
    AviationModule --> LedgerMod
    MaintTracker -->|K9 Request| AssetMod
    AirCargo -->|Custody Verifier| LgxMod
    TicketBooking -->|KYC Check| PtyMod
    DispatchGate -->|Crew Lookup| HcmMod
    ExtGateway --> AviationModule
```

---

## 2. Diagram State Machine (D1)

### 2.1 State Machine Penerbangan (`FlightStateMachine` — 8 Status)
```mermaid
stateDiagram-v2
    [*] --> planned: Buat Jadwal
    planned --> slot_secured: Konfirmasi Slot Bandara
    slot_secured --> airworthy_cleared: Lolos A2 Kelaikan & A3 W&B
    airworthy_cleared --> boarding: Gate Dibuka
    boarding --> departed: Lepas Landas (Off-Block)
    departed --> arrived: Mendarat (On-Block)
    arrived --> closed: Rekonsiliasi & Tutup Penerbangan
    planned --> cancelled: Dibatalkan (Cuaca / Operasional)
    slot_secured --> cancelled: Dibatalkan
    airworthy_cleared --> cancelled: Gagal Teknis / Cuaca
    closed --> [*]
    cancelled --> [*]
```

### 2.2 State Machine Tiket Penumpang (`TicketStateMachine` — 8 Status)
```mermaid
stateDiagram-v2
    [*] --> held: Hold Kursi (TTL 15m)
    held --> paid: Pembayaran Diterima
    paid --> issued: Tiket Terbit & PNR Siap
    issued --> checked_in: Check-in & Bagasi Tercatat
    checked_in --> boarded: Masuk Pesawat (Boarding Pass Valid)
    boarded --> flown: Penerbangan Selesai (Pengakuan Revenue)
    issued --> refunded: Pengajuan Pembatalan & Refund (A4)
    paid --> refunded: Pengembalian Dana Sebelum Terbit
    checked_in --> refunded: Refund Darurat / IRROPS
    issued --> no_show: Penumpang Tidak Hadir di Gate
    flown --> [*]
    refunded --> [*]
    no_show --> [*]
```

### 2.3 State Machine Permintaan Slot (`SlotRequestStateMachine` — 8 Status)
```mermaid
stateDiagram-v2
    [*] --> submitted: Maskapai Ajukan Permintaan
    submitted --> under_review: Koordinasi Slot IATA
    under_review --> allocated: Lolos Algoritma A1
    under_review --> waitlisted: Kuota Habis (Antrean)
    waitlisted --> allocated: Slot Tersedia Kembali
    allocated --> confirmed: Maskapai Konfirmasi Jadwal
    confirmed --> returned: Sukarela Dikembalikan
    confirmed --> revoked: Pelanggaran / Tidak Dipakai (<80%)
    waitlisted --> expired: Musim Berakhir
    allocated --> expired: Batas Waktu Konfirmasi Lewat
    returned --> [*]
    revoked --> [*]
    expired --> [*]
```

### 2.4 State Machine Konsinyasi Kargo Udara (`CargoConsignmentStateMachine` — 7 Status)
```mermaid
stateDiagram-v2
    [*] --> booked: Booking Kuota Kargo
    booked --> accepted: Penerimaan Fisik di Gudang
    accepted --> dg_cleared: Lolos Pemeriksaan A5 DG & Suhu
    accepted --> rejected: Gagal Pemeriksaan DG / Holdover
    dg_cleared --> loaded: Masuk ULD / Compartment
    loaded --> in_flight: Terbang Bersama Pesawat
    in_flight --> delivered: Serah Terima di Tujuan
    delivered --> [*]
    rejected --> [*]
```

### 2.5 State Machine Kunjungan Perawatan (`MaintenanceVisitStateMachine` — 7 Status)
```mermaid
stateDiagram-v2
    [*] --> planned: Jadwal Perawatan (A-Check / C-Check)
    planned --> grounded: Pesawat Masuk Hanggar
    grounded --> in_work: Eksekusi Pekerjaan MRO
    in_work --> inspection: Uji Kelaikan & NDT
    inspection --> released_to_service: Lolos Uji Sertifikat Terbit
    inspection --> deferred: MEL Deferral Disetujui
    deferred --> released_to_service: Batas Deferral Aktif
    released_to_service --> closed: Rekonsiliasi Jam & Tutup
    closed --> [*]
```

---

## 3. Diagram Alur Utama & Saga Kompensasi (D2)

### 3.1 Alur Reservasi Penumpang (`PassengerBookingSaga`)
```mermaid
flowchart TD
    P[Calon Penumpang] -->|Pilih Rute & Jadwal| Web[Booking UI / API]
    Web -->|Mulai Saga| Saga[PassengerBookingSaga]
    Saga -->|1. Hold Kursi TTL 15 Menit| Inv[SeatInventoryReserver G7-K26]
    
    Inv -->|Kapasitas Habis| Fail1[Gagal: Kapasitas Penuh]
    Inv -->|Hold Sukses| KYC[2. Party KYC Adapter]
    
    KYC -->|Terkena Sanksi / Invalid| Comp1[Kompensasi: Release Hold]
    Comp1 --> Fail2[Ditolak Otoritas Keamanan]
    KYC -->|Identitas Lolos| Pay[3. Payment Adapter]
    
    Pay -->|Pembayaran Gagal| Comp2[Kompensasi: Release Hold]
    Comp2 --> Fail3[Transaksi Batal]
    Pay -->|Pembayaran Sukses| Confirm[4. Konfirmasi Tiket]
    Confirm --> Ledger[5. Jurnal Unearned Revenue Ledger]
    Ledger --> Success[Tiket Terbit & PNR Aktif]
```

### 3.2 Alur Penerimaan Kargo Udara & Pemeriksaan DG (`AirCargoAcceptanceSaga`)
```mermaid
flowchart TD
    Shipper[Cargo Agent / Shipper] -->|Ajukan Paket Kargo| CargoAction[AirCargoAcceptanceAction]
    CargoAction -->|1. Validasi DGR| DGCheck[DangerousGoodsEligibilityChecker A5]
    
    DGCheck -->|Melebihi Batas / Mode Terlarang| RejectDG[Ditolak: Pelanggaran DG]
    DGCheck -->|Sertifikat Lolos| Custody[2. Logistics CustodyVerifier]
    
    Custody -->|Hash Rantai Terverifikasi| LedgerCargo[3. Jurnal Piutang Kargo Ledger]
    LedgerCargo --> Manifest[Kargo Diterima & Masuk Manifest ULD]
```

---

## 4. Entity-Relationship Diagram (D3)

```mermaid
erDiagram
    avi_operators ||--o{ avi_aircraft : owns
    avi_operators ||--o{ avi_slots : holds
    avi_airports ||--o{ avi_routes : origin
    avi_airports ||--o{ avi_routes : destination
    avi_airports ||--o{ avi_slots : coordinates
    avi_aircraft ||--o{ avi_flights : assigned
    avi_aircraft ||--o{ avi_maintenance_visits : undergoes
    avi_routes ||--o{ avi_flights : defines
    avi_flights ||--o{ avi_seat_inventories : manages
    avi_flights ||--o{ avi_tickets : issues
    avi_flights ||--o{ avi_cargo_consignments : carries
    avi_flights ||--o{ avi_weight_balance_records : documents
    avi_tickets ||--o{ avi_refunds : settles
    avi_tickets ||--o{ avi_ticket_transitions : logs
    avi_flights ||--o{ avi_flight_transitions : logs
    avi_maintenance_visits ||--o{ avi_maintenance_transitions : logs

    avi_operators {
        bigint id PK
        string code UK
        string name
        string country
    }

    avi_airports {
        bigint id PK
        string iata_code UK
        string icao_code UK
        string name
        string city
        integer hourly_capacity
        boolean slot_coordinated
    }

    avi_aircraft {
        bigint id PK
        string registration_mark UK
        string icao_type
        integer cabin_y_capacity
        integer cabin_j_capacity
        integer mtow_kg
        integer oew_kg
        date airworthiness_expires_at
        integer a_check_due_minutes
        integer a_check_current_minutes
    }

    avi_flights {
        bigint id PK
        string flight_number
        datetime departure_time
        datetime arrival_time
        string status
        integer fuel_burn_kg
    }

    avi_tickets {
        bigint id PK
        string ticket_number UK
        string cabin
        integer base_fare_minor
        integer tax_minor
        string status
        string pii_blind_index
    }
```

---

## 5. Flowchart Siklus Penjurnalan Buku Besar (D4)

```mermaid
flowchart TD
    StartTicket[Tiket Dipesan & Dibayar] --> JurnalBayar["Debit: bank:clearing:payment:IDR<br/>Kredit: avi:passenger_unearned:IDR"]
    JurnalBayar --> FlightDeparted{Pesawat Berangkat & Mendarat?}
    FlightDeparted -->|Ya: Flown| JurnalRevenue["Debit: avi:passenger_unearned:IDR<br/>Kredit: avi:ticket_revenue:IDR<br/>Kredit: avi:tax_payable:IDR"]
    FlightDeparted -->|Tidak: Dibatalkan/Refund| HitungRefund[A4 FareRefundCalculator]
    HitungRefund --> JurnalRefund["Debit: avi:passenger_unearned:IDR<br/>Kredit: avi:refund_payable:IDR"]
    
    CargoAccept[Kargo Udara Diterima & Diberangkatkan] --> JurnalCargo["Debit: avi:cargo_receivable:IDR<br/>Kredit: avi:cargo_revenue:IDR"]
    
    MaintComplete[Pekerjaan Perawatan Selesai MRO] --> JurnalMaint["Debit: avi:maintenance_expense:IDR<br/>Kredit: avi:maintenance_payable:IDR"]
```

---

## 6. Flowchart Konteks Lintas Modul & Event Spine (D5)

```mermaid
flowchart LR
    Aviation["Modul Aviation (avi_)"]
    
    subgraph Providers ["Modul Penyedia"]
        Asset["Asset (ast_)"]
        Logistics["Logistics (lgx_)"]
        Party["Party (pty_)"]
        Hcm["Hcm (hcm_)"]
        Insurance["Insurance (ins_)"]
        Hotel["Hotel (htl_)"]
    end

    Aviation -->|"K9: MaintenanceWorkOrderRequester"| Asset
    Aviation -->|"CustodyVerifier & ShipmentBooking"| Logistics
    Aviation -->|"KYC & Sanctions Query"| Party
    Aviation -->|"EmployeeDirectory (Pilot/Crew)"| Hcm
    Aviation -->|"RoomBlockReserver (IRROPS Accommodation)"| Hotel
    Aviation -->|"EventCoverBinder (Flight Insurance)"| Insurance
    
    subgraph Consumers ["Pemakai Kontrak P7 (G7-K26)"]
        YieldMod["Fase 178: Yield & IRROPS"]
        TravelMod["Fase 113: Platform Perjalanan"]
    end
    
    YieldMod -->|"G7-K26: SeatInventoryReserver"| Aviation
    TravelMod -->|"G7-K26: SeatInventoryReserver"| Aviation
```

