# CODEBASE.md — Peta Codebase Superwebsite (BACA INI DULU)

> **Fungsi dokumen:** satu-satunya pintu masuk untuk memahami kode. Sesi baru **membaca file ini, bukan memindai seluruh codebase**. Buka file sumber hanya untuk bagian yang akan diubah.
> **Kewajiban:** setiap perubahan (modul, tabel, rute, command, event, contract, role, config, keputusan, angka gate) **harus memperbarui file ini pada commit yang sama**. Lihat §14 (Protokol Pembaruan).
> Pelengkap: `docs/PROGRESS.md` (checklist tugas), `docs/DECISIONS.md` (alasan keputusan), `docs/ARCHITECTURE.md` (diagram & invarian), `docs/RUNBOOK.md` (operasi), `docs/AUDIT.md` (hasil gate).

**Terakhir diperbarui:** 2026-10-04 · **Fase selesai terakhir:** 31 (Aset: Penyusutan, Pemeliharaan, Revaluasi & Disposal) · **Berjalan:** — · **Berikutnya:** Fase 32 Produsen & Pemasok (modul sup_)
**Snapshot gate (akhir Fase 31):** 680 test / 3733 assertion (Fase 30: 664/3662), 0 skipped · `bank:reconcile` 0 selisih (128 akun) · `lgx:audit-billing` 0 selisih (29 dok) · `lgx:verify-custody`, `lgx:capacity-check` valid · `super:health-check` 9 pilar HEALTHY · Pint, Vite, arch (10) lulus.

---

## 1. Stack & lingkungan

| Hal | Nilai |
|---|---|
| Framework | Laravel 13 (`laravel/framework ^13.17`), PHP `^8.3` (composer.lock butuh ≥ 8.4; sandbox 8.3 → pakai `platform.php 8.3.6` sementara, **jangan commit composer.lock**) |
| DB | SQLite (dev/test). Uang = integer IDR atau `decimal(36,18)` untuk kripto; **tanpa float** |
| Test | Pest 4 + pest-plugin-arch; `vendor/bin/pest` (≈ 81 file test) |
| Front-end | Blade + Tailwind + Alpine, Vite (`npm run build`) |
| Lib | `brick/math` v1 (`RoundingMode::HalfUp`), `bacon/bacon-qr-code`, Breeze (auth) |
| Autoload | `App\`, `Modules\` → `modules/`, `Database\Seeders\` |
| Lint | `vendor/bin/pint` |

Gotcha yang sudah pernah menggigit: `event(new X)` bukan `X::dispatch` bila event tak punya trait Dispatchable · `RoundingMode::HalfUp` (bukan `HALF_UP`) · TopUpAction idempotent per key → pakai key unik per top-up di fixture · `sed` jangan dipakai untuk edit file PHP struktural.

## 2. Pola arsitektur (wajib diikuti)

- **Modular monolith** `modules/{Nama}/` — struktur standar: `Application/{Actions,Services,Queries,Listeners}`, `Contracts/`, `Console/(Commands)`, `Domain/{Enums,Events,Exceptions,Models,DTOs}`, `Http/Controllers`, `database/{migrations,seeders}`, `resources/views` (namespace view `{modul}::`), `routes/web.php`, `tests/Feature`, `{Nama}ServiceProvider.php`.
- **Action/Service**: logika bisnis di Action (satu use-case, `__invoke`/`execute`, extends `Shared\Application\BaseAction` bila relevan), mutasi dalam `DB::transaction`. **Controller tipis**, tidak boleh memakai `DB` facade (ditegakkan arch test).
- **Antar-modul hanya lewat Contract / Domain Event / Ledger / PaymentGateway**. Tidak boleh impor `Domain` modul lain (`tests/Architecture/ModuleBoundariesTest.php`, 10 aturan).
- **Idempotensi**: setiap posting ledger & event handler memakai idempotency key deterministik (per sumber).
- **State machine** lewat enum (`ShipmentStatus`, `FleetStatus`, `BookingStatus`, …) dengan guard transisi.
- **Hash-chain append-only**: `core_vehicle_events` (Vehicle Passport) & tracking event Logistik. Model menolak update/delete.
- **Menu**: `Shared\Application\MenuRegistry` + `MenuItem`, didaftarkan di ServiceProvider tiap modul (visibilitas per role).
- **Role** = RBAC tabel (`roles`, `permissions`, `role_permission`, `user_role` — multi-role + scope entitas) + legacy kolom `users.role` sebagai fallback/mirror; `Gate::before` mengecek RBAC permission; middleware `role:` (`app/Http/Middleware/CheckRole.php`) mengecek kedua sumber; Policy (Logistics). Seeder `RbacSeeder` backfill dari `users.role`. Admin UI di `/admin/rbac`.

## 3. Peta modul

| Modul | Prefix tabel | Prefix rute | Isi pokok | Ukuran |
|---|---|---|---|---|
| Shared | – | – | `MenuRegistry`, `BaseAction` (dengan helper `audit()` & `outbox()`), VO `Money`, trait, komponen Blade, halaman umum | 18 php |
| Core | `core_` | `/admin/*`, publik passport | `Vehicle`, `VehicleEvent` (hash-chain), `ActivityLog`, `AuditLog`, `PlatformNotification`, **`Role`, `Permission`** (RBAC), **`OutboxMessage`**, `OutboxSubscription`, `OutboxDispatch`, **`DocumentSequence`**, **`DocumentAttachment`**, **`Approval`**, **`ApprovalStep`**, **`ApprovalHistory`**, dashboard terpadu, `PlatformSeeder`, `RbacSeeder`; command `core:verify-passports`, `core:process-outbox`, `super:health-check`; **`RbacService`**, **`AuditTrailService`**, **`OutboxBusService`**, **`DocumentNumberingService`**, **`DocumentStoreService`**, **`ApprovalEngineService`**, **`HasRbacRoles`** trait, **`RbacController`**, **`AuditLogController`** | 70 |
| Banking | `bank_` | `/admin/ledger`, wallet | **Ledger double-entry** multi-aset, `LedgerAccount`, wallet, PIN, transfer, top-up, statement/CSV; command `bank:reconcile` | 52 |
| Payment | `pay_` | `/payment` | `PaymentGateway` (charge/hold/capture/release/refund), `Payable`, `PaymentIntent`; command `payment:release-expired-holds` | 18 |
| Inventory | `inv_` | – | `InventoryService`, `StockMovement` append-only, reservasi 2 langkah | 8 |
| Store | `store_` | `/store`, `/admin/store` | Katalog, cart, checkout, order, C2C escrow; cmd `store:cancel-stale-orders`, `store:auto-capture-c2c` | 54 |
| AutoServe | `serve_` (+ `bookings`, `spareparts`, `services`) | root | Bengkel: booking, estimasi, invoice(Payable), backorder | 38 |
| AutoDex | `dex_` (+ `cars`, `brands`, `garages`, `wishlists`) | `/autodex` | Ensiklopedia mobil, garasi, wishlist | 12 |
| Crypto | `crypto_` | – | Simulasi bursa, `PriceEngine`, quote 15 dtk, portfolio, alert; cmd `crypto:tick` | 32 |
| Finance | `fin_` | `/pembiayaan` | HODL-to-Drive: pinjaman beragun kripto, cicilan, LTV monitor/likuidasi; cmd `finance:charge-installments` | 22 |
| Resto | `resto_` (34 tabel) | `/resto` | RM Sari Ranah: outlet, dapur sentral CK-01, resep BOM, HPP (MAC), batch, etalase hidang, POS/shift, rantai pasok, katering, franchise/royalti; cmd `resto:{expire-display,close-day,post-royalty,check-stock}` | 156 |
| Mall | `mall_` (25 tabel) | `/mall` | Duta Mall: leasing, tagihan, tunggakan/denda, utilitas, parkir, footfall, loyalty (points), voucher, event, facility WO, `mall_assets`; cmd `mall:*` (9) | 168 |
| Logistics | `lgx_` (≈ 43 tabel) | `/logistics` | Sari Ranah Express — lihat §6 | 269 |
| Party | `pty_` (11 tabel) | `/party` | Party Master & Badan Hukum: `Party` (orang/perusahaan), `LegalEntity` (holding/anak/cabang), `PartyRole`, `PartyAddress`, `PartyContact`, `PartyBankAccount`, `KycDocument`, `SanctionCheck`, `CreditProfile`, `pty_merge_logs`; actions KYC submit/approve/reject; screening sanksi fuzzy similar_text + hash; credit scoring 0-100; command `party:backfill-links`, `party:remind-expiring-docs` | 25 |
| Asset | `ast_` (16 tabel) | `/assets` | Aset Inti: `AssetCategory` (umur ekonomis & metode PSAK 16 simulasi), `AssetLocation` (hirarki entitas→site→area), `Asset` (register + book value), `AssetEvent` (hash-chain append-only SHA-256), `AssetStocktake` (opname per siklus), `AssetAssignment` (check-out/in), `AssetInsurance` (polis+klaim); `AssetService` (nomor gapless `AST/{ENT}/`, posting `ast:fixed_assets`, mutasi via ApprovalEngine, opname idempoten, DocumentStore untuk foto/dokumen); command `ast:verify-chain`, `ast:backfill-links` (348 aset lama: mall_assets, lgx fleet, idempoten); role `asset_manager`, `auditor`; tautan `asset_id` nullable di 6 tabel legacy; **Fase 31**: `DepreciationService` (SL/saldo menurun/unit produksi, buku komersial+fiskal terpisah, key `ast:depreciate:{id}:{periode}:{buku}`), `RevaluationService` (approval asset_manager+admin → revaluasi/impairment/disposal, laba-rugi = proceeds − book value), `WorkOrderService` (trigger time/usage, expense vs capitalized → `ast:fixed_assets`), `LeaseService` (PSAK 73 simulasi: PV liabilitas, bunga per periode), `AssetTcoService` (TCO + rekomendasi ganti), `AssetAuditService` (subledger↔ledger, hanya aset ber-posting `ast:acquire:*`); command `ast:depreciate`, `ast:audit {--tco}` (jadwal bulanan); pilar `assets` ke-9 di `super:health-check` |
| Contract | `ctr_` (13 tabel) | `/contracts` | Kontrak inti: `Contract`, `ContractParty`, `ContractClause`, `ContractVersion` (SHA-256 hash-chain append-only), `ContractMilestone`, `ContractAttachment` (Core DocumentStore checksum/retensi), `ContractTemplate`, `ClauseTemplate`; `ContractService` (gapless numbering, state machine, approval engine, e-sign simulasi, append/verify chain, diff); command `contracts:verify-chain`, `ctr:remind`; role `contract_manager`, `legal`; tabs kontrak: overview/pihak/klausul/obligasi/**keuangan**/lampiran/versi; **Fase 29**: `ContractFinanceService` (jadwal termin/advance/retensi, denda waiver via approval, eskalasi indeks terbatas-parser), `ContractAmendmentService` (diff + versi chain + regenerate jadwal), `ContractUsageService`/`UsageSync` (plafon idempoten, early warning 80/100%), `ContractRiskService` (skor 0–100), `ContractReportService` (eksposur per jenis/pihak, aging, `ctr:audit`), `ContractRateResolver` (rate card kontrak menang via `RateCardOverrideResolver`); command `ctr:audit`; laporan `/contracts/reports` | 47 |

Tabel non-prefiks lama: `users`, `bookings`, `spareparts`, `services`, `cars`, `brands`, `garages`, `wishlists`, `platform_*`.

## 4. Role & akun demo

Role (`users.role` + RBAC tabel `roles`): `admin`, `customer`, `mekanik`, `tenant`, `outlet_manager`, `kitchen`, `cashier`, `shipper`, `driver`, `dispatcher`, `hub_operator`, `logistics_admin`, `party_manager`, `contract_manager`, `legal`, `asset_manager`, `auditor`. RBAC mendukung multi-role per user dengan scope entitas (contoh: cashier scoped ke outlet). 17 role (termasuk `party_manager`, `contract_manager`, `legal`, `asset_manager`, `auditor`) tersemai di `RbacSeeder`.
Seeder: `DatabaseSeeder` → Banking, Platform, Crypto, Mall, Resto, Logistics (+ `LogisticsFinanceSeeder`). Akun demo contoh: `admin@autoserve.test`, `customer@autoserve.test`, `mekanik@autoserve.test` (password `password`). `DemoLargeSeeder` = data besar lintas modul; `LogisticsLargeSeeder` = skala logistik (Fase 25).

## 5. Ledger & uang (inti sistem)

- **Contract** `Modules\Banking\Contracts\Ledger`: `post(PostingDTO)` dengan `PostingEntryDTO[]`; Σ entri = 0 per aset; idempotency key unik; lock akun urut ascending (anti-deadlock).
- **Konvensi tanda:** kredit **+**, debit **−**. Akun piutang bersaldo negatif (mis. `loan_receivable:IDR`).
- `AccountKind`: wallet, revenue, escrow, clearing, collateral, exchange, loan_receivable, fee, expense, cash, inventory, ap, deposit, liability, asset, points.
- `TransactionType` (Banking enum, ±40 case; nilai ≤ 32 char; Logistik memakai `LOGISTICS_*`).
- Akun sistem Logistik (`Application/Services/LogisticsLedger.php`): `lgx:unearned_freight`, `lgx:freight_revenue`, `lgx:cod_fee_revenue`, `lgx:carrier_cost`, `lgx:claims_expense`, `lgx:dd_revenue`, `lgx:customs_duty_payable`, `lgx:fuel_expense`, `clearing:external:IDR`.
- Akun escrow pembayaran `escrow:payment:IDR`; kolateral `escrow:finance:collateral:{ASSET}`.
- Akun & tipe kontrak (Fase 29): `ctr:advance` (liabilitas uang muka), `ctr:retention_receivable`/`ctr:retention_payable`, `ctr:penalty_revenue:IDR`, `ctr:revenue:{contract_id}`; TransactionType `ctr_advance|ctr_payment|ctr_retention|ctr_penalty|ctr_expense`; aset `ast_acquire|ast_dispose|ast_transfer|ast_depreciation|ast_revaluation|ast_impairment|ast_work_order|ast_lease_amort` dengan akun `ast:fixed_assets`, `ast:accumulated_depreciation` (CONTRA_ASSET), `ast:depreciation_expense`, `ast:fiscal_*` (buku fiskal simulasi), `ast:right_of_use`, `ast:lease_liability`, `ast:lease_interest`, `maintenance:asset:IDR`.
- Pembulatan: Brick Math `HalfUp`; PB1 resto 10% pembulatan Rp100; PPN 11% (config `logistics.vat_rate`).
- **Audit:** `bank:reconcile` (Σ=0 & saldo cache = agregat entri), `mall:audit-billing`, `lgx:audit-billing` (15 pemeriksaan, exit 1 bila selisih).

## 6. Modul Logistics (`modules/Logistics`) — detail

Fase 20–25 selesai. Config: `config/logistics.php` (vat_rate, cancellation_fee_idr, insurance_rate/min, cod_fee_rate, cod_settlement_days, sla_hours per service level, claim_window_days, claim_uninsured_multiplier, customs_red_lane_threshold_idr, dll.).

**Enum:** `ShipmentStatus` (state machine), `ServiceLevel`, `TransportMode`, `TruckType`, `TrailerType`, `FleetStatus`, `LocationType`, `Incoterm`, `PaymentTerms`, `ScheduleStatus`, `DeliveryFailureReason`, `ExceptionType` (+CustomsHold), `ExceptionSeverity`.

**Model (domain):** `LogisticsEntity`, `Location`, `Lane`, `Driver`, `Truck`, `Trailer`, `Vessel`, `Aircraft`, `Container`, `Uld`, `ShipperAccount`, `ShipmentLeg`, `HubOperator`, `Schedule`, `CapacityReservation`, `RateCard`/`RateBracket`/`Surcharge`, `Quote`, `Shipment`, `Package`, `Load`/`LoadItem`, `TrackingEvent`, `DispatchAssignment`, `ProofOfDelivery`, `DeliveryAttempt`, `ShipmentException`, `LogisticsInvoice` (kind: freight|dd), `CodCollection`, `Carrier`/`CarrierPayment`, `Claim`, `DdTariff`/`ContainerDwell`, `HsTariff`/`CustomsDeclaration`, `FuelLog`, `TemperatureReading`, `DockAppointment`, `WebhookEndpoint`/`WebhookDelivery`.

**Action (45)** per domain: Booking/Quote (`QuoteShipmentAction`, `BookShipmentAction`, `BookPostpaidShipmentAction`, `CancelShipmentAction`, `GenerateMonthlyInvoicesAction`, `PayLogisticsInvoiceAction`) · Jaringan (`PlanShipmentRouteAction`, `ReserveCapacityAction`/`ReleaseCapacityAction`, `ConsolidateLclAction`, `RecordSolasVgmAction`, `ProcessHub{InboundScan,Sort,Outbound}Action`, `RecordTrackingEventAction`) · Dispatch/Driver (`AssignScheduleResourcesAction`, `ReleaseScheduleResourcesAction`, `AssignShipmentToDriverAction`, `ScanPickup`, `StartDelivery`, `CompleteDelivery`, `ReportFailedDelivery` — via `AbstractDriverTaskAction`) · Exception (`RaiseShipmentExceptionAction`, `ResolveShipmentExceptionAction`) · Uang (`RecognizeFreightRevenueAction`, `RecordCodCollection`, `DepositCodCash`, `SettleCod`, `AssignCarrierToLeg`, `AccrueLegCost`, `CompleteShipmentLeg`, `PayCarriers`, `CreateClaim/SubmitClaim/DecideClaim/PayClaim`, `StartContainerDwell`, `AccrueDemurrageDetention`, `EndContainerDwell`, `GenerateDdInvoices`, `SubmitCustomsDeclaration`, `PayCustomsDuty`, `ClearCustoms`, `RecordFuelLog`).

**Service:** `LogisticsLedger` (akun+post+balance), `BillingAuditor`, `DeliverySlaPolicy`, `CodFeeCalculator`, `DwellChargeCalculator`, `CustomsDutyCalculator`, `FuelConsumptionAnalyzer`, `ShipmentMarginReport`.

**Event/Listener:** `ShipmentDelivered` (dipicu dalam transaksi `CompleteDeliveryAction`) → `RecognizeFreightRevenueOnDelivery` (idempoten; unearned → revenue).

**Command (`lgx:`)** (semua terjadwal di `routes/console.php`): `invoice-shippers` (bulanan tgl 1; ada di `Application/Commands`, lainnya di `Console/Commands`), `detect-late` (15 mnt), `settle-cod` (harian), `pay-carriers` (mingguan), `accrue-dd` (harian), `audit-billing` (harian), `capacity-check`, `verify-custody`.

**Controller/UI** (`/logistics`): `LogisticsDashboard`, `ShipperPortal`, `PublicTracking` (publik, throttle), `Fleet`, `Driver`, `Location`, `Lane`, `HubOperations`, `DispatchBoard`, `DriverTask`, `Exception`, `Cod`, `Carrier`, `Claim`, `Demurrage`, `Customs`, `FuelLog`.

**Aturan bisnis kunci:** UU 22/2009 Pasal 90 (jam mengemudi) · SLA per service level · OTP delivery ter-hash + RateLimiter · four-eyes pada klaim/pembayaran carrier · D&D per zona waktu · bea cukai simulasi (BM/PPN/PPh22) · anomali BBM > 30% · quote menyimpan `declared_value_idr`, `insured`, `cod_amount_idr` · COD fee surcharge dimatikan (dihitung `CodFeeCalculator`).

**Seeder:** `LogisticsNetworkSeeder`, `LogisticsSeeder`, `LogisticsFinanceSeeder` (data nyata agar gate custody/capacity tidak vacuous).

**Fase 24 (integrasi lintas lini):** kontrak `ShipmentBooking` (Store `OrderPaid` → `CreateShipmentOnOrderPaid` → `BookShipmentForOrderAction`, ongkir ke `unearned_freight`, resi di order) · `DeliverVehicleByCarrierAction` (event `DELIVERED_BY_CARRIER` ke Vehicle Passport) · `FleetServiceDue` → `HandleFleetServiceDue` → kontrak `FleetMaintenanceBooking` (impl. `AutoServe\...\AutoServeFleetMaintenanceBooking`), armada `MAINTENANCE` ditolak dispatch, `CompleteFleetMaintenanceAction` · cold-chain: `TemperatureReading`, `RecordTemperatureAction` (excursion → `ShipmentException`), `ReceiveReeferReplenishmentAction` (stok ke Inventory) · `DockAppointment` (slot dock Duta Mall tanpa overlap, check-in/out) · pilar Logistik di `super:health-check` (9 pilar). Migrasi `2026_10_02_240101_phase_24_cross_line_integration`.
**Fase 25 (skala & API):** `LogisticsLargeSeeder` · `ControlTowerQuery` + `ControlTowerController` (logistics_admin) · **API v1** `routes/api.php` (`/api/v1/logistics/{quotes,shipments,tracking}`, `Idempotency-Key`, throttle 60/30 per menit, `Http/Controllers/Api/LogisticsApiController`, dokumen `docs/API.md`) · **Webhook outbox**: `WebhookEndpoint`, `WebhookDelivery`, `DispatchWebhookAction`, `lgx:retry-webhooks` (5 mnt; HMAC-SHA256, backoff, dead-letter), migrasi `2026_10_03_250401` · job `ProcessBulkShipmentUploadJob` (ShouldBeUnique) · `SecurityTest`/`RouteSmokeTest`/`QueryBudgetTest` mencakup rute logistik.
**Autentikasi API (Fase 26.1, Sanctum asli):** `laravel/sanctum ^4.3` terpasang; migrasi `personal_access_tokens`; `User` memakai trait `HasApiTokens`; `config/sanctum.php` `'guard' => []` sehingga session cookie **tidak** diterima di API (wajib bearer token). Rute kelola token: `POST|DELETE /profile/api-tokens` (`ApiTokenController`, UI `profile/partials/api-token-form.blade.php`). Pengecekan ability via `tokenCan()` di `LogisticsApiController`. `class_alias` palsu, guard `viaRequest('sanctum')`, dan `Domain/Support/Sanctum.php` sudah dihapus.

## 7. Modul lain — fakta penting

- **Banking:** `Ledger`, `VerifiesWalletPin` (contract); `Application/{Actions(6),DTOs,Queries,Services}`; PIN 6 digit, kunci 15 mnt setelah 5 salah; wallet otomatis via trait `HasLedgerAccounts`; UI wallet/transfer/mutasi/admin ledger.
- **Payment:** event `PaymentHeld/Captured/Released/Refunded`; `Payable` = `payableAmount()` + `revenueSplits()`; two-phase hold→capture dengan pengembalian remainder.
- **Core:** contract `AcquiresVehicle`, `TransfersVehicleOwnership`; event `VehicleAcquired`, `VehicleOwnershipTransferred`; passport publik via signed URL; notifikasi in-app, activity feed, dashboard per role.
- **Store:** C2C escrow, cart/checkout dengan reservasi stok, mobil sebagai produk (`dex_car → store_product`).
- **Crypto:** event `PricesTicked` → risk monitor Finance. **Finance:** margin_call LTV ≥ 80%, likuidasi ≥ 90%.
- **Resto:** satuan bahan & BOM bertingkat, MAC presisi tinggi, in-transit akuntansi antar-outlet, siklus etalase resirkulasi ≤ 3, royalti waralaba, tutup harian. **Mall:** contract `LoyaltyLedger`, `ParkingValidator`, `TenantSalesProvider`; anti-overlap lease; tagihan alokasi berurut; parkir progresif; poin PTS multi-aset; breakage voucher.
- Detail alasan: `docs/DECISIONS.md` (≈ 40 entri bertanggal).

## 8. Peta command lengkap
 
`bank:reconcile` · `payment:release-expired-holds` · `store:cancel-stale-orders` · `store:auto-capture-c2c` · `crypto:tick` · `finance:charge-installments` · `resto:expire-display|close-day|post-royalty|check-stock` · `mall:generate-invoices|auto-debit|apply-penalties|renew-parking-members|audit-billing|expire-points|expire-vouchers|settle-vouchers|generate-pm-orders|simulate-footfall` · `core:verify-passports` · `super:health-check` (9 pilar) · `lgx:*` (§6; termasuk `lgx:retry-webhooks`, `lgx:capacity-check`, `lgx:verify-custody` yang kini terjadwal) · `party:backfill-links` · `party:remind-expiring-docs` · `contracts:verify-chain` · `ctr:remind` · `ctr:audit {--sync}` · `ast:verify-chain` · `ast:backfill-links` · `ast:depreciate {--period} {--book} {--asset}` · `ast:audit {--tco}`. Jadwal bulanan: `ast:depreciate` (tgl 1 01:30), `ast:audit` (tgl 1 02:00).

## 9. Test

`tests/Architecture/ModuleBoundariesTest.php` · `tests/Feature/{RouteSmokeTest,SecurityTest,ActionConcurrencyRegressionTest,AssetCoreTest,AssetPhase31Test,ContractFeatureTest,ContractObligationsTest,CrossLineIntegrationTest,*Characterization*,Auth,Seed}` · `tests/Performance/QueryBudgetTest.php` · test per modul di `modules/*/tests/Feature` (Logistics: DispatchBoard, DriverApp, ExceptionAndSla, Phase22Integration, RevenueRecognition, CodFlow, CarrierSubcontract, ClaimWorkflow, DemurrageDetention, CustomsClearance, FuelLog, BillingAudit, …). Fixture: `tests/Support/MoneyFlowWorld.php`. Standar tiap fitur: test (a) happy path (b) validasi/otorisasi (c) idempotensi/retry (d) invarian ledger (e) edge case.

## 10. Quality gate (jalankan di akhir tiap fase)

`vendor/bin/pest` (0 gagal, 0 skipped) · `vendor/bin/pint --test` · `npm run build` · arch test · `php artisan bank:reconcile` (0 selisih) · `php artisan lgx:audit-billing` & `mall:audit-billing` (0 selisih) · `lgx:verify-custody` / `lgx:capacity-check` / `core:verify-passports` (data nyata) · `php artisan super:health-check`.

## 11. Dokumen & branch

Dokumen: `README.md` (Logistics + section API v1 sejak 26.2) · `docs/{PROGRESS,DECISIONS,ARCHITECTURE,RUNBOOK,AUDIT,CODEBASE,BLOCKERS}.md`. Branch: kerja di `feature/...` (tanpa kata "claude"), merge via PR ke `master`. PR #1 = Fase 22, PR #2 = Fase 23. PR #3 = Fase 24–25. Branch lama `claude/funny-galileo-v69spt` & `docs/prompt-fase-24-25` perlu dihapus manual di GitHub.

## 12. Utang teknis / catatan terbuka

1. ~~Sanctum palsu~~ **DITUTUP (26.1)** — `laravel/sanctum ^4.3` asli aktif, token bisa diterbitkan/dicabut di Profil, session cookie ditolak di API.
2. ~~AUDIT.md tanpa gate Fase 25~~ **DITUTUP (26.2)** — section "Quality Gate Fase 25" ditambahkan; README disinkronkan (545 test / 3203 assertion) dan diberi section API v1.
3. ~~Sweep transaksi/idempotensi~~ **DITUTUP (26.3)** — 110 temuan di 143 Action diperbaiki/ditutup (laporan: AUDIT 26.3).
4. ~~Sweep N+1 / indeks / query budget~~ **DITUTUP (26.4)** — Agregat SQL pada `bank:reconcile` dan `BillingAuditor`, `chunkById` pada `VerifyPassportsCommand`, `ExpireDisplayTraysCommand`, `CancelStaleOrdersCommand`, penambahan assertion query budget untuk `bank:reconcile` ($\le 10$) dan `lgx:audit-billing` ($\le 60$).
5. ~~Audit trail generik~~ **DITUTUP (26.6)** — `core_audit_logs` dengan `correlation_id` & `impact_type`, `AuditTrailInterface/Service`, append-only (RuntimeException pada update/delete), helper `audit()` di `BaseAction`, admin UI `/admin/audit-logs`.
6. ~~Outbox/event bus generik~~ **DITUTUP (26.7)** — `core_outbox` + subscriptions + dispatches, `OutboxBusInterface/Service`, helper `outbox()` di `BaseAction`, `core:process-outbox`, dead-letter & replay; Logistics `DispatchWebhookAction` dimigrasikan.
7. ~~Document numbering & document store~~ **DITUTUP (26.8)** — `core_document_sequences` (gapless, lockForUpdate), `core_documents` (SHA-256 checksum, retensi, mime/extension guard), `DocumentNumberingInterface/Service`, `DocumentStoreInterface/Service`.
8. ~~Approval engine generik~~ **DITUTUP (26.9)** — `core_approvals`, `core_approval_steps`, `core_approval_histories`, `ApprovalEngineInterface/Service` (four-eyes, multi-level, delegasi, SLA escalation, histori); helper `approval()` di `BaseAction`.

## 13. Rencana ke depan (ringkas; detail di PROGRESS.md)

Fase 20–25 Logistik selesai → **26–57** (detail di PROGRESS.md): 26 utang teknis+RBAC+outbox+approval · 27 Party & badan hukum · 28–29 Kontrak · 30–31 Aset · 32–34 Pemasok/Procurement/AP · 35–40 Pabrik (master, MRP, shop floor, costing, QMS, OEE/K3) · 41 WMS · 42–44 Distributor, distribusi, pricing · 45–46 Agensi · 47 Mitra · 48 Multi-currency/Treasury · 49–50 Ekspor-impor & Trade finance · 51–52 Kerja sama internasional & konsolidasi · 53 S&OP/Control tower · 54 Finance grup/kepatuhan · 55 API v2/EDI · 56 Skala/simulasi · 57 E2E & serah terima. Backlog 58+: SDM, PLM, ESG, marketplace, hulu pertanian, konstruksi, AI, mobile, DR.

## 14. PROTOKOL PEMBARUAN (wajib)

Awal sesi: baca file ini → baca bagian PROGRESS.md fase aktif → baru buka file sumber yang relevan.
Setiap commit yang mengubah salah satu di bawah **harus** ikut mengubah bagian terkait di file ini:

| Perubahan | Bagian yang diperbarui |
|---|---|
| Modul/prefix tabel/rute baru | §3, §6/§7 |
| Role baru | §4 |
| Akun ledger / TransactionType / AccountKind | §5 |
| Action/Service/Event/Listener/Contract penting | §6/§7 (atau §15 untuk modul baru) |
| Command / jadwal | §8 |
| Test/fixture baru yang penting, atau angka gate | §9, header "Snapshot gate" |
| Keputusan desain | tambah di DECISIONS.md + satu baris rujukan di sini |
| Utang teknis ditemukan/ditutup | §12 |
| Fase selesai | header "Fase selesai terakhir", §13, centang PROGRESS.md |

Aturan: ringkas (fakta, nama kelas, alasan 1 baris), jangan menyalin kode. Bila ragu apakah suatu file ada, verifikasi dengan `ls`/`grep`, lalu koreksi dokumen ini.

## 15. Modul baru (diisi saat dibuat, Fase 26+)

### Asset (`ast_`) — Fase 30

- **Tujuan:** register aset grup tunggal (PSAK 16 simulasi) dengan kapitalisasi, hash-chain riwayat, mutasi ber-approval, stok opname, penugasan, dan asuransi.
- **Tabel:** `ast_categories`, `ast_locations`, `ast_assets`, `ast_events`, `ast_stocktakes`, `ast_assignments`, `ast_insurances` (+ `asset_id` nullable di 6 tabel legacy).
- **Service:** `AssetService` — nomor gapless via `DocumentNumberingInterface` (`AST/{ENT}/`), posting `ast:fixed_assets` (key `ast:acquire:{id}`), riwayat hash-chain, mutasi via `ApprovalEngineInterface`, opname/check-out idempoten, asuransi, dokumen via `DocumentStoreInterface`.
- **Rute:** `/assets` (role `admin`, `asset_manager`): index/create/show/scan/verify-chain/move/checkout/checkin/insurance.
- **Command:** `ast:verify-chain --asset=<UUID>`, `ast:backfill-links {--dry-run}` (idempoten).
- **Aturan:** PSAK 16 & regulasi asuransi bersifat simulasi; book value = biaya perolehan + landed − depresiasi akumulatif.
- **Fase 31:** tabel `ast_depreciations`, `ast_usage_logs`, `ast_revaluations`, `ast_disposals`, `ast_work_orders`, `ast_leases`, `ast_lease_payments`, `ast_assets.salvage_value_idr`. Rute `/assets/audit` (GET), `/assets/depreciation` (GET+POST), `/assets/{asset}/{depreciate,revaluation,disposal,work-orders,leases}`. Aset `legacy_backfill` tidak dinaikan ke ledger & tidak disusutkan (biaya milik modul asal → `ensureCapitalized` idempoten untuk aset buatan sendiri).

### Contract (`ctr_`) — Fase 28

- **Tujuan:** sistem kontrak grup dengan klausul/templat, state machine ber-guard, persetujuan generik Core, e-sign simulasi, hash-chain versi, lampiran melalui DocumentStore, milestone/obligasi & pengingat.
- **Tabel:** `ctr_clause_templates`, `ctr_contract_templates`, `ctr_contracts`, `ctr_contract_parties`, `ctr_contract_versions`, `ctr_milestones`, `ctr_contract_clauses`, `ctr_contract_attachments`.
- **Service:** `ContractService` memakai `DocumentNumberingInterface` (`CTR/{entity}/YYYY-NNNNN` gapless) + `ApprovalEngineInterface`; append-version `lockForUpdate`; verify-chain `contracts:verify-chain`; diff textual.
- **Rute:** `/contracts` (role `admin`, `contract_manager`, `legal`): CRUD kontrak draft/negosiasi, transisi status, approval, sign per pihak berurutan, clauses/templates, versions/diff, milestones, attachments via `DocumentStoreInterface`, obligations dashboard.
- **Command:** `contracts:verify-chain --contract=<UUID>`, `ctr:remind --days=30` (notice period, expiring, milestone; in-app + Core Outbox idempoten).
- **Aturan kunci:** ≥2 pihak sebelum review; Signed hanya setelah Approved; alasan wajib suspend/terminate; hash-chain versi append-only; tanda tangan simulasi di-hash; lampiran disimpan checksum+retensi 7 tahun melalui Core DocumentStore; jenis pajak/regulasi tetap simulasi.
