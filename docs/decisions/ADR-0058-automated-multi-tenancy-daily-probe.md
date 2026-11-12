# ADR-0058: Probe Otomatis Harian Isolasi Multi-Tenant & Penegakan Fail-Closed

**Status:** ACCEPTED  
**Tanggal:** 2026-11-12  
**Pengambil Keputusan:** Security Officer, Platform Architect, Pelaksana W8-P4  
**Konteks Gelombang:** Gelombang 8, Fase 188 (W8-P4.3 / W8-D58)

---

## Konteks & Masalah

Pada arsitektur *shared database, multi-tenant*, jaminan keterpisahan data tenant bergantung pada penegakan kueri `TenantScope` di Eloquent ORM. Celah kebocoran data (*cross-tenant leak*) dapat timbul tanpa disengaja akibat pengembang menggunakan kueri mentah (`DB::table(...)`) atau lupa mendaftarkan model baru ke dalam registry multi-tenancy.

Diperlukan mekanisme audit aktif harian (*continuous probing*) yang secara otomatis memverifikasi bahwa kueri atas nama Tenant A tidak pernah membocorkan data milik Tenant B.

---

## Keputusan Arsitektur

1. **Perintah Terjadwal `access:tenant-probe`:**
   - Dijalankan secara otomatis setiap hari via scheduler Artisan.
   - Mengambil sampel 100 pasangan tenant acak $(T_A, T_B)$ dan mengeksekusi kueri pada seluruh model bisnis terdaftar di `TenantScopeRegistry`.
   - Menguji skenario:
     - Kueri dalam konteks $T_A$ wajib menghasilkan tepat 0 baris data milik $T_B$.
     - Upaya mengakses data $T_B$ secara langsung menggunakan ID dalam konteks $T_A$ wajib menghasilkan status `403 Forbidden` (`TENANT_CROSS_BOUNDARY_LEAK`).
2. **Fail-Closed & Alarm Insiden:**
   - Jika ditemukan kebocoran data sekecil apapun (kebocoran $\ge 1$ baris):
     - Probe keluar dengan exit code `1`.
     - Insiden dicatat ke `core_tenant_scope_bypasses` dengan tingkat keparahan `CRITICAL`.
     - Alert otomatis diteruskan ke tim keamanan platform dan diekspos pada endpoint `/admin/tenancy/coverage`.

---

## Konsekuensi

- **Positif:** Verifikasi isolasi data aktif berkelanjutan, deteksi regresi kueri sebelum insiden produksi.
- **Negatif:** Beban kueri tambahan pada saat eksekusi probe harian (dijalankan di luar jam sibuk).
- **Kepatuhan:** Diuji pada `AccessLifecycleTest` dan terintegrasi pada status health-check sistem.

