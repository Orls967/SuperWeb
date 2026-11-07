# Desain Arsitektur & Spesifikasi Teknis Fase 91: PMS, CRS, Rate Management & Smart Room

Dokumen ini berisi spesifikasi arsitektur teknis, diagram relasi entitas, diagram alur status, dan diagram posting buku besar berpasangan untuk Fase 91.

---

## 1. Diagram Relasi Entitas Database (D3 ER-Diagram)

```mermaid
erDiagram
    htl_properties ||--o{ htl_rooms : contains
    htl_properties ||--o{ htl_room_types : categorizes
    htl_properties ||--o{ htl_daily_revenue_snapshots : audits
    htl_properties ||--o{ htl_business_dates : tracks
    htl_room_types ||--o{ htl_rooms : specifies
    htl_rooms ||--o{ htl_room_nights : schedules
    htl_rooms ||--o{ htl_housekeeping_tasks : cleans
    htl_rooms ||--o{ htl_digital_keys : unlocks
    htl_rooms ||--o{ htl_room_energy_logs : monitors
    htl_reservations ||--o{ htl_room_nights : locks
    htl_reservations ||--o{ htl_digital_keys : issues
    htl_reservations ||--o{ htl_apoa_reports : reports
    htl_reservations ||--o{ htl_folios : bills
    htl_folios ||--o{ htl_folio_lines : itemizes

    htl_digital_keys {
        uuid id PK
        string reservation_id FK
        string guest_user_id
        string room_id FK
        string status
        string session_token_hash
        text qr_payload_encrypted
        datetime valid_until
    }

    htl_apoa_reports {
        uuid id PK
        string reservation_id FK
        string passport_number
        string nationality
        date check_in_date
        date check_out_date
        string status
        boolean is_bali_pwa_paid
        string apoa_reference_number
    }

    htl_housekeeping_tasks {
        uuid id PK
        string room_id FK
        string assigned_to_user_id
        date task_date
        string task_type
        int priority
        string status
    }

    htl_room_energy_logs {
        uuid id PK
        string room_id FK
        boolean is_occupied
        boolean motion_detected
        string hvac_mode
        int energy_saved_wh
    }
```

---

## 2. Diagram Alur Status State Machine (D1)

### 2.1 State Machine Reservasi Tamu (ReservationStateMachine — 9 Status)
```mermaid
stateDiagram-v2
    [*] --> inquiry : Permintaan Awal
    inquiry --> tentative : Penawaran / Hold Sementara
    tentative --> confirmed : Pembayaran Deposit / Jaminan
    tentative --> cancelled : Melewati Batas Waktu Hold
    confirmed --> reconfirmed : Konfirmasi Ulang H-1
    confirmed --> cancelled : Pembatalan Mandiri Tamu
    reconfirmed --> checked_in : Registrasi Front Desk & Kunci
    confirmed --> checked_in : Check-In Langsung
    confirmed --> walked : Relokasi Overbooking (Saga Walk)
    reconfirmed --> walked : Relokasi Overbooking
    confirmed --> no_show : Tidak Hadir Hingga Night Audit
    reconfirmed --> no_show : Tidak Hadir Hingga Night Audit
    checked_in --> checked_out : Pelunasan Folio Rp 0 & Kunci Dicabut
    checked_out --> [*]
    cancelled --> [*]
    walked --> [*]
    no_show --> [*]
```

### 2.2 State Machine Kunci Digital Kamar (DigitalKeyStateMachine — 7 Status)
```mermaid
stateDiagram-v2
    [*] --> pending_activation : Diterbitkan Saat Booking
    pending_activation --> issued : Kunci Siap di Cloud
    issued --> active : Tamu Check-In & Verifikasi ID
    active --> suspended : Pintu Dikunci Supervisor / Tunggakan
    suspended --> active : Buka Kunci Kembali
    active --> expired : Melewati Batas Jam Check-Out (12:00)
    active --> revoked : Tamu Check-Out / Ganti Kunci
    revoked --> reissued : Kunci Pengganti Diterbitkan
    reissued --> active : Kunci Baru Aktif
    expired --> [*]
    revoked --> [*]
```

### 2.3 State Machine Pelaporan Orang Asing (ApoaReportStateMachine — 7 Status)
```mermaid
stateDiagram-v2
    [*] --> pending : Tamu WNA Check-In
    pending --> submitted : Dikirim ke Portal Imigrasi
    submitted --> acknowledged : Tanda Terima Resmi Terbit
    pending --> late : Melewati Batas 24 Jam
    late --> escalated : Notifikasi ke General Manager
    escalated --> corrected : Data Paspor Diperbaiki
    corrected --> submitted : Pengiriman Ulang
    acknowledged --> closed : Tamu Selesai Check-Out
    closed --> [*]
```

### 2.4 State Machine Tugas Tata Graha (HousekeepingTaskStateMachine — 7 Status)
```mermaid
stateDiagram-v2
    [*] --> assigned : Kamar Dirty / Stayover Dijadwalkan
    assigned --> in_progress : Petugas Mulai Membersihkan
    in_progress --> done : Pembersihan Kamar Selesai
    done --> inspected : Disetujui Floor Supervisor
    done --> failed_inspection : Ditolak / Masih Ada Kekurangan
    failed_inspection --> reassigned : Ditugaskan Ulang
    reassigned --> in_progress : Pembersihan Ulang
    inspected --> closed : Kamar Dirilis Siap Jual
    closed --> [*]
```

---

## 3. Diagram Alur Transaksi Finansial Buku Besar (D4 Flowchart Posting)

```mermaid
flowchart TD
    NA["Eksekusi Night Audit Harian"] --> CHECK["Validasi Kamar Checked-In"]
    CHECK --> CALC["Hitung: Tarif Dasar + Service 5% + PBJT 10%"]
    CALC --> POST["Penjurnalan Double-Entry Ledger"]
    POST --> D1["Debit: htl:guest_ledger:IDR (Total Piutang)"]
    POST --> C1["Kredit: htl:room_revenue:IDR (Pendapatan Dasar)"]
    POST --> C2["Kredit: htl:service_charge_payable:IDR (Hutang Service)"]
    POST --> C3["Kredit: htl:tax_payable:IDR (Hutang Pajak PBJT)"]
    POST --> SNAP["Simpan Snapshot Harian (htl_daily_revenue_snapshots)"]
    SNAP --> ADV["Majukan Tanggal Bisnis Properti (+1 Hari)"]
```

---

## 4. Diagram Konteks Integrasi Lintas Modul (D5)

```mermaid
flowchart LR
    CRS["Hotel CRS / PMS"] -->|KYC & Verifikasi Umur| PTY["Modul Party"]
    CRS -->|Aset Fisik Kamar| AST["Modul Asset (G7-K9)"]
    CRS -->|Shuttle Relokasi Walk| LGX["Modul Logistics (ShipmentBooking)"]
    CRS -->|Kembar Digital Suhu Kamar| TWN["Modul Core (DigitalTwin)"]
    CRS -->|Metrik Penghematan Listrik AC| ESG["Modul Esg (EmissionBenefit)"]
    CRS -->|Penjurnalan Sewa Kamar & Deposit| BNK["Modul Banking (Double-Entry Ledger)"]
```
