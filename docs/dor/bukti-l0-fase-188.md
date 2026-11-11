# Bukti Kondisi Awal (L.0) — Fase 188: Tata Kelola Identitas, Akses & Multi-Tenancy

**Tanggal Inspeksi:** 2026-11-12  
**Baseline SHA:** `a2d3b634984fa35e5e8c5bf100a57314c78f0b5f` (Merge commit PR #113 Fase 55)  
**Branch Kerja:** `feature/w8-p4-batas-api-tenancy`  
**Status Eksekusi:** Solo mode (tidak ada PR lain yang terbuka di repo).

---

## 1. Inventaris Kondisi Awal Codebase (L.0)

| Komponen / Subjek | Lokasi / Perintah | Status Nyata L.0 | Catatan Verifikasi & Mitigasi |
|---|---|---|---|
| Status Master & CI | `gh pr list`, `git log -1` | **Bersih di `a2d3b634`** | PR #113 (Fase 55) merged sukses, 16/16 check-runs CI hijau; 0 PR terbuka. |
| Baseline `arch:scan` | `php artisan arch:scan` | **Bersih (0 baru, 0 basi)** | A1=0, A2=15, A12=300, aturan lainnya 0. |
| UI Linter | `php artisan ui:lint` | **0 pelanggaran** | 37 file Blade tersertifikasi desain sistem `x-ds.*`. |
| OpenAPI 3.1 & Docs | `openapi:validate`, `docs:inventory` | **Sinkron 100%** | Spesifikasi OpenAPI 3.1 valid dan CODEBASE.md sinkron dengan 705 migrasi. |
| Layanan warisan `IdentityTenancyService` | `modules/Integration/Application/Services/IdentityTenancyService.php` | **Ada (kerangka lama)** | 159 baris mentah `DB::table('idp_*')` dengan `Str::random()` non-deterministik dan tanpa state machine; dibekukan di `integration-freeze.json` (IZIN-4: dilarang diubah). |
| Migrasi warisan `idp_*` | `modules/Integration/database/migrations/2026_10_08_188001_create_identity_tenancy_tables.php` | **Ada (dibekukan)** | Tabel lama `idp_*` dibekukan; seluruh model baru Fase 188 memakai prefiks kanonikal `core_` di `modules/Core/`. |
| Evaluator ABAC Murni | `grep -rn "DenyOverridesEvaluator" modules/` | **Belum ada** | Dibangun dari nol di `Modules\Core\Domain\Algorithms\Access\DenyOverridesEvaluator.php` (C1) bebas floating-point & I/O. |
| Linker Identitas Berbobot | `grep -rn "WeightedIdentityLinker" modules/` | **Belum ada** | Dibangun dari nol di `Modules\Core\Domain\Algorithms\Access\WeightedIdentityLinker.php` (C2) dengan consent ganda dan ambang $\ge 90$. |
| Agregator Eksposur Vendor | `grep -rn "VendorExposureAggregator" modules/` | **Belum ada** | Dibangun dari nol di `Modules\Core\Domain\Algorithms\Access\VendorExposureAggregator.php` (C3) berbasis integer minor units IDR. |
| State Machine Akses | `grep -rn "AccessGrantStateMachine" modules/` | **Belum ada** | Dibangun di `Modules\Core\Domain\StateMachines\AccessGrantStateMachine.php` (8 status siklus hidup). |
| Command Audit Dua-Sumber | `grep -rn "access:audit" app/ modules/` | **Belum ada** | Dibangun di `Modules\Core\Console\Commands\AccessAuditCommand.php` + `AccessAuditCorruptionFixture.php`. |

---

## 2. Pemetaan Model Bisnis Multi-Tenancy & Akses 30 Lini

Pemeriksaan model bisnis aktif yang tercakup dalam matriks multi-tenancy dan penautan identitas:

| Lini Bisnis / Modul | Model Bisnis | Kolom Partisi / Tenant | Status Integrasi Fase 188 |
|---|---|---|---|
| `Core` | `CoreTenant`, `CoreUser` | `tenant_id`, `id` | Sumber identitas induk & keanggotaan tenant |
| `Procurement` | `PurchaseOrder`, `Supplier` | `tenant_id`, `entity_id` | Sumber eksposur tranche PO terbuka |
| `Distribution` | `DistOrder`, `Distributor` | `party_id`, `tenant_id` | Penautan mitra & batas pemesanan terpadu |
| `Logistics` | `Shipment`, `DockAppointment` | `tenant_id` | Verifikasi penerimaan barang belum difaktur |
| `Wms` | `Warehouse`, `StorageLocation` | `tenant_id` | Isolasi akses fisik & otorisasi ABAC site |
| `B2b` | `WholesaleCatalog`, `B2bOrder` | `vendor_id`, `tenant_id` | Akses katalog grosir ber-scope entitas |
| `Finance` | `Loan`, `CreditLine` | `tenant_id`, `entity_id` | Evaluasi limit kredit gabungan vendor |
| `Mall` | `Lease`, `TenantMerchant` | `tenant_id` | Penautan merchant & consent sharing lintas mall |
| `Contract` | `Contract`, `LegalEntity` | `legal_entity_id` | Scope kontrak hukum & klausul kepatuhan |
| `Asset` | `Asset`, `Equipment` | `legal_entity_id` | Otorisasi ABAC tingkat site/fasilitas |
| `Esg` | `EsgEmission` | `entity_id` | Pelaporan emisi ber-scope wilayah/pabrik |

---

## 3. Komitmen Invarian & Batas Toleransi Mutlak

1. **Anti-Padding Standar:** M-2 $\ge 55\%$, M-3 $\le 20\%$, M-4 = 0, M-5 = 0.
2. **Tanpa Ledger Posting (U-3):** Fase 188 murni tata kelola akses dan penautan identitas; RAB = 0 transaksi pembukuan.
3. **Pencabutan Sub-Detik:** Pencabutan `AccessGrant` (`revoked`) memicu invalidasi stempel izin seketika tanpa jeda polling.
4. **Deny-Overrides Fail-Closed:** Permintaan tanpa aturan eksplisit atau yang memiliki minimal satu aturan `DENY` otomatis ditolak `403 Forbidden`.
5. **Double Consent Invariant:** Penautan identitas pelanggan lintas vertikal menolak berbagi data jika salah satu pihak belum memberikan `consent_granted`.

