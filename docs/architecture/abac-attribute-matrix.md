# Matriks Atribut Otorisasi ABAC & Multi-Tenancy 30 Lini

Dokumen ini mendefinisikan matriks atribut, hierarki evaluasi, dan model penegakan isolasi akses berbasis atribut (*Attribute-Based Access Control / ABAC*) pada platform SuperWeb (Fase 188).

---

## 1. Dimensi Otorisasi & Skala Pembatasan

Setiap permintaan akses operasional atau mutasi data dievaluasi berdasarkan lima dimensi hierarki utama:

| Dimensi | Tipe Data | Representasi Contoh | Perilaku Wildcard (`*`) | Cakupan Regulasi |
|---|---|---|---|---|
| `entity_id` | `string` / `uuid` | `PT-AGRO-01`, `PT-MAR-02` | Akses lintas anak perusahaan induk (Khusus C-Suite/Auditor Grup) | Pemisahan liabilitas hukum perseroan terbatas |
| `region_id` | `string` | `ID-JKT`, `ID-SBY`, `ID-SUL` | Akses seluruh zona operasional nasional | Yurisdiksi pajak daerah & kepatuhan zona logistik |
| `site_id` | `string` | `WH-MARUNDA`, `PLANT-CIK-01` | Akses seluruh fasilitas fisik dalam wilayah | Keselamatan kerja fisik & audit inventaris gudang |
| `project_id`| `string` | `PRJ-EV-2026`, `EXP-SUMATRA` | Akses seluruh lini inisiatif perusahaan | Kerahasiaan R&D, merger/akuisisi & proyek strategis |
| `valid_window` | `timestamp` | `2026-11-01 s.d. 2026-11-30` | Izin tanpa batas akhir (Khusus peran sistemik tetap) | Pembatasan hak akses berbasis penugasan sementara |

---

## 2. Matriks Aturan Kebijakan Standar per Peran Operasional

| Kode Peran | Izin Standar | Dimensi Efektif | Kebijakan Terpilih | Strategi Evaluasi |
|---|---|---|---|---|
| `platform_architect` | `architecture:*`, `access:admin` | `entity_id = *`, `region_id = *` | ALLOW semua rute arsitektur & tata kelola akses | Deny-Overrides |
| `security_officer` | `access:revoke`, `access:suspend` | `entity_id = *`, `region_id = *` | ALLOW pembekuan & pencabutan hak darurat | Deny-Overrides |
| `auditor` | `audit:read`, `reports:view` | `entity_id = [assigned]`, `site_id = *` | ALLOW baca log imutabel; DENY mutasi data operasional | Deny-Overrides (Mutlak DENY pada aksi tulis) |
| `site_manager` | `wms:manage`, `asset:operate` | `site_id = [site]`, `region_id = [region]` | ALLOW operasional gudang dan pabrik setempat | Deny-Overrides (DENY jika site_id mismatch) |
| `procurement_buyer`| `po:create`, `vendor:read` | `entity_id = [entity]`, `limit <= [threshold]`| ALLOW pemesanan hingga limit kredit vendor cluster | Deny-Overrides (DENY jika eksposur over-limit) |
| `compliance_officer`| `recert:manage`, `consent:audit`| `entity_id = *`, `valid_window = [active]`| ALLOW audit kampanye re-sertifikasi & consent graf | Deny-Overrides |

---

## 3. Matriks Pembobotan Penautan Identitas Pelanggan/Mitra

| Atribut Identitas | Metode Normalisasi | Algoritma Hashing | Bobot Skor | Ketentuan Validitas |
|---|---|---|---|---|
| **Nomor Induk Kependudukan (NIK)** | Angka 16 digit dibersihkan dari spasi/strip | HMAC-SHA256 (Kunci Salt Platform) | **100** | Format wajib valid regex `^[0-9]{16}$` |
| **Alamat Surat Elektronik (Email)** | Huruf kecil (`strtolower`), trim spasi | HMAC-SHA256 (Kunci Salt Platform) | **60** | Validitas RFC 5322 |
| **Nomor Telepon Seluler** | Format E.164 (`+628...`) | HMAC-SHA256 (Kunci Salt Platform) | **50** | Normalisasi prefix `08` ke `+628` |
| **Nama Lengkap + Tanggal Lahir** | Nama fonetik Soundex + Tanggal `YYYY-MM-DD` | SHA-256 Digest | **40** | Menghilangkan gelar dan karakter khusus |

> **Ambang Batas Penautan Sah:**
> $$\text{Skor Total} \ge 90 \implies \text{Memenuhi Syarat Penautan}$$
> 
> Penautan **DIBLOKIR** (`BLOCKED_NO_CONSENT`) jika salah satu pihak (`source_consent` atau `target_consent`) bernilai `false`.

