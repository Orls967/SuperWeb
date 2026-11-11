# ADR-0051: Multi-Tenancy via TenantContext & TenantScopeRegistry Tanpa Mengedit Model (W8-D51)

## Status
Diterima (Accepted)

## Konteks
Seluruh modul bisnis (Mall, Logistics, Contract, Asset, Procurement, Esg, Distribution, Manufacturing, Core) menyimpan data berbagai entitas/tenant dalam tabel bersama, tetapi belum memiliki penegakan isolasi baris (Row-Level Security) di tingkat query ORM. Memaksa mengedit puluhan file model bisnis di modul pemilik melanggar batas zona prompt dan berisiko konflik antarprompt.

## Keputusan
1. **`TenantContext` & Nilai Kepemilikan dari Pengguna Terautentikasi**:
   - `TenantContext` menyimpan `tenantId`, daftar `allowedTenantIds`, `allowedNumericTenantIds`, `allowedEntityIds` (`legal_entity_id`/`entity_id`), serta `allowedPartyIds` (`party_id`). Nilai diambil dari `core_tenant_memberships` milik pengguna terautentikasi atau diteruskan eksplisit ke job/command.
2. **`TenantScopeRegistry` + `TenantScopedModelProvider`**:
   - Modul mendaftarkan model yang sudah memiliki kolom tenant/entitas/party fisik melalui kontrak `TenantScopedModelProvider`.
   - `CoreServiceProvider::booted()` memasang Eloquent Global Scope `TenantIsolationScope` secara dinamis via `ModelClass::addGlobalScope(...)` tanpa menyentuh file kelas model bisnis mana pun.
   - Model yang belum memiliki kolom pemisah dicatat secara jujur sebagai cakupan belum terpasang pada laporan command `api:tenancy-coverage` dan UI `/admin/tenancy/coverage` serta Register Minus.
3. **Fail-Closed & Audit Bypass Tercatat**:
   - Dalam konteks penegakan aktif (seluruh HTTP web/API v2, antrean job, command terikat tenancy, dan probe penetrasi), ketiadaan `TenantContext` menyebabkan query selalu menghasilkan 0 baris (`whereRaw('1 = 0')`) atau melempar `TenantContextMissingException`.
   - Pengecualian ruang lingkup hanya tersedia via `TenantScopeRegistry::withoutTenantScope(string $ability, string $reason, callable $fn)` yang otomatis menulis baris audit ke `core_tenant_scope_bypasses`.

## Konsekuensi
- Nol modifikasi berkas model bisnis lintas modul dan siap dipakai ulang oleh probe isolasi harian di Fase 188.
