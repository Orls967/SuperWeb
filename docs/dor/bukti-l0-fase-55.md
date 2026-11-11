# Bukti Kondisi Awal (L.0) — Fase 55: API v2, EDI & Multi-Tenancy

**Tanggal Inspeksi:** 2026-11-12  
**Branch Kerja:** `feature/w8-p4-batas-api-tenancy` (`c8f03af86f5a5eabd09c77da9b9fd6922fa60f51`)  
**Status PR #112 (Fase R4):** Menunggu 2 job CI tersisa (`Static & Architecture Gate`, `SQLite Full Gate Suite`); `MySQL 8`, `Suite Regresi`, dan `Pagar integritas` sudah hijau.

---

## 1. Tabel Inventaris Kondisi Nyata

| Komponen / Subjek | Lokasi / Perintah | Status L.0 | Catatan Verifikasi |
|---|---|---|---|
| `quality:phase-metrics` | `modules/Platform/Domain/Actions/CalculatePhaseMetricsAction.php` | **Ada (berbeda dari rencana)** | Hanya opsi `{--path=} {--phase=} {--check}`; tanpa `--from`/`--ui`/`--json`. Pengukur resmi tetap gerbang T-1/T-1b + `/tmp/t3_metrics.py`. |
| `docs/gates/ambang.json` | `ls docs/gates` | **Tidak ada** | Ambang M-2..M-5 mengikuti spesifikasi eksplisit prompt (`M-2 >= 55%`, `M-3 <= 20%`, `M-4 = 0`, `M-5 = 0`). |
| `docs/gates/fase-55.md` | `docs/gates/fase-55.md:1-87` | **Ada (pra-R0 / dibatalkan F-03)** | Menautkan commit `82d19dd9` dan `B2bOperationsSliceTest`; seluruh item 55.1–55.10 berstatus `[ ]` di `docs/PROGRESS.md:6455-6567`. |
| `interface TaskInboxProvider` | `grep -rn "interface TaskInboxProvider" modules app` | **Tidak ada** | Digantikan `Modules\Core\Application\Actions\TaskInbox\TaskInboxService::createTask(array $data): CoreTask`. |
| Ekstensi PHP `ext-zip` | `php -m \| grep -i zip` | **Ada (`zip`)** | Tersedia built-in sehingga `ZipArchive` dan pembaca XLSX minimal murni PHP layak tanpa dependensi Composer baru (IZIN-2). |
| Baseline `arch:scan` pasca-R4 | `php artisan arch:scan` | **Ada & bersih** | `A1=0`, `A2=15`, `A12=300`, `A3..A11,A13=0`, `Baru=0`, `Basi=0`. |
| Event Spine & Outbox W8-P3 | `Modules\Core\Domain\Models\{CoreEventDelivery,CoreConsumerInbox,OutboxMessage}` & `EventDeliveryStateMachine` | **Ada & benar** | Backoff A5 (`DeterministicBackoff`) dan DLQ (`DeadLetterQueueService`) dipakai ulang untuk mekanisme retry & DLQ webhook Fase 55. |
| Layanan referensi Integration | `modules/Integration/Application/Services/IntegrationService.php:15-205` | **Ada (kerangka lama)** | Mencatat webhook `delivered`/HTTP 200 saat response sukses namun belum ada rotasi ganda, replay window 300s, circuit breaker 5/300s, cursor keyset, B1 bucket, maupun global scope multi-tenant; hanya diberi `@deprecated` dan tidak dihapus (IZIN-3). |
| Migrasi `intg_*` | `modules/Integration/database/migrations/2026_10_06_550100_create_intg_tables.php` | **Ada (kerangka lama)** | Dibekukan (`IntegrationFreezeTest`), tidak diubah/dihapus; tabel baru dibangun berprefiks `core_` di `modules/Core/`. |
| Layanan EnterpriseAPI Core | `modules/Core/Application/Services/EnterpriseApiV3Service.php:9-98` | **Ada (parsial)** | Hanya helper RFC 7807 statis, rate limiter cache sederhana, dan lock idempotency cache; belum ada API v2 resource gateway, persistent idempotency 24 jam, maupun B1 milli-token integer bucket. |
| Command `api:audit` & fixture | `modules/Integration/Console/Commands/AuditIntegrationCommand.php:17-73` & `app/Quality/Audit/Fixtures/ApiAuditCorruptionFixture.php` | **Ada (cakupan awal)** | Memeriksa tabel `intg_*` dan `pe_embedded_finance_txs`. Nama command tunggal wajib tetap (`CommandSignatureUniqueTest`); perluasan Fase 55 dikaitkan melalui hook Artisan Core tanpa mengedit `modules/Integration/**`. |
| OpenAPI 3.1 Generator | `app/Console/Commands/OpenApiGenerateCommand.php` & `app/Quality/OpenApi/OpenApiSpecGenerator.php` | **Ada & terotomasi** | Mengintropeksi seluruh rute terdaftar Laravel termasuk rute `/api/v2/*` tanpa mengubah struktur v1 (`modules/Logistics/routes/api.php:20-49`). |
| Vektor B2 & B3 | Verifikasi PHP `hash_hmac` & parser EDIFACT/X12 | **Terverifikasi** | Tiga hash HMAC vektor B2 (`0d74ebc9...`, `d3abc86c...`, `c94593a0...`) cocok 100%. Vektor EDIFACT B3 tepat 13 segmen total dan 11 segmen `UNH..UNT`. **Catatan X12 prompt (PR2):** string X12 di teks prompt (`ST8500001~...` dan `AK9A11*1~`) kehilangan pemisah `*` tunggal akibat rendering Markdown *italic*; format X12 kanonik tetap memakai pemisah elemen `*` (`ST*850*0001~...` dan `AK1*PO*1~...AK9*A*1*1*1~`), konsisten dengan referensi `IntegrationService.php:111`. |

---

## 2. Survei Kolom Tenant / Entitas pada Model Bisnis Nyata

Pemeriksaan skema migrasi nyata pada basis kode saat ini untuk menentukan kandidat **`TenantScopedModelProvider`** (hanya model yang **sudah** memiliki kolom tenant/entitas/party di DB):

| Modul | Model | Tabel | Kolom Aktual & Tipe Migrasi | Dipasang Scope? |
|---|---|---|---|---|
| `Core` | `PartnerWebhook` | `core_partner_webhooks` | `tenant_id` (`string(64)`) | Ya (baru & eksisting Core) |
| `Logistics` | `DockAppointment` | `lgx_dock_appointments` | `tenant_id` (`unsignedBigInteger nullable`) | Ya (`tenant_int`) |
| `Mall` | `Invoice`, `Lease` | `mall_invoices`, `mall_leases` | `tenant_id` (`unsignedBigInteger`) | Ya (`tenant_int`) |
| `Contract` | `Contract` | `ctr_contracts` | `legal_entity_id` (`uuid -> pty_legal_entities`) | Ya (`entity`) |
| `Asset` | `Asset` | `ast_assets` | `legal_entity_id` (`uuid nullable`) | Ya (`entity`) |
| `Procurement` | `Requisition` | `prc_requisitions` | `legal_entity_id` (`uuid nullable`) | Ya (`entity`) |
| `Esg` | `EsgEmission` | `esg_emissions` | `entity_id` (`string`) | Ya (`entity`) |
| `Distribution` | `Distributor` | `dist_distributors` | `party_id` (`uuid`) | Ya (`party`) |
| `Manufacturing` | `Plant` | `mfg_plants` | `legal_entity_id` (`uuid`) | Ya (`entity`) |
| `Treasury` | `BankAccount` | `trs_bank_accounts` | `entity_code` (atribut virtual `entity_id`) | **Tidak** — tidak memiliki kolom fisik `entity_id`/`tenant_id`; masuk Register Minus & laporan `api:tenancy-coverage`. |

Model target **`ApiResourceProvider`** (8 modul, syarat ≥ 6): `Procurement` (`PurchaseOrder`), `Wms` (`Warehouse`), `Logistics` (`Shipment` + penanda `Deprecation` untuk padanan `/api/v1/logistics/shipments`), `Trade` (`ImportOrder`), `Treasury` (`ExchangeRate`), `Finance` (`Loan`), `B2b` (`WholesaleCatalog`), dan `Distribution` (`DistOrder`).  
Model target **`EdiDocumentHandler`** (3 modul pemilik): `Distribution` (`ORDERS` / X12 `850` $\to$ `DistOrder` draft), `Procurement` (`INVOIC` $\to$ `SupplierInvoice` status `pending` DRAFT tanpa posting ledger), `Wms` (`DESADV` $\to$ `DockAppointment` inbound ASN).  
Model target **`BulkImportTarget`** (3 modul pemilik): `B2b` (`WholesaleCatalog`), `Distribution` (`HetPrice`), `Trade` (`HsCode`).  
Model target **Retensi & Arsip `> 5 tahun`** (3 modul berbeda): `Core` (`core_audit_logs` $\to$ `core_audit_logs_archive`), `Logistics` (`lgx_tracking_events` $\to$ `lgx_tracking_events_archive`), `Procurement` (`prc_po_versions` $\to$ `prc_po_versions_archive`).
