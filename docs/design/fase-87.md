# Desain Arsitektur & Diagram — Fase 87: Rumah Sakit I

Dokumen ini memuat spesifikasi arsitektur teknis dan diagram Mermaid naratif untuk Fase 87.

## 1. State Diagrams (D1)

### 1.1 State Machine: Admission (8 Status)
```mermaid
stateDiagram-v2
    [*] --> requested: Pasien Mendaftar Rawat Inap
    requested --> bed_reserved: Alokasi Ranjang (B1)
    requested --> cancelled: Pasien Membatalkan
    bed_reserved --> admitted: Deposit Terverifikasi (Saga Sukses)
    bed_reserved --> cancelled: Deposit Gagal (Kompensasi Lepas Bed)
    admitted --> transferred: Pindah Kelas / Bangsal
    transferred --> admitted: Selesai Pindah Kamar
    admitted --> discharge_planned: Resume Medis Selesai
    discharge_planned --> discharged: Pelunasan / Pelepasan Deposit
    discharged --> readmitted: Re-admisi Mendadak (Komplikasi < 48 Jam)
    discharged --> [*]
    cancelled --> [*]
```

### 1.2 State Machine: Hospital Bed (7 Status)
```mermaid
stateDiagram-v2
    [*] --> available: Ranjang Siap Dipakai
    available --> reserved: Lock Alokasi Admisi
    available --> isolated_hold: Isolasi KLB / Siaga Bencana
    available --> maintenance: Perbaikan Fasilitas Medis
    reserved --> occupied: Pasien Check-in Kamar
    reserved --> available: Pembatalan / Kompensasi
    occupied --> cleaning: Pasien Discharge / Pindah
    cleaning --> available: Housekeeping Selesai & Disanitasi
    isolated_hold --> available: Pencabutan Siaga KLB
    maintenance --> available: Kalibrasi & Servis Lulus
    available --> out_of_service: Rusak Berat / Renovasi
    out_of_service --> maintenance: Masuk Bengkel Medis
```

### 1.3 State Machine: Clinical Order (7 Status)
```mermaid
stateDiagram-v2
    [*] --> ordered: Dokter Input Resep / Lab / Prosedur
    ordered --> scheduled: Slot Jadwal Dikonfirmasi
    ordered --> cancelled: Dokter Menarik Order
    scheduled --> in_progress: Tindakan / Pemeriksaan Dimulai
    scheduled --> delayed: Lewat Batas Toleransi (> 15 Menit)
    delayed --> in_progress: Dilanjutkan dengan Eskalasi
    delayed --> escalated: Butuh Tindakan Supervisor
    escalated --> in_progress: Diberi Prioritas
    in_progress --> completed: Hasil Selesai / Terlaksana
    completed --> [*]
    cancelled --> [*]
```

### 1.4 State Machine: Code Blue Event (7 Status)
```mermaid
stateDiagram-v2
    [*] --> detected: Algoritma B5 / Telemetri Kritis
    detected --> raised: Alarm Sistem & Notifikasi Broadcast
    raised --> acknowledged: Direspons Perawat Jaga (Menit 0-2)
    raised --> team_dispatched: Eskalasi Tim Resusitasi (Menit 2-5)
    acknowledged --> team_dispatched: Dokter Tiba di Lokasi
    team_dispatched --> resolved: Pasien Stabil / Teratasi
    team_dispatched --> escalated: Kegagalan Resusitasi / Pindah ICU
    escalated --> resolved: Tertangani di Ruang Khusus
    resolved --> closed: Review Medis & Tutup Hash Chain
    closed --> [*]
```

## 2. Sequence Diagram Alur Utama & Saga Kompensasi (D2)

```mermaid
sequenceDiagram
    autonumber
    actor Reg as Petugas Admisi
    participant Saga as InpatientAdmissionSaga
    participant Opt as BedAssignmentOptimizer
    participant BedRepo as HospitalBedRepository
    participant Escrow as Payment/EscrowService
    participant EMR as PatientEmrService
    participant Ledger as HospitalLedgerService

    Reg->>Saga: execute(patientId, requiredClass, priority)
    Saga->>Opt: calculateOptimalBed(requestList, availableBeds)
    Opt-->>Saga: BedAssignmentCertificate(Bed#101, class=K1, upgraded=false)
    Saga->>BedRepo: lockForUpdate(Bed#101) & reserve()
    BedRepo-->>Saga: reserved_ok
    
    rect rgb(240, 248, 255)
        Note over Saga,Escrow: Langkah 2: Tangkap Deposit Rawat Inap
        Saga->>Escrow: captureDeposit(amount=5.000.000 IDR)
        alt Deposit Berhasil
            Escrow-->>Saga: deposit_captured(receiptRef)
            Saga->>Ledger: postDeposit(cash Clearing -> hsp:patient_deposit_escrow)
            Saga->>BedRepo: markOccupied(Bed#101)
            Saga->>EMR: createAdmissionRecord(status=admitted)
            Saga-->>Reg: AdmissionSuccess(admissionNo)
        else Deposit Ditolak / Saldo Kurang
            Escrow-->>Saga: deposit_failed(insufficient_funds)
            Note over Saga,BedRepo: KOMPENSASI OTOMATIS: Lepas Kuncian Bed
            Saga->>BedRepo: releaseReservation(Bed#101, status=available)
            Saga->>EMR: cancelAdmission(reason=deposit_failure)
            Saga-->>Reg: AdmissionFailedWithCompensation(error)
        end
    end
```

## 3. Entity-Relationship Diagram (D3)

```mermaid
erDiagram
    HSP_PATIENTS ||--o{ HSP_ADMISSIONS : undergoes
    HSP_PATIENTS ||--o{ HSP_PATIENT_PASSPORT_EVENTS : chains
    HSP_BEDS ||--o{ HSP_ADMISSIONS : hosts
    HSP_BEDS ||--o{ HSP_BED_TRANSITIONS : records
    HSP_ADMISSIONS ||--o{ HSP_ADMISSION_TRANSITIONS : audits
    HSP_ADMISSIONS ||--o{ HSP_CLINICAL_ORDERS : contains
    HSP_CLINICAL_ORDERS ||--o{ HSP_CLINICAL_ORDER_TRANSITIONS : audits
    HSP_ADMISSIONS ||--o{ HSP_VITALS_TELEMETRIES : streams
    HSP_ADMISSIONS ||--o{ HSP_CODE_BLUE_EVENTS : triggers

    HSP_PATIENTS {
        bigint id PK
        string mrn UK
        string name
        date date_of_birth
        string blood_type
        string blind_index_nik
        json encrypted_allergies
        json encrypted_chronic_diagnoses
        string passport_hash
    }

    HSP_BEDS {
        bigint id PK
        string bed_code UK
        string ward_name
        string room_number
        string bed_class
        bigint rate_per_day_idr
        string status
        bigint current_encounter_id
    }

    HSP_ADMISSIONS {
        bigint id PK
        string admission_no UK
        bigint patient_id FK
        bigint bed_id FK
        string current_class
        string entitled_class
        boolean is_upgraded
        boolean upgrade_absorbed_by_hospital
        string status
        bigint deposit_idr
        timestamp admitted_at
        timestamp discharged_at
    }

    HSP_CLINICAL_ORDERS {
        bigint id PK
        string order_code UK
        bigint admission_id FK
        string order_type
        string description
        integer sequence_step
        timestamp scheduled_at
        string status
        boolean delay_alert_sent
    }

    HSP_CODE_BLUE_EVENTS {
        bigint id PK
        string event_no UK
        bigint admission_id FK
        bigint bed_id FK
        string status
        string escalation_level
        timestamp nurse_ack_at
        timestamp doctor_ack_at
        timestamp team_ack_at
        timestamp resolved_at
        string proof_hash
    }

    HSP_EXTERNAL_FACTORS {
        bigint id PK
        string factor_key
        string factor_name
        string city_code
        bigint factor_value
        date effective_from
        string source_ref
        string classification
    }
```

## 4. Flowchart Posting Ledger (D4)

```mermaid
flowchart TD
    A[Peristiwa Transaksi Rawat Inap] --> B{Jenis Transaksi}
    
    B -->|Deposit Awal Masuk| C[Posting Deposit Escrow]
    C --> C1[Debit: kas/clearing_pembayaran]
    C1 --> C2[Kredit: hsp:patient_deposit_escrow:IDR]
    
    B -->|Bed-Day Diakui Harian| D[Posting Pendapatan Rawat Inap]
    D --> D1[Debit: hsp:patient_receivable:IDR]
    D1 --> D2[Kredit: hsp:hospital_revenue:IDR]
    
    B -->|Koreksi / Hari Rawat Batal| E[Posting Pembalik Koreksi]
    E --> E1[Debit: hsp:hospital_revenue:IDR - Pembalik]
    E1 --> E2[Kredit: hsp:patient_receivable:IDR]
    
    B -->|Penyelesaian Saat Discharge| F[Pelepasan Deposit ke Tagihan]
    F --> F1[Debit: hsp:patient_deposit_escrow:IDR]
    F1 --> F2[Kredit: hsp:patient_receivable:IDR]
```

## 5. Flowchart Konteks Modul & Integrasi (D5)

```mermaid
flowchart LR
    PartyMod[Modul Party / KYC] -->|Identitas Terverifikasi| HospPatient[HSP Patients & Passport]
    AssetMod[Modul Asset] -->|Status Kalibrasi Alkes & Bed| HospBed[HSP Bed Management]
    HcmMod[Modul HCM] -->|Roster Dokter & Perawat| HospCare[Clinical Pathway & Care]
    TwinMod[Core Digital Twin] -->|Peta Bangsal & Spasial| HospBoard[Papan Bed & IGD 375px]
    
    HospPatient --> HospAdmission[Admisi Rawat Inap Saga]
    HospBed --> HospAdmission
    HospAdmission --> HospOrders[Clinical Pathway Orders]
    HospAdmission --> HospTelemetry[IoT Critical Care & Code Blue]
    
    HospAdmission -->|EncounterBillReader| InsMod[Modul Insurance Cashless]
    HospTelemetry -->|MedicalEmergencyResponseDispatcher| VenueMod[Modul Venue Emergency]
    HospAdmission -->|Posting Akuntansi| LedgerMod[Sistem Buku Besar / Ledger]
```


## 6. Sequence Diagram Alur Pertukaran FHIR SatuSehat Kemenkes RI (D6)

```mermaid
sequenceDiagram
    autonumber
    participant Simrs as SIMRS Hospital Module
    participant Mapper as SatuSehatFHIRMappingService
    participant Validator as ValidateSatuSehatFHIRComplianceAction
    participant Dto as FHIRBundlePayloadDTO
    participant Outbox as HSP FHIR Outbox Queue
    participant Kemkes as SatuSehat Kemenkes Gateway

    Simrs->>Mapper: mapEncounter(Admission $admission)
    Mapper->>Mapper: Konversi status, identifier IHS, periode rawat
    Simrs->>Mapper: mapNews2Observation(Vitals, News2Score)
    Mapper->>Mapper: Format LOINC 96552-5 & komponen vital
    Simrs->>Mapper: mapCondition(ICD10Code)
    Mapper->>Dto: createBundle(bundle_id, [Encounter, Observation, Condition])
    Dto-->>Validator: Validasi kepatuhan sintaksis R4
    alt Validasi Berhasil
        Validator-->>Outbox: Enqueue FHIR Bundle Transaction
        Outbox->>Kemkes: HTTP POST /fhir-r4/v1/Bundle (OAuth2 Bearer)
        Kemkes-->>Outbox: HTTP 200 OK (Resource IDs Committed)
        Outbox-->>Simrs: Update status sinkronisasi SATUSEHAT_SYNCED
    else Validasi Gagal
        Validator-->>Simrs: Alert format tidak patuh (HSP-ERR-017)
    end
```

## 7. Sequence Diagram Rekonstruksi Forensik Code Blue Replay (D7)

```mermaid
sequenceDiagram
    autonumber
    participant Nurse as Perawat / Sensor Telemetri
    participant Planner as CodeBlueEscalationPlanner
    participant Event as CodeBlueEvent Record
    participant Rrt as Tim Resusitasi / ACLS
    participant Replay as CriticalIncidentReplayEngine
    participant Passport as PatientPassportService

    Nurse->>Planner: Input Pemburukan Vital (SpO2 < 85% menit ke-1 & 2)
    Planner->>Event: Pemicu Alarm Code Blue (Menit 0)
    Event-->>Nurse: Banner Merah & Sirine Bangsal
    Note over Nurse, Event: Jika belum di-ack hingga Menit 2
    Planner->>Event: Eskalasi Panggilan Dokter Jaga (Menit 2)
    Note over Event, Rrt: Jika belum resolusi hingga Menit 5
    Planner->>Rrt: Broadcast Sirine Sentral Tim Resusitasi (Menit 5)
    Rrt->>Event: Resusitasi ACLS Selesai (ROSC Tercapai)
    Event->>Replay: Eksekusi Timeline Audit Replay
    Replay->>Replay: Validasi Rantai Hash Merkle H0..Hn
    Replay->>Passport: Rekam Sertifikat CIRE-V1 ke Paspor Pasien
    Passport-->>Nurse: Bukti Audit Terkunci Permanen
```

## 8. Matriks Hak Akses Peran & Kapabilitas Layanan (RBAC Matrix)

| Endpoint / Layanan Domain | `hospital_registration` | `bed_manager` | `nurse` | `doctor` | `rs_admin` |
| :--- | :---: | :---: | :---: | :---: | :---: |
| `/hospital/beds` (Papan Kontrol Ranjang) | Read-Only | **Read & Write** | Read-Only | Read-Only | **Read & Write** |
| `/hospital/er` (Papan Triase & IGD) | Read-Only | Read-Only | **Read & Write** | **Read & Write** | Read-Only |
| `/hospital/nursing` (Terminal Bangsal 375px) | Ditolak (403) | Ditolak (403) | **Read & Write** | Read-Only | Read-Only |
| `/hospital/critical-care` (Monitor ICU & Code Blue) | Ditolak (403) | Read-Only | **Read & Write** | **Read & Write** | Read-Only |
| `/hospital/efficiency` (Analisis Barber-Johnson) | Ditolak (403) | Read-Only | Ditolak (403) | Ditolak (403) | **Read & Write** |
| `/hospital/patients/{id}/passport` (Paspor Medis) | Read-Only | Ditolak (403) | Read-Only | **Read & Write** | Audit Log |
| `InpatientAdmissionSaga` (Admisi Rawat Inap) | Inisiasi | **Eksekusi** | Ditolak (403) | Ditolak (403) | **Eksekusi** |
| `BedDischargeAndCleaningSaga` (Pemulangan & Cuci) | Ditolak (403) | **Eksekusi** | Konfirmasi Cuci | Rekomendasi KRS | **Eksekusi** |
| `AdjustCapacityForEpidemicAction` (Kontingensi KLB) | Ditolak (403) | Ditolak (403) | Ditolak (403) | Ditolak (403) | **Eksekusi** |
| `hsp:audit` (Audit Dua Sumber & CLI) | Ditolak | Ditolak | Ditolak | Ditolak | **Eksekusi** |
