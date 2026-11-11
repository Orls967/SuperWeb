# ADR-0051: Pendaftaran Terpusat Model Multi-Tenancy & Batas Isolasi Fail-Closed (W8-D51)

## Status
Diterima (Accepted)

## Konteks
Sebagai platform SaaS multi-penyewa (*multi-tenant*), kebocoran data antar-tenant (*cross-tenant data leakage*) merupakan ancaman keamanan dan privasi dengan tingkat keparahan tertinggi (Severity 1). Implementasi lama yang mengandalkan pengembang untuk secara manual menambahkan klausa `->where('tenant_id', ...)` pada setiap kueri controller sangat rentan terhadap kelalaian manusia (*human error*).

Selain itu, kebutuhan proses audit platform, rekonsiliasi antar-perusahaan, dan pemeliharaan sistem membutuhkan akses pembacaan lintas tenant yang sah tanpa melumpuhkan pertahanan keamanan umum.

## Keputusan Arsitektur

### 1. Penegakan Otomatis Melalui `TenantIsolationScope`
Setiap model domain yang memiliki kepemilikan tenant diwajibkan mendaftarkan diri ke `TenantScopeRegistry` melalui kontrak `TenantScopedModelProvider`.
Global scope `TenantIsolationScope` secara otomatis disuntikkan ke model bersangkutan saat boot aplikasi:
- Menambahkan klausa penyaring `WHERE {model}.tenant_id = ?` ke seluruh operasi kueri (`select`, `update`, `delete`).
- Mengisi kolom `tenant_id` secara otomatis saat pembuatan entitas baru (`creating` event).

### 2. Paradigma Penegakan Ketat (*Fail-Closed Strict Enforcement*)
Dalam mode operasi produksi dan pengujian (`withStrictEnforcement`):
- Bila sebuah kueri dijalankan terhadap model ter-scope namun `TenantContext::current()` bernilai `null` (tidak ada tenant aktif yang terikat), sistem **MENOLAK** melanjutkan kueri dan segera melempar:
  ```php
  throw new TenantContextMissingException("Strict tenant enforcement active: Model [{$modelClass}] queried without TenantContext.");
  ```
- Ini menjamin bahwa kesalahan pengkodean di lapisan controller/service akan memicu kegagalan deterministik daripada membocorkan data tenant lain.

### 3. Pengecualian Lingkup yang Terkelola & Terasosiasi Jejak Audit (*Audited Scope Bypass*)
Akses baca global lintas tenant hanya diizinkan melalui mekanisme resmi:
```php
$scopeRegistry->withoutTenantScope(
    ability: 'tenancy:audit_read',
    reason: 'Platform scheduled compliance verification',
    callback: fn () => CoreApiClient::count()
);
```
Setiap pemanggilan metode ini:
1. Mematikan sementara penyaringan tenant untuk eksekusi closure yang diberikan.
2. Mencatat entri fakta ke tabel append-only `core_tenant_scope_bypasses` berisi: identitas aktor (`actor_user_id`), kemampuan yang digunakan (`ability`), model terdampak (`model_class`), alasan pemanggilan (`reason`), dan stempel waktu.
3. Mengembalikan status isolasi ke kondisi aktif semula di blok `finally`.

### 4. Perintah Verifikasi Rutin Kepatuhan (`api:tenancy-coverage`)
Perintah CLI `api:tenancy-coverage` memindai seluruh model yang terdaftar di sistem:
- Memverifikasi keberadaan `TenantIsolationScope` pada setiap model.
- Mengembalikan exit code 0 bila seluruh model terlindungi secara aktif, atau exit code 1 bila terdeteksi model rentan tanpa scope.

## Konsekuensi & Kepatuhan
- **Positif:** Mengeliminasi seluruh kelas bug kebocoran data antar-tenant, kepatuhan penuh terhadap regulasi privasi data.
- **Negatif:** Seluruh pengujian unit/feature yang berinteraksi dengan model multi-tenant wajib membungkus logika dalam `TenantContext::runWith()`.
- **Verifikasi:** Diuji pada `ApiV2LifecycleTest` skenario `test_55_4_bulk_import_20k_resume_export_zip_and_tenant_isolation`.
