# Desain Arsitektur — Fase 89: Ticketing, Access Control, Usia & Venue Operations

**Domain:** Venue & Entertainment (`modules/Venue/`)  
**Basis Konsep:** KONSEP 10F, Lini 15; Aturan Rekayasa G7 & Standar Tambahan Ketat W8  

---

## 1. D1: Diagram Status (State Machine)

### a. Event State Machine (8 Status)
```mermaid
stateDiagram-v2
    [*] --> draft
    draft --> permit_pending : ajukan_izin
    permit_pending --> on_sale : izin_disetujui
    permit_pending --> cancelled : izin_ditolak
    on_sale --> sold_out : kuota_penuh
    on_sale --> live : pintu_dibuka
    sold_out --> live : pintu_dibuka
    live --> closed : acara_berakhir
    closed --> settled : rekonsiliasi_selesai
    on_sale --> cancelled : force_majeure
    live --> cancelled : evakuasi_darurat
    settled --> [*]
    cancelled --> [*]
```

Transisi dan Guard Event SM:
1. `draft` $\to$ `permit_pending`: Diajukan manajer acara dengan lampiran dokumen kapasitas legal.
2. `permit_pending` $\to$ `on_sale`: Guard memverifikasi surat izin keramaian gapless berstatus `APPROVED` dan kapasitas legal $\ge$ total kuota tier tiket.
3. `on_sale` $\to$ `sold_out`: Guard memeriksa sisa tiket pada seluruh tier $= 0$.
4. `on_sale` / `sold_out` $\to$ `live`: Waktu sistem mencapai `doors_open_at` dan pos gerbang dibuka.
5. `live` $\to$ `closed`: Manajer acara atau trigger terjadwal menutup event. Memindahkan saldo `ven:ticket_unearned:IDR` ke `ven:ticket_revenue:IDR`.
6. `closed` $\to$ `settled`: Seluruh transaksi F&B, meja VIP, dan klaim asuransi telah terekonsiliasi penuh via `AuditVenueCommand`.
7. Transisi `cancelled`: Dipicu oleh pencabutan izin polisi atau cuaca ekstrem badai laut; memicu saga kompensasi penuh.

### b. Ticket State Machine (8 Status)
```mermaid
stateDiagram-v2
    [*] --> reserved
    reserved --> issued : pembayaran_sukses
    reserved --> void : hold_kedaluwarsa
    issued --> transferred : transfer_sekali
    issued --> scanned_in : pindaian_masuk
    transferred --> scanned_in : pindaian_masuk
    scanned_in --> scanned_out : re-entry_keluar
    scanned_out --> scanned_in : re-entry_masuk
    scanned_in --> used : event_selesai
    issued --> refunded : event_dibatalkan
    void --> [*]
    used --> [*]
    refunded --> [*]
```

Guard Ticket SM:
1. `reserved` $\to$ `issued`: Pembayaran terkonfirmasi; nomor urut gapless dan hash SHA-256 diterbitkan.
2. `issued` $\to$ `transferred`: Tiket lama dibekukan, tiket baru diterbitkan ke pembeli baru. Maksimal 1 kali transfer seumur hidup tiket.
3. `issued`/`transferred` $\to$ `scanned_in`: Pindaian gerbang arah masuk. Guard memverifikasi usia $\ge 21$ tahun, anti-passback valid, dan zona belum penuh.
4. `scanned_in` $\to$ `scanned_out`: Pindaian gerbang arah keluar. Mengurangi okupansi zona fisik.
5. `scanned_out` $\to$ `scanned_in`: Masuk kembali (*re-entry*). Guard memastikan `reentry_count <= 2`.
6. `scanned_in` $\to$ `used`: Event mencapai status `closed`.
7. `issued` $\to$ `refunded`: Pembatalan event via saga kompensasi.

### c. Table Booking State Machine (7 Status)
```mermaid
stateDiagram-v2
    [*] --> requested
    requested --> deposit_held : hold_payment_intent
    requested --> cancelled_refunded : payment_gagal
    deposit_held --> confirmed : konfirmasi_otomatis
    confirmed --> seated : tamu_tiba
    confirmed --> no_show_forfeited : batas_waktu_lewat
    seated --> settled : tagihan_selesai
    confirmed --> cancelled_refunded : pembatalan_event
    settled --> [*]
    no_show_forfeited --> [*]
    cancelled_refunded --> [*]
```

Guard Table Booking SM:
1. `requested` $\to$ `deposit_held`: PaymentIntent berhasil mengunci deposit di kartu/rekening tamu.
2. `deposit_held` $\to$ `confirmed`: Nomor meja dialokasikan ke tamu secara permanen.
3. `confirmed` $\to$ `seated`: Tamu hadir di meja VIP sebelum batas toleransi waktu (pukul 23:00 WITA).
4. `confirmed` $\to$ `no_show_forfeited`: Tamu tidak hadir hingga batas waktu; algoritma V4 mengeksekusi forfeiture $50\%$.
5. `seated` $\to$ `settled`: Pesanan konsumsi POS dihitung, shortfall diakui, dan tagihan lunas.

---

## 2. D2: Diagram Sekuens Alur Utama & Saga Kompensasi

### a. Alur Pembelian Tiket & Pindaian Gerbang
```mermaid
flowchart TD
    Tamu["Tamu / Customer"] -->|Beli Tiket Tier GA| Portal["Guest Portal / Web"]
    Portal -->|execute| Action["IssueTicketAction"]
    Action -->|calculate| Pricer["V1 TierCountdownPricer"]
    Pricer -->|dynamicPriceIdr| Action
    Action -->|generateNext| Numbering["DocumentNumbering TCK-VEN"]
    Numbering -->|ticketNumber| Action
    Action -->|computeHash| Chain["CanonicalHashChain"]
    Chain -->|ticketHash| Action
    Action -->|post unearned| Ledger["LedgerService"]
    Action -->|EntertainmentTicket issued| Portal

    Staff["Petugas Gerbang 375px"] -->|Scan QR Direction IN| Gate["ScanGateAction"]
    Gate -->|verifyAge 21+| KYC["PartyAgeVerification"]
    KYC -->|Pass| Gate
    Gate -->|evaluateScan| APB["V2 AntiPassbackGate"]
    APB -->|ALLOWED| Gate
    Gate -->|Indikator Hijau Besar| Staff
```

### b. Alur Reservasi Meja VIP & Penagihan Minimum Spend (Saga)
```mermaid
flowchart TD
    VIP["Tamu VIP"] -->|Booking Meja VIP| App["Portal Meja"]
    App -->|startBooking| Saga["TableBookingSaga"]
    Saga -->|holdDeposit Rp 2jt| Pay["PaymentIntentGateway"]
    Pay -->|Hold Confirmed| Saga
    Saga -->|Debit Escrow, Kredit Unearned| Ledger["LedgerService"]
    Saga -->|Status confirmed| App

    VIP -->|Pesan Botol Spirits & Makanan| Resto["G7-K17 OutletSaleRecorder"]
    Resto -->|Total Konsumsi Rp 3.2jt| Saga
    Saga -->|settle minimum spend 5jt| Alg["V4 MinimumSpendSettlement"]
    Alg -->|SettlementResult| Saga
    Saga -->|charge additional Rp 3jt| Pay
    Saga -->|Debit Unearned, Kredit Revenue| Ledger
    Saga -->|Status settled| App
```

### c. Saga Pembatalan Event & Kompensasi Terdistribusi
```mermaid
flowchart TD
    Mgr["Venue Manager"] -->|triggerCancellation Cuaca Ekstrem| Saga["EventCancellationSaga"]
    Saga -->|update status CANCELLED| Evt["EntertainmentEvent"]
    Saga -->|refundTicket per tiket| Tck["TicketRefundAction"]
    Tck -->|Balik Unearned Revenue & Tax Payable| Ledger["LedgerService"]
    Saga -->|refundDeposit per meja| Tbl["TableRefundAction"]
    Tbl -->|Rilis VIP Escrow kembali ke Tamu| Ledger
    Saga -->|claimCancellation| Ins["G7-K15 EventCoverBinder"]
    Ins -->|ClaimId| Saga
    Saga -->|Saga Selesai| Mgr
```

---

## 3. D3: Entity-Relationship Diagram (ERD) Fase 89

```mermaid
erDiagram
    ven_entertainment_venues ||--o{ ven_entertainment_zones : memiliki
    ven_entertainment_venues ||--o{ ven_entertainment_events : menyelenggarakan
    ven_entertainment_events ||--o{ ven_ticket_tiers : membagi
    ven_entertainment_events ||--o{ ven_entertainment_tickets : menerbitkan
    ven_entertainment_events ||--o{ ven_entertainment_table_bookings : memesan
    ven_entertainment_events ||--o{ ven_permits : memiliki
    ven_ticket_tiers ||--o{ ven_entertainment_tickets : menggolongkan
    ven_entertainment_tickets ||--o{ ven_gate_scans : riwayat_pindaian

    ven_entertainment_venues {
        bigint id PK
        string venue_code UK
        string name
        string venue_type
        string city
        int max_legal_capacity
        int min_age_requirement
    }

    ven_entertainment_zones {
        bigint id PK
        string zone_code UK
        bigint venue_id FK
        string name
        int capacity_limit
        int current_occupancy
        boolean crowd_lockdown_active
    }

    ven_entertainment_events {
        bigint id PK
        string event_code UK
        bigint venue_id FK
        string title
        datetime doors_open_at
        bigint ticket_floor_price_idr
        bigint ticket_ceiling_price_idr
        bigint current_ticket_price_idr
        string status
    }

    ven_entertainment_tickets {
        bigint id PK
        string ticket_number UK
        string ticket_uuid UK
        bigint event_id FK
        bigint tier_id FK
        bigint buyer_user_id FK
        bigint price_paid_idr
        bigint tax_paid_idr
        string ticket_hash
        string prev_hash
        string status
        int reentry_count
        string last_direction
        timestamp scanned_at
        string idempotency_key UK
    }

    ven_entertainment_table_bookings {
        bigint id PK
        string booking_code UK
        bigint event_id FK
        string table_number
        bigint user_id FK
        bigint minimum_spend_idr
        bigint deposit_amount_idr
        bigint actual_spend_idr
        bigint shortfall_idr
        bigint forfeited_deposit_idr
        string status
        string idempotency_key UK
    }

    ven_permits {
        bigint id PK
        string permit_number UK
        bigint event_id FK
        string issuing_authority
        int max_legal_capacity
        timestamp valid_from
        timestamp valid_until
        string status
    }
```

---

## 4. D4: Diagram Alur Posting Ledger Double-Entry

```mermaid
flowchart TD
    subgraph Penjualan_Tiket["1. Penjualan Tiket"]
        D1["Debit: ven:ticket_clearing:IDR"] --> C1["Kredit: ven:ticket_unearned:IDR"]
        D1 --> C2["Kredit: ven:entertainment_tax_payable:IDR"]
    end

    subgraph Penutupan_Event["2. Event Selesai (Closed)"]
        D2["Debit: ven:ticket_unearned:IDR"] --> C3["Kredit: ven:ticket_revenue:IDR"]
    end

    subgraph Deposit_Meja["3. Hold Deposit Meja"]
        D3["Debit: ven:vip_escrow:IDR"] --> C4["Kredit: ven:vip_unearned_rev:IDR"]
    end

    subgraph Penyelesaian_Meja["4. Capture / Settle Meja"]
        D4["Debit: ven:vip_unearned_rev:IDR"] --> C5["Kredit: ven:table_revenue:IDR"]
    end

    subgraph No_Show_Meja["5. No-Show Forfeiture (50%)"]
        D5["Debit: ven:vip_unearned_rev:IDR"] --> C6["Kredit: ven:vip_noshow_rev:IDR"]
    end

    subgraph Asuransi_Event["6. Premi Asuransi Event (G7-K15)"]
        D6["Debit: ven:event_insurance_expense:IDR"] --> C7["Kredit: ven:organizer_payable:IDR"]
    end
```

---

## 5. D5: Diagram Konteks Modul & Kontrak

```mermaid
flowchart LR
    subgraph Modul_Venue["Modul Venue (ven_)"]
        GateController["Gate Scan (375px)"]
        EventService["Event & Ticketing Service"]
        TableService["VIP Table Service"]
        PricerEngine["V1 TierCountdownPricer"]
        APBEngine["V2 AntiPassbackGate"]
        GovEngine["V3 ZoneCapacityGovernor"]
        MinSpend["V4 MinimumSpendSettlement"]
    end

    subgraph Modul_Eksternal["Modul Lain / Kontrak G7"]
        Insurance["Modules\\Insurance (G7-K15 EventCoverBinder)"]
        Resto["Modules\\Resto (G7-K17 OutletSaleRecorder)"]
        Party["Modules\\Party (PartyAgeVerificationInterface)"]
        Banking["Modules\\Banking (LedgerService)"]
        Core["Modules\\Core (Clock, DocumentNumbering)"]
    end

    EventService --> Core
    EventService --> Banking
    EventService --> Insurance
    GateController --> APBEngine
    GateController --> Party
    GateController --> GovEngine
    TableService --> Resto
    TableService --> MinSpend
    TableService --> Banking
```
