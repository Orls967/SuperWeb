# Kamus Data Skema Database Modul Hospital Fase 104

Dokumen ini mendokumentasikan skema relasional, indeks, serta batasan integritas tabel-tabel baru yang diperkenalkan pada migrasi Fase 104 (`2026_11_09_104100_create_hsp_phase_104_tables.php`).

---

## 1. Daftar Tabel Baru

1. `hsp_medicine_courier_deliveries`: Riwayat pengantaran obat oleh armada kurir internal/pihak ketiga disertai telemetri suhu dan tanda tangan penerima (e-POD).
2. `hsp_bpjs_referral_bridges`: Log transaksi jembatan rujukan SISRUTE dan bridging BPJS dari konsultasi telemedis ke rumah sakit rujukan.
3. `hsp_drug_interaction_rules`: Aturan dinamis interaksi obat kustom rumah sakit dan matriks farmakodinamik.

---

## 2. Rincian Kolom & Tipe Data

### 2.1 Tabel `hsp_medicine_courier_deliveries`
Mencatat seluruh siklus hidup pengantaran obat langsung ke rumah pasien:

| Nama Kolom | Tipe Data | Nullable | Keterangan & Batasan |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | Kunci utama (Auto Increment) |
| `delivery_code` | `VARCHAR(32)` | No | Kode unik pengiriman, misal `DEL-A1B2C3D4` (Unique) |
| `epharmacy_order_id` | `BIGINT UNSIGNED` | No | Relasi ke `hsp_epharmacy_orders.id` (Foreign Key, Cascade) |
| `courier_service` | `VARCHAR(64)` | No | Layanan ekspedisi (misal: `HOSPITAL_EXPRESS`, `GRAB_MEDIC`, `INTERNAL_FLEET`) |
| `courier_name` | `VARCHAR(128)` | No | Nama petugas kurir logistik |
| `courier_phone` | `VARCHAR(32)` | No | Nomor kontak aktif kurir |
| `recipient_name` | `VARCHAR(128)` | No | Nama penerima paket di alamat tujuan |
| `recipient_phone` | `VARCHAR(32)` | No | Nomor telepon penerima |
| `destination_address` | `TEXT` | No | Alamat pengantaran lengkap |
| `destination_latitude` | `DECIMAL(10, 7)` | Yes | Titik koordinat lintang tujuan |
| `destination_longitude` | `DECIMAL(10, 7)` | Yes | Titik koordinat bujur tujuan |
| `requires_cold_chain` | `BOOLEAN` | No | Status wajib rantai dingin (Default: false) |
| `min_allowed_temp_c` | `DECIMAL(5, 2)` | Yes | Suhu minimum yang diperbolehkan (°C, default 2.0) |
| `max_allowed_temp_c` | `DECIMAL(5, 2)` | Yes | Suhu maksimum yang diperbolehkan (°C, default 8.0) |
| `last_recorded_temp_c` | `DECIMAL(5, 2)` | Yes | Telemetri suhu terkini dari boks pendingin kurir |
| `temperature_breached` | `BOOLEAN` | No | Penanda pernah terjadi pelanggaran suhu (Default: false) |
| `delivery_status` | `VARCHAR(32)` | No | Status: `PENDING`, `IN_TRANSIT`, `DELIVERED`, `QUARANTINED_BREACH`, `FAILED` |
| `dispatched_at` | `TIMESTAMP` | Yes | Waktu kurir diberangkatkan dari apotek |
| `delivered_at` | `TIMESTAMP` | Yes | Waktu paket berhasil diserahkan ke penerima |
| `pod_recipient_nik` | `VARCHAR(32)` | Yes | NIK 16 digit penerima fisik paket |
| `pod_recipient_signature_hash` | `VARCHAR(64)` | Yes | Hash SHA-256 e-POD tanda tangan digital penerima |
| `created_at` / `updated_at` | `TIMESTAMP` | Yes | Jejak waktu pembuatan dan modifikasi rekaman |

### 2.2 Tabel `hsp_bpjs_referral_bridges`
Menghubungkan sesi telekonsultasi dengan rujukan SISRUTE dan vClaim BPJS:

| Nama Kolom | Tipe Data | Nullable | Keterangan & Batasan |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | Kunci utama (Auto Increment) |
| `referral_code` | `VARCHAR(32)` | No | Nomor registrasi rujukan Kemenkes (Unique) |
| `tele_consult_id` | `BIGINT UNSIGNED` | No | Relasi ke `hsp_tele_consults.id` (Foreign Key, Cascade) |
| `patient_id` | `BIGINT UNSIGNED` | No | Relasi ke `hsp_patients.id` (Foreign Key, Cascade) |
| `referring_doctor_id`| `BIGINT UNSIGNED` | No | Relasi ke `hsp_doctors.id` (Foreign Key, Cascade) |
| `target_hospital_code` | `VARCHAR(32)` | No | Kode Faskes penerima rujukan (standar Kemenkes) |
| `target_hospital_name` | `VARCHAR(128)` | No | Nama Rumah Sakit Rujukan tujuan |
| `target_polyclinic_code`| `VARCHAR(32)` | Yes | Kode poli tujuan rujukan rawat jalan |
| `referral_type` | `VARCHAR(32)` | No | Jenis: `INPATIENT_TRANSFER`, `OUTPATIENT_SPECIALIST`, `EMERGENCY_TRIAGE` |
| `urgency_level` | `VARCHAR(16)` | No | Tingkat kegawatan: `ROUTINE`, `URGENT`, `EMERGENCY` |
| `icd10_diagnosis_code` | `VARCHAR(16)` | No | Diagnosis utama berbasis kode ICD-10 (misal: `I21.9`) |
| `clinical_summary` | `TEXT` | No | Ringkasan anamnesis dan alasan permohonan rujukan |
| `sisrute_request_payload` | `JSON` | Yes | Salinan lengkap muatan JSON terenkripsi ke SISRUTE |
| `sisrute_response_payload`| `JSON` | Yes | Respon resmi server SISRUTE Kemkes |
| `status` | `VARCHAR(32)` | No | Status: `PENDING`, `ACCEPTED`, `REJECTED`, `CANCELLED`, `COMPLETED` |
| `response_notes` | `TEXT` | Yes | Catatan dokter penerima rujukan di RS tujuan |
| `responded_at` | `TIMESTAMP` | Yes | Waktu jawaban resmi dari RS tujuan |
| `created_at` / `updated_at` | `TIMESTAMP` | Yes | Jejak waktu standar audit |

### 2.3 Tabel `hsp_drug_interaction_rules`
Repositori aturan interaksi obat dinamis kustom:

| Nama Kolom | Tipe Data | Nullable | Keterangan & Batasan |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | Kunci utama |
| `drug_a_code` | `VARCHAR(64)` | No | Kode obat pertama (Index) |
| `drug_a_name` | `VARCHAR(128)` | No | Nama sediaan obat pertama |
| `drug_b_code` | `VARCHAR(64)` | No | Kode obat kedua (Index) |
| `drug_b_name` | `VARCHAR(128)` | No | Nama sediaan obat kedua |
| `interaction_severity` | `VARCHAR(32)` | No | Tingkat: `CONTRAINDICATED`, `MAJOR`, `MODERATE`, `MINOR` |
| `clinical_mechanism` | `TEXT` | No | Penjelasan mekanisme farmakokinetik/farmakodinamik |
| `recommendation` | `TEXT` | No | Panduan tindakan klinis bagi dokter/apoteker |
| `is_active` | `BOOLEAN` | No | Status keaktifan aturan (Default: true) |
| `created_at` / `updated_at` | `TIMESTAMP` | Yes | Jejak waktu modifikasi |

