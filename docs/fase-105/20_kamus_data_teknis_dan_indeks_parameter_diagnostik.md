# Kamus Data Teknis & Indeks Parameter Diagnostik (Fase 105)

## 1. Kamus Data Atribut Spesimen Laboratorium

Kamus data ini mendefinisikan tipe data, validasi, dan batasan integritas untuk setiap elemen data spesimen laboratorium:

| Nama Kolom | Tipe Basis Data | Batasan / Nullable | Deskripsi Fungsional Medis | Contoh Nilai |
|---|---|---|---|---|
| `specimen_barcode` | `VARCHAR(64)` | NOT NULL, UNIQUE | Barcode unik Code 128 yang tercetak pada label tabung | `SPEC-20261109-0091` |
| `patient_id` | `BIGINT UNSIGNED` | NOT NULL, FK | Relasi kunci asing ke tabel identitas pasien `hsp_patients` | `1024` |
| `encounter_id` | `BIGINT UNSIGNED` | NULLABLE, FK | Kunjungan rawat inap atau rawat jalan terkait | `5012` |
| `sample_type` | `VARCHAR(32)` | NOT NULL | Jenis matriks biologis: `BLOOD`, `SERUM`, `PLASMA`, `URINE`, `CSF`, `SWAB` | `BLOOD` |
| `collected_at` | `DATETIME` | NOT NULL | Waktu tepat saat darah diambil oleh perawat phlebotomy | `2026-11-09 08:15:00` |
| `transport_temp_c` | `DECIMAL(4,1)` | NULLABLE | Suhu kotak pendingin saat serah terima antar-staf (°C) | `4.2` |
| `custody_hash` | `VARCHAR(64)` | NOT NULL | Digest SHA-256 rantai kustodi terkini | `a4f8...e2f3` |
| `status` | `VARCHAR(32)` | NOT NULL | Status mesin: `COLLECTED`, `IN_TRANSIT`, `RECEIVED_LAB`, `PROCESSED`, `REJECTED`, `DISPOSED` | `COLLECTED` |

## 2. Kamus Data Atribut Notifikasi Nilai Kritis

| Nama Kolom | Tipe Basis Data | Batasan / Nullable | Deskripsi Fungsional Medis | Contoh Nilai |
|---|---|---|---|---|
| `notification_uid` | `VARCHAR(64)` | NOT NULL, UNIQUE | Pengenal unik pelacakan notifikasi darurat | `NOTIF-CRIT-99014` |
| `test_code` | `VARCHAR(32)` | NOT NULL | Kode uji LOINC atau kode baku analit | `POTASSIUM` |
| `analyte_name` | `VARCHAR(128)` | NOT NULL | Nama analit lengkap dalam bahasa klinis | `Serum Potassium (K+)` |
| `measured_value` | `DECIMAL(12,4)` | NOT NULL | Nilai hasil pemeriksaan yang diukur alat | `2.1000` |
| `panic_threshold` | `DECIMAL(12,4)` | NOT NULL | Batas panik yang dilanggar (low atau high) | `2.5000` |
| `panic_type` | `VARCHAR(16)` | NOT NULL | Jenis pelanggaran batas: `LOW` atau `HIGH` | `LOW` |
| `notification_protocol`| `VARCHAR(64)` | NOT NULL | Nama protokol panggilan darurat | `CRITICAL_HOLD_AND_PAGE` |
| `doctor_phone` | `VARCHAR(255)` | NULLABLE, ENCRYPTED | Nomor telepon darurat dokter yang dienkripsi | `enc:v2:...` |
| `read_back_confirmed` | `BOOLEAN` | NOT NULL, DEFAULT 0 | Status konfirmasi lisan pembacaan ulang (TBAK) | `1` (True) |
| `pathologist_signature_hash` | `VARCHAR(64)` | NULLABLE | Digest SHA-256 tanda tangan digital dokter patolog | `7c9b...110a` |

## 3. Kamus Data Atribut Studi DICOM PACS

| Nama Kolom | Tipe Basis Data | Batasan / Nullable | Deskripsi Fungsional Medis | Contoh Nilai |
|---|---|---|---|---|
| `study_instance_uid` | `VARCHAR(64)` | NOT NULL, UNIQUE | Pengenal global berstandar ISO/IEC untuk studi radiologi | `1.2.840.113619.2.55.3...` |
| `accession_number` | `VARCHAR(32)` | NOT NULL, UNIQUE | Nomor accession order dari modul radiologi RIS | `ACC-RAD-202611-0012` |
| `modality` | `VARCHAR(16)` | NOT NULL | Singkatan modalitas: `CT`, `MRI`, `XR`, `US`, `NM` | `CT` |
| `body_part` | `VARCHAR(64)` | NOT NULL | Region anatomi tubuh yang dipindai | `HEAD_BRAIN_CONTRAST` |
| `turnaround_minutes` | `INT` | NOT NULL, DEFAULT 0 | Durasi penyelesaian ekspertise dalam menit | `25` |
| `priority` | `VARCHAR(16)` | NOT NULL | Tingkat urgensi: `STAT`, `CITO`, `ROUTINE` | `STAT` |
| `radiologist_findings`| `TEXT` | NULLABLE | Uraian temuan ekspertise dokter spesialis radiologi | `No intracranial bleed...` |
| `fee_idr` | `BIGINT` | NOT NULL, DEFAULT 0 | Tarif jasa pemeriksaan radiologi dalam satuan rupiah | `1850000` |

