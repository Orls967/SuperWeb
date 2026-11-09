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
flowchart TD
    P1["1. Pasien Request Booking Telekonsultasi"] --> P2["2. Jadwalkan Sesi Video & Buka Rekam Medis"]
    P2 --> P3["3. Lakukan Konsultasi Klinis & Anamnesis"]
    P3 --> P4["4. Submit Draf Resep ke CDSEngine"]
    P4 --> P5["5. Cek Alergi, DDI, Klirens Ginjal & Dosis Max"]
    P5 --> P6["6. Terbitkan Resep dengan Tanda Tangan Digital"]
    P6 --> P7["7. Rute Geospasial ke Apotek Terdekat & Alokasi FEFO"]
    P7 --> P8["8. Racik Obat & Verifikasi Double-Check Label"]
    P8 --> P9["9. Serahkan Boks Pendingin (Suhu Awal 4.0°C)"]
    P9 --> P10["10. Telemetri Sensor IoT Dingin Realtime (2°C - 8°C)"]
    P10 --> P11["11. Verifikasi NIK KTP & Tanda Tangan Digital e-POD"]
    P11 --> P12["12. Selesaikan Pengantaran (Status: DELIVERED)"]
```

---

## 3. Diagram Alur Rujukan Rawat Inap Darurat dari Telemedis

Ketika kondisi pasien memburuk selama telekonsultasi dan memerlukan rawat inap:

```mermaid
flowchart TD
    R1["1. bookInpatientFromReferral(teleConsultId, wardClass, ICD-10)"] --> R2["2. Cari Bed Tersedia (status: AVAILABLE) & Lock"]
    R2 --> R3["3. Update Bed status = OCCUPIED"]
    R3 --> R4["4. Buat Admission (admission_no, hospital_bed_id, ADMITTED)"]
    R4 --> R5["5. Bentuk Encounter Baru Rawat Inap"]
    R5 --> R6["6. Return Kode Admisi, Nomor Bed, & Petunjuk Rujukan"]
```

