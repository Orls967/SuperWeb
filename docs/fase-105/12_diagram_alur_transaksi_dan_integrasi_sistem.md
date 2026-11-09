# Diagram Alur Transaksi & Integrasi Sistem (Fase 105)

## 1. Alur Transaksi End-to-End Pemeriksaan Laboratorium & Nilai Kritis

Diagram alur berikut mengilustrasikan interaksi menyeluruh antara Persona Klinis, Pengendali (*Controller*), Lapisan Layanan (*Domain Services*), Basis Data, dan Buku Besar Finansial:

```mermaid
flowchart TD
    subgraph Aktor["1. Aktor & Pengguna"]
        DOC["Dokter Pemohon / Dokter IGD"]
        PHL["Perawat Phlebotomist"]
        CUR["Kurir Medis Suhu Terkendali"]
        ATLM["Analis Lab / ATLM"]
        PAT["Dokter Spesialis Patologi Klinik"]
    end

    subgraph ControllerDanAPI["2. Controller & Routing"]
        CTRL["LabDiagnosticImagingController"]
    end

    subgraph ServiceLayer["3. Domain Services & Algoritma"]
        TRACKER["SpecimenCustodyHashChainTracker"]
        QC["LabQualityControlLevyJenningsEvaluator"]
        PANIC["CriticalValuePanicAlertCoordinator"]
        PACS["DiagnosticImagingPacsIntegrationService"]
        CLEARING["InterBranchLabClearingService"]
    end

    subgraph StorageLayer["4. Persistensi & Audit Trail"]
        DB_SPEC[("hsp_lab_specimens & hsp_specimen_custody_chain_logs")]
        DB_NOTIF[("hsp_critical_notifications")]
        DB_STUDY[("hsp_imaging_studies & hsp_imaging_series")]
        LEDGER[("Subledger Akuntansi Double-Entry")]
    end

    DOC -->|1. Order Tes Kalium & Darah Rutin| CTRL
    CTRL -->|2. Inisiasi Spesimen & Genesis Hash| TRACKER
    TRACKER -->|3. Simpan Rekam Spesimen COLLECTED| DB_SPEC

    PHL -->|4. Scan Barcode Serah Terima Hop 1| CTRL
    CTRL -->|5. Catat Hop 1 & Validasi Suhu < 10C| TRACKER
    TRACKER -->|6. Kunci Hash-Chain 1| DB_SPEC

    CUR -->|7. Scan Barcode Tiba di Lab Hop 2| CTRL
    CTRL -->|8. Catat Hop 2 & Validasi Rantai Dingin| TRACKER
    TRACKER -->|9. Kunci Hash-Chain 2| DB_SPEC

    ATLM -->|10. Jalankan Uji pada Alat Analisis| QC
    QC -->|11. Evaluasi Aturan Westgard Lolos| ATLM
    ATLM -->|12. Masukkan Hasil Uji Kalium 2.1 mEq/L| CTRL
    CTRL -->|13. Evaluasi Batas Panik| PANIC

    PANIC -->|14. Terdeteksi Panic Low: Tahan Rilis| DB_NOTIF
    PANIC -->|15. Kirim Panggilan STAT ke Dokter IGD| DOC

    DOC -->|16. Konfirmasi Lisan & Read-Back| PANIC
    PANIC -->|17. Catat Status READ_BACK_CONFIRMED| DB_NOTIF

    PAT -->|18. Review Medis & Tanda Tangan Digital SHA-256| PANIC
    PANIC -->|19. Perbarui Status PATHOLOGIST_VERIFIED| DB_NOTIF
    PANIC -->|20. Eksekusi Penjurnalan Pendapatan| LEDGER
    LEDGER -->|21. Debit: Piutang Lab, Kredit: Pendapatan Lab| LEDGER
```

## 2. Alur Transaksi Pencitraan Radiologi DICOM PACS & Viewer

```mermaid
flowchart TD
    subgraph RadiologiWorkflow["Alur Kerja Radiologi Diagnostik"]
        RAD_TECH["Radiografer (Modality Operator)"]
        MODALITY["Modalitas Radiologi: CT-Scan 128 Slices"]
        CONTROLLER["LabDiagnosticImagingController@uploadDicomStudy"]
        PACS_SVC["DiagnosticImagingPacsIntegrationService"]
        DOSE_TRK["CtRadiationDoseTracker"]
        STORAGE[("Arsip PACS & hsp_dicom_instances")]
        VIEWER["Dokter / Radiolog Buka Viewer Web"]
        WADO_SVC["DicomWebMetadataExtractor"]
    end

    RAD_TECH -->|1. Registrasikan Pasien & Posisikan di Gantry| MODALITY
    MODALITY -->|2. Rekam Irisan Citra CT & Pancarkan DICOM C-STORE| CONTROLLER
    CONTROLLER -->|3. Parsing Part 10 Metadata & SOP Instance UID| PACS_SVC
    PACS_SVC -->|4. Ekstraksi Metrik Dosimetri Radiasi| DOSE_TRK
    DOSE_TRK -->|5. Verifikasi Kepatuhan DRL BAPETEN| PACS_SVC
    PACS_SVC -->|6. Simpan Metadata Studi, Seri, & Instance| STORAGE
    
    VIEWER -->|7. Akses route hospital.imaging.pacs.index| CONTROLLER
    CONTROLLER -->|8. Minta Payload JSON WADO-RS / QIDO-RS| WADO_SVC
    WADO_SVC -->|9. Transformasi Window Width & Center Hounsfield| CONTROLLER
    CONTROLLER -->|10. Stream Metadata JSON & Citra Canvas| VIEWER
```

## 3. Alur Kompensasi & Penanganan Deviasi (Exception Handling)

```mermaid
flowchart TD
    EX1[Insiden 1: Suhu Kotak Pendingin > 10.0 C Saat Tiba di Lab]
    EX1 --> HND1[Trigger: ColdChainBreachException]
    HND1 --> ACT1[Otomatis Ubah Status Spesimen Jadi REJECTED]
    ACT1 --> BLK1[Blokir Mutlak Eksekusi Pemeriksaan pada Alat Analis]
    BLK1 --> NOT1[Pemberitahuan ke Ruangan untuk Pengambilan Sampel Ulang]

    EX2[Insiden 2: Analisis Kontrol Kualitas Melanggar Aturan Westgard 1-3s]
    EX2 --> HND2[Trigger: WestgardQcViolationException]
    HND2 --> ACT2[Karantina & Kunci Alat: is_locked = true]
    ACT2 --> BLK2[Batalkan Seluruh Batch Pengujian Sampel Pasien Berjalan]
    BLK2 --> NOT2[Panggilan Teknisi Kalibrasi & Dokumentasi CAPA]

    EX3[Insiden 3: Integritas Hash-Chain Spesimen Terputus di Hop 2]
    EX3 --> HND3[Trigger: HashChainIntegrityBrokenException]
    HND3 --> ACT3[Tandai Manipulasi Data & Exit Code 1 pada Command hsp:audit]
    ACT3 --> NOT3[Audit Forensik Komite Keselamatan Pasien]
```

