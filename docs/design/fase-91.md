# Desain Teknis & Arsitektur — Fase 91: PMS, Central Reservation, Rate Management & Smart Room

**Modul Pemilik:** Hotel (`htl_`)  
**Status Eksekusi:** [🔵] Siap Verifikasi  
**Dokumen Pendukung:** `docs/dor/fase-91.md`, `docs/konsep/hotel.md`  

---

## 1. Diagram State Machine (D1)

### 1.1 State Machine Reservasi Tamu (9 Status)
```mermaid
stateDiagram-v2
    [*] --> inquiry
    inquiry --> tentative: hold inventori
    tentative --> confirmed: pembayaran deposit / garansi
    confirmed --> reconfirmed: konfirmasi H-1 / kanal
    reconfirmed --> checked_in: check-in fisik / digital
    confirmed --> checked_in: direct check-in
    reconfirmed --> walked: relokasi overbooking saga
    confirmed --> walked: relokasi overbooking saga
    confirmed --> cancelled: pembatalan sesuai kebijakan
    reconfirmed --> cancelled: pembatalan force majeure
    reconfirmed --> no_show: lewat batas night audit
    checked_in --> checked_out: checkout saldo 0
    checked_out --> [*]
    walked --> [*]
    cancelled --> [*]
    no_show --> [*]
```

### 1.2 State Machine Kunci Digital (7 Status)
```mermaid
stateDiagram-v2
    [*] --> pending_activation
    pending_activation --> issued: check-in berhasil
    issued --> active: tap pertama pintu
    active --> suspended: freeze keamanan / checkout tertunda
    suspended --> active: reaktivasi front desk
    active --> expired: lewat waktu checkout
    active --> revoked: checkout dini / kartu hilang
    suspended --> revoked: pembatalan paksa
    expired --> reissued: perpanjangan late checkout
    revoked --> reissued: penerbitan ulang kunci baru
    reissued --> active: tap kunci baru
    expired --> [*]
    revoked --> [*]
```

### 1.3 State Machine Laporan APOA UU 6/2011 (7 Status)
```mermaid
stateDiagram-v2
    [*] --> pending: check-in tamu WNA
    pending --> submitted: kirim batch ke imigrasi S1
    submitted --> acknowledged: tanda terima resmi APOA
    pending --> late: lewat tenggat 24 jam
    late --> escalated: notifikasi darurat GM hotel
    escalated --> submitted: pelaporan darurat
    submitted --> corrected: pembetulan data paspor/visa
    corrected --> acknowledged: pengesahan revisi
    acknowledged --> closed: arsip kepatuhan
    closed --> [*]
```

---

## 2. Diagram Alur Utama & Saga Kompensasi (D2)

### Relokasi Overbooking Berjalan Sebagai Saga Terdistribusi
```mermaid
sequenceDiagram
    autonumber
    participant CRS as Central Reservation
    participant SAGA as OverbookingWalkSaga
    participant NGH as Properti Tetangga
    participant LOG as Logistics (ShipmentBooking)
    participant FOL as Guest Folio
    participant ACC as General Ledger

    CRS->>SAGA: Mulai relokasi tamu ter-overbooked
    SAGA->>NGH: Request hold kamar setara/lebih tinggi
    alt Kamar Tetangga Tersedia
        NGH-->>SAGA: Konfirmasi kamar (Property B)
        SAGA->>LOG: Pesan shuttle VIP / armada antar tamu
        SAGA->>FOL: Terbitkan voucher kompensasi (H5)
        SAGA->>ACC: Jurnal beban overbooking & kewajiban walk
        SAGA-->>CRS: Relokasi sukses (Status: WALKED)
    else Kamar Tetangga Penuh / Gagal
        NGH-->>SAGA: Gagal (No Capacity)
        SAGA->>SAGA: Rollback / Coba properti berikutnya
        SAGA-->>CRS: Eskalasi Manager On Duty (Duty Manager Alert)
    end
```

---

## 3. Diagram Entity-Relationship Database (D3)

```mermaid
erDiagram
    HTL_PROPERTIES ||--o{ HTL_ROOM_TYPES : owns
    HTL_ROOM_TYPES ||--o{ HTL_ROOMS : contains
    HTL_ROOMS ||--o{ HTL_ROOM_NIGHTS : allocates
    HTL_PROPERTIES ||--o{ HTL_RESERVATIONS : books
    HTL_RESERVATIONS ||--o{ HTL_DIGITAL_KEYS : issues
    HTL_RESERVATIONS ||--o{ HTL_APOA_REPORTS : reports
    HTL_ROOMS ||--o{ HTL_HOUSEKEEPING_TASKS : assigns
    HTL_PROPERTIES ||--o{ HTL_RATE_PLAN_VERSIONS : configures

    HTL_RESERVATIONS {
        bigint id PK
        string reservation_code UK
        string status
        int total_amount_idr
        string channel
    }
    HTL_DIGITAL_KEYS {
        bigint id PK
        bigint reservation_id FK
        string session_token_hash
        string status
        timestamp valid_until
    }
    HTL_APOA_REPORTS {
        bigint id PK
        bigint reservation_id FK
        string passport_blind_index
        string status
        timestamp deadline_at
    }
```

---

## 4. Alur Posting Ledger Seimbang (D4)

```mermaid
flowchart TD
    NA[Night Audit Harian] -->|Room Charge| P1[Posting 1]
    P1 -->|Debit| GL[htl:guest_ledger:IDR]
    P1 -->|Kredit| RR[htl:room_revenue:IDR]
    P1 -->|Kredit| TX[htl:tax_payable:IDR]
    P1 -->|Kredit| SC[htl:service_charge_payable:IDR]

    SR[Gangguan Kamar / SLA] -->|Kompensasi H5| P2[Posting 2]
    P2 -->|Debit| CR[htl:service_recovery_contra_revenue:IDR]
    P2 -->|Kredit| GL

    WK[Overbooking Walk] -->|Relokasi Tamu| P3[Posting 3]
    P3 -->|Debit| OE[htl:overbooking_expense:IDR]
    P3 -->|Kredit| WP[htl:walk_payable:IDR]
```

---

## 5. Konteks Integrasi Lintas Modul (D5)

```mermaid
flowchart LR
    Hotel -->|Asset Work Order| G7_K9[Asset G7-K9 Requester]
    Hotel -->|Meter Interval kWh| G7_K22[Egy G7-K22 Reader]
    Hotel -->|Emission Benefit| Esg[Esg EmissionBenefitRecorder]
    Hotel -->|Digital Twin State| CoreTwin[Core DigitalTwinInterface]
    Hotel -->|Identity & Sanction| Party[Party Sanctions & Age]
```
