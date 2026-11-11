# Arsitektur Isolasi Multi-tenansi Komprehensif (Fase 188)

Dokumen ini mendokumentasikan prinsip desain arsitektural, batas isolasi (*isolation boundaries*), mekanisme pertahanan berlapis (*defense-in-depth*), serta protokol penjaminan kebocoran nol (*zero data leakage*) dalam platform SuperWeb.

---

## 1. Latar Belakang & Filosofi Isolasi (W8-D54)

SuperWeb dirancang untuk melayani ribuan entitas bisnis (*tenants*) secara bersamaan (*multi-tenant shared-process architecture*). 

Dalam arsitektur *shared database / shared schema*, risiko terbesar adalah kegagalan isolasi data (*cross-tenant leakage* / *tenant hopping*) di mana pengguna dari Tenant A dapat melihat atau memodifikasi data milik Tenant B baik secara sengaja (melalui serangan IDOR - *Insecure Direct Object Reference*) maupun tidak sengaja (melalui *query leak* tanpa filter tenant).

Untuk memitigasi risiko ini hingga ke level *mathematical assurance*, SuperWeb menerapkan 5 lapisan pertahanan:

```
[Layer 1: Edge & Gateway] -> Resolusi subdomain / header X-Tenant-ID & validasi token
        |
        v
[Layer 2: Multi-Dimensional Scoping] -> Tenant × Org × Role × Context
        |
        v
[Layer 3: Eloquent Global Scope] -> TenantScope otomatis menyuntikkan where('tenant_id', ...)
        |
        v
[Layer 4: In-Memory ABAC Guard] -> Deny-Overrides memverifikasi kepemilikan resource
        |
        v
[Layer 5: Automated Daily Probe] -> Synthetic probe queries menguji batas kebocoran
```

---

## 2. Model Registri Lingkup Tenan (`TenantScopeRegistry`)

Setiap model database yang menyimpan data spesifik tenan WAJIB terdaftar dalam `TenantScopeRegistry`. Model-model ini secara otomatis menerapkan trait `BelongsToTenant` atau global scope `TenantScope`.

### Alur Eksekusi Query

```
Aplikasi memanggil Model::all()
       |
       v
Global Scope TenantScope mencegat query
       |
       +--> Apakah tenant aktif terdeteksi?
       |         |
       |         +--[YA]--> Menambahkan query SQL: WHERE tenant_id = '{active_uuid}'
       |         |
       |         +--[TIDAK]--> Menolak query / melempar UnauthorizedTenantAccessException
```

### Pengecualian Global Scope yang Aman
Hanya proses latar belakang (*background workers*, migrasi database, dan audit reconciler) yang diizinkan memanggil `withoutGlobalScope(TenantScope::class)` atau `withoutTenancy()`, dan pemanggilan ini diawasi secara ketat oleh pengujian arsitektur (`arch:scan` & `TenantIsolationGuard`).

---

## 3. Matriks Lingkup Multi-Dimensi (Tenant × Org × Role × Context)

Isolasi hak akses tidak hanya ditentukan oleh tenant secara tunggal, melainkan merupakan perpotongan dari 4 dimensi ortogonal:

| Dimensi | Parameter | Deskripsi & Batas |
|---|---|---|
| **Tenant** | `tenant_id` (UUID v7) | Partisi data primer. Isolasi mutlak; data antar tenan tidak dapat bercampur kecuali di bawah perjanjian konsorsium (*consortium sharing*). |
| **Organization** | `org_unit_id`, `department_codes` | Partisi vertikal internal tenan (Divisi Keuangan, Operasional, Medis, Rantai Pasok). |
| **Role & Clearance** | `roles`, `clearance_level` (1-5) | Partisi hak istimewa (Staff, Supervisor, Auditor, Direktur, C-Level). |
| **Context** | `ip`, `geo_country`, `security_zone`, `mfa` | Faktor lingkungan dinamis (jam kerja, jaringan LAN terpercaya, status MFA aktif). |

### Hash Vektor Lingkup
Setiap konteks akses diringkas menjadi sebuah *Scope Vector Hash* yang deterministik:
$$\text{VectorHash} = \text{SHA256}(\text{TenantID} \mathbin{\Vert} \text{OrgID} \mathbin{\Vert} \text{Roles} \mathbin{\Vert} \text{SecurityZone} \mathbin{\Vert} \text{ThreatLevel})$$

Vektor hash ini digunakan oleh `AbacMemoryCache` untuk memvalidasi apakah konteks lingkungan permintaan saat ini identik dengan konteks saat keputusan otorisasi pertama kali diberikan.

---

## 4. Grafik Identitas Lintas-Modul & Skema Double Consent (W8-D56)

Ketika entitas yang sama beroperasi di berbagai lini bisnis dalam satu ekosistem tenan (misalnya seorang dokter di modul Hospital yang juga merupakan konsultan di modul Edu, atau mitra pengemudi di Logistics yang juga pemilik restoran di Food), grafik identitas menghubungkan profil-profil ini tanpa menghapus batasan tenan.

### Syarat Tautan Identitas
1. **Ambang Batas Skor Kecocokan**: Skor kesamaan berbobot (*weighted similarity score*) dari NIK, Nomor BPJS, NPWP, Nama Legal, Telepon, dan Tanggal Lahir harus $\ge 0.85$ (85%).
2. **Persetujuan Ganda (*Double Consent*)**: Kedua belah pihak (`source_consent` dan `target_consent`) harus memberikan persetujuan eksplisit. Jika salah satu pihak menarik persetujuan (`consent = false`), status tautan seketika berubah menjadi `BLOCKED_MISSING_CONSENT`, dan pertukaran profil lintas-vertikal langsung dibekukan.

---

## 5. Protokol Pembuktian Isolasi Sintetis Harian (W8-D58)

Layanan `TenantBoundaryAssuranceService` menjalankan pengujian penetrasi otomatis secara terjadwal:
1. **Uji Kebocoran Baca (*Cross-Tenant Read Leakage Probe*)**: Mencoba melakukan query langsung terhadap ID rekaman milik Tenant B saat konteks sesi aktif adalah Tenant A. Hasil yang diharapkan: ModelNotFoundException (HTTP 404).
2. **Uji Penahanan Tulis (*Cross-Tenant Write Containment Probe*)**: Mencoba melakukan update atau delete pada baris milik Tenant B dari sesi Tenant A. Hasil yang diharapkan: Penolakan langsung oleh `TenantIsolationGuard`.
3. **Uji Bypass Tanpa Tenansi (*Unscoped Query Probe*)**: Memeriksa seluruh query yang dieksekusi di jalur web apakah ada yang lolos tanpa klausa `tenant_id`.

Hasil pengujian direkam dalam bentuk sertifikat kepatuhan harian dengan skor isolasi wajib 100.0%.

