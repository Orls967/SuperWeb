# Arsitektur Integrasi Sistem & Diagram Urutan Layanan Terpadu

Dokumen ini memvisualisasikan arsitektur integrasi lintas modul dan alur interaksi antarkomponen perangkat lunak pada implementasi Telemedicine, E-Pharmacy, dan Jaringan Apotek Fase 104.

---

## 1. Diagram Arsitektur Komponen

Arsitektur sistem dibangun di atas arsitektur berlapis (Clean Architecture / Domain-Driven Design) dengan pemisahan tanggung jawab yang terstruktur:

```mermaid
flowchart TD
    subgraph ClientLayer["Lapisan Klien & Gateway"]
        WebPortal["Portal Web Dokter & Pasien"]
        CourierApp["Aplikasi Bergerak Kurir IoT"]
        BpjsGw["Gateway SISRUTE / PCare BPJS"]
    end

    subgraph AppServiceLayer["Lapisan Aplikasi (Actions & Contracts)"]
        K30["G7-K30: PatientReferralBooker"]
        BookTele["BookTeleconsultationAction"]
        IssueRx["IssueElectronicPrescriptionAction"]
        DispatchDelivery["DispatchColdChainCourierAction"]
        PodAction["CompleteElectronicPodAction"]
        CronRefill["ExecuteBatchRefillRunAction"]
    end

    subgraph DomainServiceLayer["Lapisan Domain (Rules & Calculations)"]
        CDSE["ClinicalDecisionSupportRuleEngine"]
        SigVerifier["DigitalPrescriptionSignatureVerifier"]
        DDIMatrix["DrugInteractionMatrixService"]
        FEFOEngine["FefoPharmacyDispensingEngine"]
        ColdChainTracker["ColdChainMedicineDeliveryTracker"]
        StabilityMKT["ThermosensitiveMedicineStabilityEngine"]
        AdherenceAnalyzer["ChronicMedicationAdherenceAnalyzer"]
    end

    subgraph InfrastructureLayer["Lapisan Infrastruktur & Persistensi"]
        DB[(Database Relasional)]
        Ledger[(Buku Besar Keuangan ERP)]
        SensorIoT["Sensor Suhu Boks Pendingin"]
    end

    ClientLayer --> AppServiceLayer
    AppServiceLayer --> DomainServiceLayer
    DomainServiceLayer --> InfrastructureLayer
```

---

## 2. Diagram Urutan Alur Konsultasi, Peresepan & Pengiriman Obat

Alur interaksi berurutan saat pasien melakukan telekonsultasi hingga obat diserahkan:

```mermaid
sequenceDiagram
    autonumber
    actor Pasien as Pasien
    actor Dokter as Dokter Spesialis
    participant K30 as PatientReferralBookerService
    participant CDS as CDSEngine
    participant Apotek as Cabang Apotek Terpilih
    actor Kurir as Kurir Farmasi
    participant Sensor as Sensor IoT Dingin

    Pasien->>K30: Request Booking Telekonsultasi
    K30->>Dokter: Jadwalkan Sesi Video & Buka Rekam Medis
    Dokter->>Pasien: Lakukan Konsultasi Klinis & Anamnesis
    Dokter->>CDS: Submit Draf Resep (Obat, Dosis, Durasi)
    CDS->>CDS: Cek Alergi, DDI, Klirens Ginjal & Dosis Max
    CDS-->>Dokter: Status Disetujui (PrescriptionSafetyScore: SAFE)
    Dokter->>K30: Terbitkan Resep dengan Tanda Tangan Digital
    K30->>Apotek: Rute Geospasial ke Apotek Terdekat & Alokasi FEFO
    Apotek->>Apotek: Racik Obat & Verifikasi Double-Check Label
    Apotek->>Kurir: Serahkan Boks Pendingin (Suhu Awal 4.0°C)
    Kurir->>Pasien: Menuju Alamat Antar
    loop Selama Perjalanan
        Sensor->>Kurir: Kirim Telemetri Suhu Realtime (2°C - 8°C)
    end
    Kurir->>Pasien: Tiba di Lokasi & Minta Verifikasi NIK KTP
    Pasien->>Kurir: Tanda Tangan Digital e-POD
    Kurir->>K30: Selesaikan Pengantaran (Status: DELIVERED)
    K30->>Dokter: Notifikasi Obat Telah Diterima Pasien
```

---

## 3. Diagram Alur Rujukan Rawat Inap Darurat dari Telemedis

Ketika kondisi pasien memburuk selama telekonsultasi dan memerlukan rawat inap:

```mermaid
sequenceDiagram
    autonumber
    Dokter->>K30: bookInpatientFromReferral(teleConsultId, wardClass, ICD-10)
    K30->>K30: Cari Bed Tersedia (status: AVAILABLE) & Lock
    K30->>K30: Update Bed status = OCCUPIED
    K30->>K30: Buat Admission (admission_no, hospital_bed_id, ADMITTED)
    K30->>K30: Bentuk Encounter Baru Rawat Inap
    K30->>Dokter: Return Kode Admisi, Nomor Bed, & Petunjuk Rujukan
```
