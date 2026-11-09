# Mesin Status (State Machines) Spesimen, Studi Radiologi & Notifikasi Kritis (Fase 105)

## 1. State Machine Rantai Kustodi Spesimen (`SpecimenStateMachine`)

Siklus hidup tabung spesimen biologis dari pengambilan fisik sampai pemusnahan biohazard dikawal oleh mesin status deterministik 6 status:

```mermaid
flowchart TD
    COLLECTED[1. COLLECTED: Tabung Diambil & Diberi Label Barcode]
    IN_TRANSIT[2. IN_TRANSIT: Dalam Box Kurir Suhu Terkendali]
    RECEIVED_LAB[3. RECEIVED_LAB: Tiba di Meja Triase Lab Pusat]
    PROCESSED[4. PROCESSED: Analisis Instrumen Selesai]
    REJECTED[5. REJECTED: Ditolak / Suhu Dingin Rusak / Hemolisis]
    DISPOSED[6. DISPOSED: Dimusnahkan Sesuai SOP Limbah Medis B3]

    COLLECTED -->|Hop 1: Scan Serah Terima Kurir| IN_TRANSIT
    COLLECTED -->|Pelanggaran Suhu / Sampel Rusak di Phlebotomy| REJECTED
    IN_TRANSIT -->|Hop 2: Scan Penerimaan Lab Triase| RECEIVED_LAB
    IN_TRANSIT -->|Suhu > 10C / Wadah Pecah / Hash Cacat| REJECTED
    RECEIVED_LAB -->|Hop 3: Verifikasi Hash Utuh & Aliquot| PROCESSED
    RECEIVED_LAB -->|Clotted / Volume Tidak Cukup (QNS)| REJECTED
    PROCESSED -->|Masa Retensi Sampel Usai (7 Hari)| DISPOSED
    REJECTED -->|Segel & Masuk Kantong Biohazard Kuning| DISPOSED
```

## 2. State Machine Hasil Lab & Notifikasi Kritis (`LabResultPanicStateMachine`)

Siklus hidup hasil pemeriksaan laboratorium diagnostik:

```mermaid
flowchart TD
    PRELIMINARY[1. PRELIMINARY: Hasil Mentah Alat Diterima]
    AUTO_VERIFIED[2. AUTO_VERIFIED: Lolos Validasi Aturan Klinis Normal]
    CRITICAL_HOLD[3. CRITICAL_HOLD: Tertahan Karena Melewati Batas Panik]
    NOTIFIED[4. NOTIFIED: Dokter Jaga Telah Dihubungi Telepon/Pager]
    READBACK_CONFIRMED[5. READBACK_CONFIRMED: Konfirmasi Lisan Dibacakan Ulang]
    PATHOLOGIST_VERIFIED[6. PATHOLOGIST_VERIFIED: Tanda Tangan Digital Patolog Sah]
    AMENDED[7. AMENDED: Koreksi Nilai Hasil dengan Jejak Audit]

    PRELIMINARY -->|Nilai Dalam Rentang Normal/Ringan| AUTO_VERIFIED
    PRELIMINARY -->|Nilai Melewati Panic Low / High| CRITICAL_HOLD
    CRITICAL_HOLD -->|Panggilan Stat Otomatis / Telepon Sukses| NOTIFIED
    NOTIFIED -->|Penerima Membaca Ulang Nama Pasien & Nilai| READBACK_CONFIRMED
    READBACK_CONFIRMED -->|Review Klinis & Tanda Tangan Digital| PATHOLOGIST_VERIFIED
    AUTO_VERIFIED -->|Ditemukan Koreksi Klinis / Pengulangan| AMENDED
    PATHOLOGIST_VERIFIED -->|Koreksi Post-Release dengan Berita Acara| AMENDED
```

## 3. State Machine Studi Radiologi PACS (`ImagingStudyStateMachine`)

Siklus hidup berkas pemeriksaan radiologi:

```mermaid
flowchart TD
    SCHEDULED[1. SCHEDULED: Pasien Dijadwalkan Masuk Ruang Radiologi]
    IN_PROGRESS[2. IN_PROGRESS: Proses Pemindaian di Gantry CT/MRI]
    ACQUIRED[3. ACQUIRED: Seluruh Slice Selesai & Ditransfer ke PACS]
    PRE_READ[4. PRE_READ: Disposisi ke Antrean Worklist Radiolog]
    REPORTED[5. REPORTED: Ekspertise & Temuan Ditulis Radiolog]
    VERIFIED[6. VERIFIED: Hasil Final Disahkan Radiolog Konsultan]
    CANCELLED[7. CANCELLED: Dibatalkan Sebelum Pemindaian]

    SCHEDULED -->|Pasien Masuk Ruang Pemeriksaan| IN_PROGRESS
    SCHEDULED -->|Pasien Tidak Hadir / Kontraindikasi Kontras| CANCELLED
    IN_PROGRESS -->|C-STORE DICOM Berhasil| ACQUIRED
    ACQUIRED -->|Ekstraksi W/L & Dosis Radiasi Selesai| PRE_READ
    PRE_READ -->|Radiolog Menyusun Draf Laporan| REPORTED
    REPORTED -->|Otentikasi Tanda Tangan Digital| VERIFIED
```

## 4. State Machine Kliring Antar-Cabang (`InterBranchClearingStateMachine`)

Siklus penyelesaian kewajiban finansial:

```mermaid
flowchart LR
    PENDING[1. PENDING: Kewajiban Kliring Terbit]
    VERIFIED_AUDIT[2. VERIFIED_AUDIT: Berkas Dukung Valid]
    SETTLED[3. SETTLED: Buku Besar Dijurnal Debit-Kredit]
    DISPUTED[4. DISPUTED: Ada Ketidakcocokan Jumlah / Klaim]
    CANCELLED[5. CANCELLED: Transaksi Batal]

    PENDING -->|Verifikasi Dokumen Manifest/Lot| VERIFIED_AUDIT
    PENDING -->|Selisih Kuantitas / Harga Tidak Sesuai| DISPUTED
    VERIFIED_AUDIT -->|Eksekusi Settlement Finansial| SETTLED
    DISPUTED -->|Rekonsiliasi Selesai| VERIFIED_AUDIT
    DISPUTED -->|Dibatalkan Oleh Para Pihak| CANCELLED
```

