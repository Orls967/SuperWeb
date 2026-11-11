# Definition of Ready (DoR) — Fase 55: Enterprise API v2, EDI, Webhook & Multi-Tenancy

**ID Fase:** Fase 55 (W8-P4.2)  
**Modul Pemilik Inti:** `Core` (`core_`) — adapter API/EDI lintas modul berada di modul pemilik masing-masing lewat kontrak.  
**Tautan L.0:** [`docs/dor/bukti-l0-fase-55.md`](bukti-l0-fase-55.md)  
**Target Garis Logika (`B1`):** ≥ 7.000 baris (target RAB: 7.450–10.800 baris, margin ≥ +6%)  
**Target Dokumentasi (`B3`):** ≥ 1.500 baris tulis-tangan (target: ≥ 1.650 baris, margin ≥ +10%)

---

## 1. Rujukan Kanonik (`docs/KONSEP.md` & `docs/PROGRESS.md`)

- `docs/KONSEP.md:59-68` (§A0): definisi vertical slice lengkap, modul pemilik prefiks, tingkat simulasi **S2/S3**, dan skala **T1**.
- `docs/KONSEP.md:70-100` (§A1): registry prefiks `core_` eksklusif di `Core`, larangan tabel asing, serta batas `intg_` pada `Integration`.
- `docs/KONSEP.md:162-191` (§A4.1–A4.5): konvensi event `{domain}.{entitas}.{kejadian}.v{n}`, consumer group Outbox/Event Spine, kontrak lintas modul, serta batas integrasi eksternal simulasi S3 (timeout, allowlist anti-SSRF, status bukan palsu).
- `docs/KONSEP.md:192-199` (§A5): command audit dua sumber (`api:audit`) dan fixture korupsi wajib gagal.
- `docs/KONSEP.md:200-207` (§A6): autentikasi Sanctum + abilities, rahasia API key tersimpan sebagai digest HMAC berkunci (hanya tampil sekali), secret webhook terenkripsi.
- `docs/KONSEP.md:208-211` (§A7): halaman daftar, detail, aksi, item `MenuRegistry`, dan responsivitas 375 px.
- `docs/KONSEP.md:269-290` (§A13): larangan X2 (fitur baru di `Integration`), X3 (`float` untuk token/uang), X4 (`Str::uuid()`/`now()` pada idempotensi), X7 (boolean kontrol), X9 (delivered/accepted palsu), X10 (enkripsi palsu), X14 (`Carbon::setTestNow`), X16 (`DB::table` lintas prefiks), X21 (salin kerangka `Integration`), X25 (default diam-diam).
- `docs/PROGRESS.md:6450-6567`: item 55.1–55.10 seluruhnya berstatus `[ ]` pasca audit forensik F-03 dan dikerjakan ulang penuh pada fase ini.

---

## 2. Identitas, Ruang Lingkup Skema, Kontrak & Peran

### 2.1 Tabel Baru (`core_` di `Core` + Tabel Arsip Aditif di Modul Pemilik)
Seluruh kolom `*_id` memakai foreign key nyata (A12 = 0) kecuali kolom pengenal eksternal pengecualian resmi (`tenant_id`, `correlation_id`, `idempotency_key`):
1. `core_tenants` (`id` PK string, `code` unik, `name`, `numeric_slot`, `entity_uuid`, `party_uuid`, `is_sandbox`, `is_active`).
2. `core_tenant_memberships` (`id`, FK `user_id -> users`, FK `tenant_id -> core_tenants`, `allowed_entities` JSON, `allowed_parties` JSON, `is_default`).
3. `core_tenant_scope_bypasses` (`id`, FK nullable `actor_user_id -> users`, FK nullable `tenant_id -> core_tenants`, `ability`, `model_class`, `reason`, `created_at`).
4. `core_api_clients` (`id`, FK `tenant_id -> core_tenants`, FK nullable `user_id -> users`, `code` unik, `name`, `legal_entity_name`, `tier`, `capacity`, `refill_per_sec`, `daily_quota`, `bucket_milli_tokens`, `bucket_last_ms`, `is_active`, `is_sandbox`).
5. `core_api_client_keys` (`id`, FK `api_client_id -> core_api_clients`, FK nullable `rotated_from_id -> core_api_client_keys`, FK nullable `created_by -> users`, `sanctum_token_id`, `key_prefix`, `key_digest`, `abilities` JSON, `status`, `expires_at`, `revoked_at`).
6. `core_api_idempotency_records` (`id`, FK `tenant_id -> core_tenants`, `client_code`, `route_name`, `http_method`, `idempotency_key`, `request_hash`, `response_status`, `response_body`, `headers` JSON, `expires_at`; unik `[client_code, route_name, http_method, idempotency_key]`).
7. `core_api_usage_events` (`id`, FK `api_client_id -> core_api_clients`, `tenant_id`, `route_uri`, `http_method`, `http_status`, `allowed`, `retry_after_ms`, `occurred_at`) & `core_api_usage_rollups` (`id`, FK `api_client_id -> core_api_clients`, `bucket_hour`, `total_requests`, `allowed_requests`, `throttled_requests`, `status_5xx_requests`; unik `[api_client_id, bucket_hour]`).
8. `core_webhooks` (`id`, FK `tenant_id -> core_tenants`, FK nullable `api_client_id -> core_api_clients`, `code` unik, `name`, `target_url`, `event_topics` JSON, `secret_encrypted`, `previous_secret_encrypted`, `previous_secret_expires_at`, `consecutive_failures`, `circuit_state`, `circuit_opened_at`, `is_active`) & `core_webhook_dispatches` (`id`, FK `webhook_id -> core_webhooks`, FK nullable `event_delivery_id -> core_event_deliveries`, `event_id`, `event_type`, `timestamp_unix`, `payload_json`, `signature_header`, `http_status`, `response_excerpt`, `status`, `attempt_count`, `idempotency_key` unik).
9. `core_edi_interchanges` & `core_edi_transitions` (State Machine 8 status, riwayat append-only dengan FK `edi_interchange_id -> core_edi_interchanges`, `actor_id -> users`).
10. `core_bulk_import_jobs`, `core_bulk_import_errors` (FK `bulk_import_job_id`), `core_bulk_export_jobs` (FK `tenant_id -> core_tenants`, FK nullable `approval_id -> core_approvals`, FK nullable `requested_by -> users`).
11. `core_retention_policies`, `core_retention_runs`, `core_audit_logs_archive`, `core_archive_drill_restores`; ditambah migrasi aditif pemilik: `lgx_tracking_events_archive` (`Logistics`) dan `prc_po_versions_archive` (`Procurement`).

### 2.2 Kontrak Baru di `Modules\Core\Contracts\`
- `ApiResourceProvider` (8 modul: `Procurement`, `Wms`, `Logistics`, `Trade`, `Treasury`, `Finance`, `B2b`, `Distribution`).
- `EdiDocumentHandler` (3 modul: `Distribution` untuk `ORDERS`/`850`, `Procurement` untuk `INVOIC` draft tanpa ledger, `Wms` untuk `DESADV` ASN).
- `BulkImportTarget` (3 modul: `B2b`, `Distribution`, `Trade`).
- `TenantScopedModelProvider` (9 modul berkolom fisik valid: `Core`, `Logistics`, `Mall`, `Contract`, `Asset`, `Procurement`, `Esg`, `Distribution`, `Manufacturing`).
- `RetentionArchiveProvider` (3 modul berbeda: `Core`, `Logistics`, `Procurement`).

### 2.3 Rute, Peran & Event
- **API v2 (`/api/v2/*`):** autentikasi `auth:sanctum` + abilities (`resource:read`, `resource:action`, `edi:write`, `webhook:manage`, `*`) + middleware `ApiV2GatewayMiddleware` (TenantContext, B1 TokenBucket rate limit, Idempotency-Key 24h, RFC 7807 `application/problem+json`, header `Deprecation` untuk padanan v1):
  - `GET /api/v2/catalog`, `GET /api/v2/{resource}`, `POST /api/v2/{resource}/actions/{action}`, `POST /api/v2/edi/interchanges`, `POST /api/v2/webhooks/{code}/test`.
  - Plus endpoint eksplisit per resource agar `OpenApiGenerateCommand` otomatis mengindeks setiap skema v2 tanpa memodifikasi `app/Quality/OpenApi/`.
- **Web Admin (`/admin/api/*`, `/admin/developer`, `/admin/tenancy/*`):**
  - Role `api_admin` (plus `admin`/`superadmin`): `/admin/api/clients`, `/admin/api/usage`, `/admin/api/edi`, `/admin/api/imports`, `/admin/api/webhooks`, `/admin/api/retention`.
  - Role `developer`, `api_admin`: `/admin/developer` (Developer Hub: katalog skema interaktif, playground API, sandbox terisolasi, contoh curl terverifikasi).
  - Role `security_officer`: `/admin/tenancy/coverage` (cakupan model terdaftar vs belum, hasil uji penetrasi terakhir).
- **Events (Outbox + Event Spine W8-P3):**
  - `core.api.client_quota_exceeded.v1`, `core.edi.interchange_rejected.v1`, plus katalog event bisnis eksternal (`order.status_changed.v1`, `payment.settled.v1`, `shipment.tracking_updated.v1`, `purchase_order.issued.v1`, `edi.contrl_generated.v1`, `bulk_export.ready.v1`).
- **Ledger Posting (`U-3`):** **0** (nol jenis posting ledger).

---

## 3. Rencana Anggaran Baris (RAB) — Tabel T-2 Fase 55 (Target B1 ≥ 7.000)

| Lapisan | Rentang Prompt T-2 | Target RAB Fase 55 | Rincian Komponen |
|---|---|---|---|
| Algoritma & Value Object | 1.100–1.600 | 1.250–1.550 | `IntegerTokenBucket` (B1) + `TokenBucketCertificateVerifier`; `WebhookSignatureVerifier` (B2) + `WebhookSignatureCertificateVerifier`; `EdiInterchangeCodec` (B3) + `EdiInterchangeCertificateVerifier`; Value Objects kontrak & envelope (`CanonicalEdiDocument`, `EdiHandleResult`, `ApiPageResult`, `TenantContext`). |
| State Machine | 500–900 | 550–850 | `EdiInterchangeStateMachine` (8 status: `received` .. `archived`), guard transisi handler/CONTRL, riwayat append-only `CoreEdiTransition`, exception domain terstruktur. |
| Aplikasi (Action/Service) | 2.300–3.200 | 2.500–3.150 | `ApiV2GatewayService`, `CursorPaginationService`, `IdempotencyStoreService`, `ApiClientLifecycleService`, `ApiUsageRollupService`, `WebhookSubscriptionService`, `WebhookDeliveryOrchestrator` (EventDelivery + CB), `EdiProcessingService`, `StreamingBulkImportService` (500/batch + ApprovalEngine >10k), `AsyncZipExportService` (+ XLSX minimal `ext-zip`), `DeveloperSandboxService`, `TenantScopeRegistry`, `TenantPenetrationProbeService`, `RetentionArchiveService`, `ApiV2AuditService`. |
| Integrasi (Kontrak & Provider) | 700–1.100 | 850–1.100 | 5 kontrak di `Modules\Core\Contracts\` + 8 `ApiResourceProvider` + 3 `EdiDocumentHandler` + 3 `BulkImportTarget` + 9 `TenantScopedModelProvider` + 3 `RetentionArchiveProvider` + `@deprecated` docblock di `IntegrationService`. |
| Posting Ledger | 0 | 0 | Nihil — sesuai aturan U-3; EDI hanya meneruskan DRAFT ke modul pemilik. |
| HTTP (Controller, FormRequest, Policy, Resource) | 1.000–1.500 | 1.100–1.450 | `ApiV2ResourceController`, `ApiV2EdiController`, `ApiV2Middleware`, `ApiClientController`, `ApiUsageController`, `EdiController`, `BulkImportExportController`, `WebhookPortalController`, `DeveloperHubController`, `TenancyCoverageController`, `RetentionController`, FormRequests, Policies (anti-IDOR), ProblemDetails + CursorEnvelope Resources. |
| Command & Laporan CSV | 600–1.000 | 700–950 | `ApiKeysRotateCommand`, `ApiUsageRollupCommand`, `ApiImportCommand`, `ApiExportPurgeCommand`, `ApiArchiveRunCommand`, `ApiArchiveDrillCommand`, `ApiTenancyCoverageCommand`, `ApiAuditCompositeCommand` (hook Artisan ke `api:audit`), `ApiAuditCorruptionFixture`, CSV Export Builder & Controller. |
| Model | 400–700 | 450–680 | `CoreTenant`, `CoreTenantMembership`, `CoreTenantScopeBypass`, `CoreApiClient`, `CoreApiClientKey`, `CoreApiIdempotencyRecord`, `CoreApiUsageEvent`, `CoreApiUsageRollup`, `CoreWebhook`, `CoreWebhookDispatch`, `CoreEdiInterchange`, `CoreEdiTransition`, `CoreBulkImportJob`, `CoreBulkImportError`, `CoreBulkExportJob`, `CoreRetentionPolicy`, `CoreRetentionRun`, `CoreArchiveDrillRestore`. |
| **Total Estimasi B1** | **≥ 7.000** | **7.400–10.680** | Margin aman ≥ +5% di atas ambang keras 7.000. |

---

## 4. Keputusan Arsitektur (W8-D45 .. W8-D52)

1. **W8-D45** (`ADR-0045`): Envelope `/api/v2`, kursor stabil keyset base64url, Problem Details RFC 7807/9457, dan header `Deprecation` bagi resource padanan v1.
2. **W8-D46** (`ADR-0046`): Penegakan `Idempotency-Key` persisten 24 jam dengan deteksi konflik payload (`409` problem) pada seluruh mutasi API v2.
3. **W8-D47** (`ADR-0047`): Pembatasan laju integer milli-token (`IntegerTokenBucket` B1) per tier Silver/Gold/Platinum dengan `Retry-After` RFC 9110.
4. **W8-D48** (`ADR-0048`): Pengiriman webhook B2B melalui `EventDelivery`/DLQ W8-P3, rotasi secret ganda 7 hari (`WebhookSignatureVerifier` B2), dan circuit breaker 5/300s.
5. **W8-D49** (`ADR-0049`): Subsistem EDI simulasi (`EdiInterchangeCodec` B3 + State Machine 8 status + `CONTRL`/`997` + `EdiDocumentHandler` 3 modul tanpa posting ledger).
6. **W8-D50** (`ADR-0050`): Pipeline impor CSV streaming per-baris (`BulkImportTarget`, batch 500, approval >10k, resume idempoten) dan ekspor ZIP bertanda tangan (+ XLSX `ext-zip`).
7. **W8-D51** (`ADR-0051`): Isolasi data multi-tenant tanpa mengedit kelas model melalui `TenantContext` + `TenantScopeRegistry` fail-closed.
8. **W8-D52** (`ADR-0052`): Kebijakan retensi data transaksional > 5 tahun ke tabel `{tabel}_archive`, drill pemulihan non-destruktif, dan perluasan `api:audit`.

