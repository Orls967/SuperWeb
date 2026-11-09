# Kamus Data & Spesifikasi Skema Basis Data Fase 88

## 1. Ikhtisar Skema Basis Data Rumah Sakit (Fase 88)

Pada Fase 88, skema basis data modul `Hospital` diperluas melalui migrasi `2026_11_09_088100_create_hsp_phase_88_tables.php` guna mendukung penagihan komprehensif, interoperabilitas jaminan asuransi komersial, telemetri pemantauan rantai dingin, kustodi limbah B3 Festronik, serta penanganan sanggahan klaim (*claim disputes*).

```
+---------------------------------------------------------------------------------------------------+
|                                 RELASI ANTAR TABEL UTAMA FASE 88                                  |
+---------------------------------------------------------------------------------------------------+

     +-----------------------+                    +-----------------------+
     |   hsp_encounters      |<-------------------|  hsp_billing_episodes |
     |   (Kunjungan Pasien)  |                    |  (Episode Penagihan)  |
     +-----------------------+                    +-----------------------+
                 |                                            |
                 |                                            |--- 1:N ---> [hsp_folio_items]
                 |                                            |--- 1:N ---> [hsp_claim_waterfalls]
                 v                                            |--- 1:N ---> [hsp_claim_disputes]
     +-----------------------+                                v
     | hsp_guarantee_letters |                    +-----------------------+
     | (Surat Jaminan K31)   |                    |  hsp_ina_cbg_tariffs  |
     +-----------------------+                    |  (Master Tarif CBG)   |
                                                  +-----------------------+

     +-----------------------+                    +-----------------------------+
     | hsp_pharmacy_branches |                    | hsp_medical_waste_manifests |
     | (Cabang / Depo Farmasi|                    | (Manifes Limbah B3 KLHK)    |
     +-----------------------+                    +-----------------------------+
                 |                                                |
                 v                                                v
     +------------------------------+             +-----------------------------+
     | hsp_cold_chain_telemetries   |             | hsp_waste_custody_logs      |
     | (Sensor Suhu IoT & Karantina)|             | (Rantai Kustodi SHA-256)    |
     +------------------------------+             +-----------------------------+
```

---

## 2. Rincian Kamus Data Tabel-Tabel Baru

### A. Tabel `hsp_ina_cbg_tariffs`
Menyimpan katalog master tarif paket prospektif INA-CBG berdasarkan Permenkes No. 3 Tahun 2023.

| Nama Kolom | Tipe Data | Nullable | Constraints & Defaults | Deskripsi Bisnis |
| :--- | :--- | :---: | :--- | :--- |
| `id` | BIGINT UNSIGNED | Tidak | Auto Increment, Primary Key | Identitas baris unik |
| `cbg_code` | VARCHAR(16) | Tidak | Unique Index | Kode alfanumerik INA-CBG (misal: `I-4-10-I`, `J-4-15-III`) |
| `cbg_description` | VARCHAR(255) | Tidak | - | Deskripsi klinis resmi diagnosis/prosedur |
| `care_type` | VARCHAR(16) | Tidak | Index (INPATIENT, OUTPATIENT) | Tipe pelayanan rawat inap atau rawat jalan |
| `severity_level` | VARCHAR(8) | Tidak | Index (I, II, III) | Tingkat keparahan komplikasi & komorbiditas |
| `base_tariff_idr` | BIGINT | Tidak | Unsigned | Nilai tarif dasar nasional dalam satuan rupiah penuh |
| `regional_zone` | VARCHAR(8) | Tidak | Default '1' | Kode regional tarif (Zona 1 s/d Zona 5) |
| `hospital_class` | VARCHAR(4) | Tidak | Default 'B' | Kelas rumah sakit (A, B, C, D) |
| `special_procedure_addon_idr` | BIGINT | Tidak | Default 0 | Tambahan tarif prosedur khusus (Special Procedure) |
| `special_drugs_addon_idr` | BIGINT | Tidak | Default 0 | Tambahan tarif obat khusus kemoterapi/biologi |
| `is_active` | BOOLEAN | Tidak | Default true | Status keaktifan tarif pada sistem kasir |
| `created_at` | TIMESTAMP | Ya | - | Waktu pembuatan data |
| `updated_at` | TIMESTAMP | Ya | - | Waktu pembaruan data |

---

### B. Tabel `hsp_guarantee_letters`
Menyimpan surat jaminan pembayaran nontunai (*cashless*) yang diterbitkan oleh asuransi swasta / TPA (Kontrak G7-K31).

| Nama Kolom | Tipe Data | Nullable | Constraints & Defaults | Deskripsi Bisnis |
| :--- | :--- | :---: | :--- | :--- |
| `id` | BIGINT UNSIGNED | Tidak | Auto Increment, Primary Key | Identitas baris unik |
| `guarantee_number` | VARCHAR(64) | Tidak | Unique Index | Nomor referensi unik Surat Jaminan dari TPA |
| `encounter_id` | BIGINT UNSIGNED | Tidak | Foreign Key -> `hsp_encounters.id` | Tautan ke encounter kunjungan pasien |
| `insurer_name` | VARCHAR(128) | Tidak | - | Nama entitas asuransi komersial penjamin |
| `policy_number` | VARCHAR(64) | Tidak | Index | Nomor polis asuransi pemegang jaminan |
| `patient_name` | VARCHAR(128) | Tidak | - | Nama lengkap pasien tertanggung |
| `approved_limit_idr` | BIGINT | Tidak | Unsigned | Plafon batas maksimal biaya yang dijamin |
| `deductible_idr` | BIGINT | Tidak | Default 0 | Biaya pengurangan yang wajib ditanggung pasien |
| `coinsurance_pct` | DECIMAL(5,2) | Tidak | Default 0.00 | Persentase bagi-biaya tanggungan pasien (%) |
| `status` | VARCHAR(32) | Tidak | Default 'APPROVED' | Status: APPROVED, PARTIALLY_APPROVED, REJECTED, EXPIRED |
| `valid_until` | TIMESTAMP | Ya | - | Batas waktu kedaluwarsa surat jaminan |
| `tpa_signature_hash` | VARCHAR(128)| Ya | - | Hash verifikasi tanda tangan digital TPA |
| `created_at` | TIMESTAMP | Ya | - | Waktu penerbitan jaminan |
| `updated_at` | TIMESTAMP | Ya | - | Waktu mutasi data |

---

### C. Tabel `hsp_claim_waterfalls`
Mencatat detail tahapan penyerapan pembiayaan pada siklus penagihan berlapis (*Coordination of Benefits*).

| Nama Kolom | Tipe Data | Nullable | Constraints & Defaults | Deskripsi Bisnis |
| :--- | :--- | :---: | :--- | :--- |
| `id` | BIGINT UNSIGNED | Tidak | Auto Increment, Primary Key | Identitas baris unik |
| `billing_episode_id` | BIGINT UNSIGNED | Tidak | Foreign Key -> `hsp_billing_episodes.id` | Tautan ke episode penagihan |
| `layer_order` | INT | Tidak | Index (1, 2, 3, ...) | Urutan prioritas penyerapan biaya |
| `payer_type` | VARCHAR(32) | Tidak | BPJS, PRIVATE_INSURANCE, CHARITY, PATIENT_OOP | Kategori penjamin |
| `payer_name` | VARCHAR(128) | Tidak | - | Nama spesifik institusi penjamin / nama pasien |
| `covered_amount_idr` | BIGINT | Tidak | Unsigned | Nominal biaya yang berhasil diserap pada layer ini |
| `patient_share_idr` | BIGINT | Tidak | Default 0 | Porsi copay/coinsurance yang dibebankan ke pasien |
| `status` | VARCHAR(32) | Tidak | Default 'PENDING' | Status alokasi: PENDING, ADJUDICATED, SETTLED |
| `reconciliation_notes` | TEXT | Ya | - | Catatan audit penyesuaian tarif |
| `settled_at` | TIMESTAMP | Ya | - | Waktu pelunasan kas atau pengakuan piutang |
| `created_at` | TIMESTAMP | Ya | - | Waktu eksekusi waterfall |
| `updated_at` | TIMESTAMP | Ya | - | Waktu pembaruan data |

---

### D. Tabel `hsp_cold_chain_telemetries`
Menyimpan log telemetri sensor suhu IoT dan status insiden ekskursi termal lemari pendingin farmasi.

| Nama Kolom | Tipe Data | Nullable | Constraints & Defaults | Deskripsi Bisnis |
| :--- | :--- | :---: | :--- | :--- |
| `id` | BIGINT UNSIGNED | Tidak | Auto Increment, Primary Key | Identitas baris unik |
| `sensor_device_code` | VARCHAR(64) | Tidak | Index | ID kode perangkat sensor IoT PT100/NTC |
| `fridge_unit_code` | VARCHAR(64) | Tidak | Index | Kode unit lemari pendingin / freezer farmasi |
| `storage_zone` | VARCHAR(32) | Tidak | CHILLER_2_TO_8, ULT_MINUS_80, AMBIENT | Zona rentang suhu penyimpanan |
| `temperature_celsius` | DECIMAL(5,2)| Tidak | - | Nilai suhu terukur aktual dalam derajat Celsius |
| `humidity_pct` | DECIMAL(5,2)| Ya | - | Nilai kelembaban relatif udara (%) |
| `is_excursion` | BOOLEAN | Tidak | Default false, Index | Menandakan terjadi deviasi suhu di luar batas aman |
| `severity` | VARCHAR(16) | Tidak | NORMAL, WARNING, CRITICAL | Tingkat keparahan penyimpangan suhu |
| `requires_quarantine` | BOOLEAN | Tidak | Default false | Mengharuskan penguncian stok obat seketika |
| `logged_at` | TIMESTAMP | Tidak | Index | Waktu pembacaan fisik oleh sensor IoT |
| `created_at` | TIMESTAMP | Ya | - | Waktu pencatatan di database |
| `updated_at` | TIMESTAMP | Ya | - | Waktu pembaruan status |

---

### E. Tabel `hsp_waste_custody_logs`
Menyimpan rantai kustodi penimbangan limbah B3 medis dengan penguncian hash SHA-256.

| Nama Kolom | Tipe Data | Nullable | Constraints & Defaults | Deskripsi Bisnis |
| :--- | :--- | :---: | :--- | :--- |
| `id` | BIGINT UNSIGNED | Tidak | Auto Increment, Primary Key | Identitas baris unik |
| `medical_waste_manifest_id` | BIGINT UNSIGNED | Tidak | Foreign Key -> `hsp_medical_waste_manifests.id` | Tautan ke manifes limbah induk |
| `stage` | VARCHAR(32) | Tidak | WARD_LOGGED, TPS_RECEIVED, TRANSPORTER_HANDOVER, DESTROYED | Tahapan perpindahan fisik limbah |
| `weight_kg` | DECIMAL(8,3)| Tidak | - | Berat timbangan aktual dalam kilogram |
| `handled_by_name` | VARCHAR(128) | Tidak | - | Nama lengkap petugas penanggung jawab |
| `facility_location` | VARCHAR(128) | Tidak | - | Nama ruangan atau kode lokasi fisik |
| `previous_log_hash` | VARCHAR(64) | Ya | - | Hash SHA-256 dari simpul log sebelumnya |
| `current_log_hash` | VARCHAR(64) | Tidak | Index | Hash SHA-256 simpul saat ini (keutuhan data) |
| `tamper_flag` | BOOLEAN | Tidak | Default false | Menandakan adanya manipulasi integritas data |
| `logged_at` | TIMESTAMP | Tidak | Index | Waktu riil penimbangan dan serah terima |
| `created_at` | TIMESTAMP | Ya | - | Waktu pencatatan sistem |
| `updated_at` | TIMESTAMP | Ya | - | Waktu verifikasi hash |

---

### F. Tabel `hsp_claim_disputes`
Menyimpan berkas sengketa penolakan klaim BPJS/TPA dan pelacakan banding ke TKMKB.

| Nama Kolom | Tipe Data | Nullable | Constraints & Defaults | Deskripsi Bisnis |
| :--- | :--- | :---: | :--- | :--- |
| `id` | BIGINT UNSIGNED | Tidak | Auto Increment, Primary Key | Identitas baris unik |
| `billing_episode_id` | BIGINT UNSIGNED | Tidak | Foreign Key -> `hsp_billing_episodes.id` | Tautan ke episode tagihan terkait |
| `dispute_code` | VARCHAR(32) | Tidak | Index (D01, D02, D03, D04, ...) | Kode kategori penolakan verifikator |
| `disputed_amount_idr` | BIGINT | Tidak | Unsigned | Nilai nominal rupiah yang disengketakan |
| `clinical_rationale` | TEXT | Tidak | - | Argumen medis pembenaran tindakan/koding |
| `appeal_status` | VARCHAR(32) | Tidak | PENDING_REVIEW, SUBMITTED_TKMKB, UPHELD, OVERTURNED | Status proses banding sanggahan |
| `resolution_date` | TIMESTAMP | Ya | - | Tanggal kesepakatan final penyelesaian |
| `resolved_amount_idr` | BIGINT | Ya | - | Nilai akhir klaim yang disepakati dibayar |
| `created_at` | TIMESTAMP | Ya | - | Waktu pengajuan sanggahan |
| `updated_at` | TIMESTAMP | Ya | - | Waktu pembaruan status sidang |

