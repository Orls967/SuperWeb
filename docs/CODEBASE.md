# CODEBASE.md — Peta Codebase Superwebsite (BACA INI DULU)

> **Fungsi dokumen:** satu-satunya pintu masuk untuk memahami kode. Sesi baru **membaca file ini, bukan memindai seluruh codebase**. Buka file sumber hanya untuk bagian yang akan diubah.
> **Kewajiban:** setiap perubahan (modul, tabel, rute, command, event, contract, role, config, keputusan, angka gate) **harus memperbarui file ini pada commit yang sama**. Lihat §14 (Protokol Pembaruan).
> Pelengkap: `docs/PROGRESS.md` (checklist tugas), `docs/DECISIONS.md` (alasan keputusan), `docs/ARCHITECTURE.md` (diagram & invarian), `docs/RUNBOOK.md` (operasi), `docs/AUDIT.md` (hasil gate).

**Terakhir diperbarui:** 2026-10-07 · **Fase selesai terakhir:** 72 (InsurTech: Micro-Insurance Tersemat & Claims Autopilot) · **Berjalan:** Fase 73 · **Berikutnya:** Fase 73 Robo-Advisor Wealth Management & Treasury Yield
**Snapshot gate (akhir Fase 72):** 951+ test / 4909+ assertion, 0 skipped · `bank:reconcile` 0 selisih (140 akun) · `chain:audit-all` 18/18 audit lulus (0 diskrepansi) · `super:health-check` 10 pilar HEALTHY · Pint, Vite, arch (14) lulus.

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
| Supplier | `sup_` (10 tabel) | `/suppliers`, `/portal/suppliers` | Produsen & Pemasok: `Supplier` (kandidat→approved→preferred→probation→disqualified, guard + riwayat), `SupplierCertification` (ISO/SNI/Halal/BPOM/GMP+masa berlaku), `SupplierQualification` (kuesioner/audit lokasi + ApprovalEngine four-eyes), `SupplierItem` + `SupplierPriceTier` (MOQ, tier qty, periode tanpa overlap, `contract_id` → kontrak Fase 28/29 menang atas katalog), `SupplierScorecard` (OTD/kualitas/harga/respons, aksi korektif <70, SCAR <50, idempoten per periode), `SupplierRiskFlag` (sertifikat kedaluwarsa, skor rendah, sanksi Party 27.6, single-source), `SupplierAsn` + `SupplierDocument` (portal via DocumentStore), `SupplierStatusHistory`; `SupplierService`; kontrak `ReferenceCostUpdater` (32.8 harga → MAC Resto tanpa import domain); role `supplier`, `procurement`; command `sup:scan-risks`, `sup:remind-certifications` (jadwal harian) |
| Contract | `ctr_` (13 tabel) | `/contracts` | Kontrak inti: `Contract`, `ContractParty`, `ContractClause`, `ContractVersion` (SHA-256 hash-chain append-only), `ContractMilestone`, `ContractAttachment` (Core DocumentStore checksum/retensi), `ContractTemplate`, `ClauseTemplate`; `ContractService` (gapless numbering, state machine, approval engine, e-sign simulasi, append/verify chain, diff); command `contracts:verify-chain`, `ctr:remind`; role `contract_manager`, `legal`; tabs kontrak: overview/pihak/klausul/obligasi/**keuangan**/lampiran/versi; **Fase 29**: `ContractFinanceService` (jadwal termin/advance/retensi, denda waiver via approval, eskalasi indeks terbatas-parser), `ContractAmendmentService` (diff + versi chain + regenerate jadwal), `ContractUsageService`/`UsageSync` (plafon idempoten, early warning 80/100%), `ContractRiskService` (skor 0–100), `ContractReportService` (eksposur per jenis/pihak, aging, `ctr:audit`), `ContractRateResolver` (rate card kontrak menang via `RateCardOverrideResolver`); command `ctr:audit`; laporan `/contracts/reports` | 47 |

| Manufacturing | `mfg_` (16 tabel) | `/manufacturing` | Pabrik: `Plant`/`PlantArea`/`WorkingCalendar`/`Shift` (35.1), `WorkCenter` (35.2 biaya/jam terbobot efisiensi, `asset_id` → Fase 30), `Material` + `UomConversion` (35.3), `Bom`+`BomLine` (35.4 multi-level ber-versi, alternatif/scrap/by+co-product, validasi siklus/UoM/nol), `Routing`+`RoutingOperation` (35.5 setup/run/inspeksi), `Formula` (35.6 draft→approval four-eyes→approved, hash-chain), `RestoAdapter` (35.7 CK-01 tanpa duplikasi data Resto), `Worker`+`WorkerShift` (35.8 tanpa payroll); `ManufacturingService`; role `planner`, `operator`, `qc_inspector`; permission `manufacturing.*`. **Fase 36**: `PlanningParam` (safety stock/ROP/lead/MOQ/lot sizing), `ForecastScenario`+`ForecastLine` (ber-versi, hanya satu active), `MpsHeader`+`MpsLine` (freeze window), `MaterialBalance`+`ScheduledReceipt` (netting), `MrpRun`+`MrpRequirement` (idempoten per `run_key`), `PlannedOrder`+`MaterialReservation` (firm, hard/soft, konflik due-date), `CapacityLoad` (CRP bottleneck); `PlanningService`; contract Procurement `MrpRequisitionProposer` (usulan PR); command `mfg:run-mrp` (terjadwal 04:45). **Fase 37**: `ProductionOrder` (state machine 5 status + nomor `MPO/{ENT}/`), `MaterialLot`+`MaterialIssue`+`MaterialIssueLot` (FIFO/FEFO + alokasi lot), `OperationReport`, `DowntimeLog` (5 kode alasan → OEE Fase 40), `FgReceipt` (guard ≤ qty_completed), `WipTransfer`, `ReworkRecord` (NCR saat scrap > toleransi), `SubcontractReceipt` (kirim/terima + PR jasa via contract); `ProductionService`; command `mfg:wip`; rute `/manufacturing/production`. **Fase 38**: `CostVersion` (draft→approval, roll-up BOM+routing), `StandardCost`, `OrderCost` (snapshot aktual), `Variance` (price/usage/labor/overhead/yield, post vs capitalize), `CostingService` (jurnal WIP/FG/scrap/COGS idempoten per key, settle order closed, margin report); listener `PostSaleCogsListener` di event `OrderPaid` Store; command `mfg:audit-costing`; rute `/manufacturing/costing`. **Fase 39**: `InspectionPlan`+`Inspection` (AQL sampling simulasi, receiving/in-process/final, alat kalibrasi wajib valid, dispensasi via ApprovalEngine four-eyes), `SpcSample` (X-bar/R + Cp/Cpk), `Ncr`+`Capa` (workflow + SCAR supplier risk flag via query mentah), `LotSale` (trace forward dari listener OrderPaid), `Recall`+`RecallRecipient` (notifikasi, kuarantina lot, biaya jurnal, penghancuran), `Certificate` (COA/COC/SNI/Halal/BPOM/GMP — simulasi, expiry block release), `Gauge` (kalibrasi); `QualityService`; rute `/manufacturing/quality` + `/lots/{lot}/trace`. **Fase 40**: `MaintenanceOrder` (korektif/preventif/prediktif + `trigger_key` idempoten, delegasi WO Aset 31.5 bila `asset_id` ada), `EquipmentPart`+`MaintenancePart` (BOM peralatan, stok minimum), `SensorReading` (suhu/getaran/arus simulasi → WO otomatis idempoten per hari), `OeeSummary` (A×P×Q, MTBF/MTTR), `HseIncident` (insiden/near-miss), `WorkPermit` (hot work/confined space, approval four-eyes + jendela berlaku), `ResourceUsage` (listrik/air/limbah per order); `MaintenanceService` (OEE, pareto downtime, backlog) + `HseService`; rute `/manufacturing/maintenance` |
| WMS | `wms_` (15 tabel) | `/wms` | Gudang: `Warehouse`/`Zone`/`Rack`/`Bin` hirarki, `BinStock` (lot/serial/status), `Task`+`Wave` (putaway/pick/pack/stage/replenish, FEFO/FIFO), `Transfer`+`TransferLine` (in-transit + cross-dock, resi via `ShipmentBooking`), `CycleCount` (approval four-eyes, akurasi KPI), `Replenishment`+`Slotting` (ABC), `DockAppointment` (anti-overlap), `PackingList` (label resi); `WmsService`; command `wms:audit`; role `hub_operator,procurement,auditor` |
| Distribution | `dist_` (10 tabel) | `/distribution` | Jaringan: `Distributor` (distributor/sub/agen/dealer, hirarki, tier, status), `Territory`+`TerritoryCoverage` (prov→kota→kecamatan, eksklusif + deteksi konflik), `Security` (jaminan), `ArInvoice`+`ArPayment` (subledger `dist:ar`, denda cap 5%, blokir otomatis), `Target`+`Tier` (Bronze/Silver/Gold diskon), `DistOutlet` (sell-out), `Scorecard` (fill rate/DSO/kepatuhan harga); `DistributionService`; command `dist:audit`; role `distributor`; permission `distribution.*`. **Fase 43**: `DistOrder`+`DistOrderLine` (limit kredit + ATP alokasi priority/fair-share, backorder), `DistShipment`+`DistShipmentLine` (pick→POD, resi `ShipmentBooking`), `DistInvoice` (PPN 11% + seri pajak simulasi), `SelloutReport`+`SelloutLine` (anomali stuffing/diversion/price_violation), `ConsignmentStock`+`ConsignmentSale`, `DistReturn` (kredit nota via approval), `RebateProgram`+`RebateAccrual` (volume/growth/tiered + settlement four-eyes), `HetPrice`, `StockLevel` (VMI); `DistributionFulfilmentService` |
| Pricing | `pric_` (10 tabel) | `/pricing` | Engine harga: `PriceList`+`PriceListItem` (segmen/channel/wilayah/mata uang, overlap guard), `DiscountRule` (waterfall deterministic volume/bundle/combo/coupon), `Promotion`+`PromotionClaim` (budget/evidence/approval), `PriceLock` immutable (contract/price-list/discount/promo/override), `MarginPolicy`+`PriceOverride` (floor & four-eyes), `PriceEvent` idempoten, `AnalyticsSnapshot` (realisasi/leakage/efektivitas); `PricingService`; contract `PriceLocker`; listener `PostSalePriceLockListener` Store `OrderPaid`; command `pricing:audit`; permission `pricing.*` |
| Agency | `agy_` (8 tabel) | `/agency` | Agensi: `Agent` (sales_agent/broker/reseller/affiliate/sole_agent, hirarki upline/downline maks N level, status onboarding/active/suspended/terminated), `AgentContract` (wilayah, scope produk, eksklusif, non-compete), `CommissionScheme` (flat/percent/slab/target_bonus + override upline bertingkat), `Attribution` (first-touch/last-touch, expiry), `CommissionAccrual` (hold periode retur, status hold/payable/reversed/paid), `recordClawback` (akrual negatif DR payable / CR expense), `Payout`+`PayoutItem` (four-eyes ApprovalEngine, withholding tax PPh 21/23 simulasi, posting DR payable / CR clearing & tax), `Statement` (opening + accrued - clawback - paid = closing balance); `AgencyService`; command `agy:audit`; role `agent`; permission `agency.*` |
| Partner | `ptn_` (8 tabel) | `/partners` | Mitra: `Partner` (strategic/tech/channel/franchise/jv/research/csr), `DueDiligence`, `JointPlan`, `RevenueShareRule`+`RevenueShareCalculation` (persentase/tiered/after_costs via ledger), `CosellListing`, `PartnerScorecard`, `IntellectualProperty`, `ExitTransition`; `PartnerService`; command `ptn:audit`; role `partner` |
| Treasury | `trs_` (9 tabel) | `/treasury` | Multi-Currency & Treasury: `Currency`, `ExchangeRate` (immutable versioned rate integer scaled 1e6), `Revaluation` (laba/rugi selisih kurs akhir periode), `BankAccount` (operasional & kas kecil), `BankStatement` (rekonsiliasi mutasi otomatis), `CashForecast` (horizon 13 minggu), `ForwardContract` (lindung nilai & mark-to-market), `CreditFacility` (plafon & covenant DER monitoring), `CashPool` (sweeping saldo ke header account); `TreasuryService`; command `treasury:audit`; role `treasury` |
| Trade | `trd_` (8 tabel) | `/trade` | Ekspor-Impor: `Country`, `Port`, `Incoterm` (Incoterms 2020), `HsCode` (BM, PPN, PPh 22, FTA), `ExportOrder` (PEB + pengakuan pendapatan saat risk transfer), `ImportOrder` (PIB + landed cost otomatis), `TradeDocument` (CoO, fumigasi, sertifikasi), `ShipmentLeg` (pelacakan lintas batas hash-chain SHA-256), `TradeDispute` (klaim dagang & asuransi kargo); `TradeService`; command `trade:audit` |
| TradeFinance | `tf_` (5 tabel) | `/trade-finance` | Trade Finance & SCF: `LetterOfCredit` (UCP 600 lifecycle), `LcDocument` (deteksi diskrepansi & waiver), `DocumentaryCollection` (D/P, D/A), `BankGuarantee` (bid/performance/advance/retention bonds), `TradeLoan` (pre/post-shipment & SCF); `TradeFinanceService`; command `tf:audit` |
| International | `intl_` (6 tabel) | `/international` | Kerja Sama Internasional: `ForeignEntity` (multi-yurisdiksi & AML), `JointVenture` (equity/contractual & capital calls), `TechnologyLicense` (royalti & MAG), `OemContract` (tolling fee & NDA), `TechTransfer` (milestones & derivative IP), `TaxTreaty` (P3B/WHT 10% vs 20%); `InternationalService`; command `intl:audit` |
| Intercompany | `ic_` (5 tabel) | `/intercompany` | Konsolidasi & IC: `IntercompanyTransaction` (mirror SO/PO), `IntercompanyLoan` (arm's length rate), `TransferPricingRule` (CUP/CPM/RPM/TNMM margin guard), `EliminationEntry` (reciprocal AP/AR), `SubsidiaryNci` (NCI net income share); `IntercompanyService`; command `group:audit` |
| ControlTower | `sct_` (4 tabel) | `/control-tower` | Supply Chain Control Tower & S&OP: `EchelonStock` (multi-eselon ABC/XYZ), `DemandForecast` (model forecast & evaluasi MAPE), `OrderPromise` (alokasi stok bebas ATP & CTP manufaktur), `DisruptionAlert` (blast radius keparahan gangguan rantai pasok); `ControlTowerService`; command `tower:audit` |
| EnterpriseFinance | `ef_` (4 tabel) | `/enterprise-finance` | Finance Grup & Tata Kelola: `EnterpriseBudget` (hard-stop vs soft-stop encumbrance), `EnterpriseTaxSummary` (rekonsiliasi PPN & PPh), `SodRule` (pemisahan tugas SoD matrix engine), `ComplianceDeadline` (kalender regulasi); `EnterpriseFinanceService`; command `enterprise:audit` |
| Integration | `intg_` (4 tabel) | `/integration` | B2B API v2 & EDI: `WebhookSubscription` (event publisher), `WebhookDelivery` (HMAC SHA-256 signature), `EdiMessage` (EDIFACT/X12 850/855/856/810 parser), `ApiClient` (tiered quota limit per minute); `IntegrationService`; command `api:audit` |
| Procurement | `prc_` (25 tabel) | `/procurement` | PR → RFQ → Tender → PO: `BudgetCenter`/`BudgetEncumbrance` (33.6 encumbrance per pusat biaya, warning >100%), `Requisition`+`RequisitionLine` (33.1 approval berjenjang, threshold 50jt), `Rfq`+`RfqInvitation`+`Quote` (33.2 matriks harga/lead/skor, alasan wajib), `Tender`+`TenderBid` (33.3 segel SHA-256 blind, buka bersamaan, evaluasi berbobot), `PurchaseOrder`+`PoLine`+`PoVersion` (33.4 versi via approval, blanket/call-off, close/cancel lepas encumbrance), `ImportProfile` (33.5 Incoterm/kurs/landed cost simulasi), `InboundShipmentService` (33.7 via kontrak `ShipmentBooking`, `source_id` UUID); rute `/procurement` (admin/procurement) + grup supplier untuk quote/seal; RBAC permission `procurement.*`. **Fase 34**: `ReceivingService` (GRN parsial/lot/kedaluwarsa + toleransi over-delivery 5% → `InventoryService` contract, inspeksi karantina → retur debit note, 3-way match PO–GRN–Invoice toleransi harga 2%/qty 5% → held+approval, GR/IR + AP per pemasok + PPN 11% & PPh 2% simulasi + PPV, batch payment run dengan approval + diskon dini, uang muka & kredit memo, alokasi landed cost value/weight/qty dengan baris terakhir menyerap selisih) + 10 tabel `prc_{receiving_reports,receiving_lines,inspections,supplier_returns,supplier_invoices,three_way_matches,ap_entries,payment_batches,payment_items,supplier_advances,credit_memos,landed_costs}`; command `proc:audit` (subledger AP == ledger dgn konvensi kredit = negatif, GR/IR = 0 untuk PO received) |
| HCM | `hcm_` (4 tabel) | `/hcm` | Human Capital Management: `Employee` (PKWT/PKWTT/freelance, NIK SHA-256 hash, gaji & rekening bank), `Payroll` (gaji kotor, BPJS TK/Kes, PPh 21 TER, gaji bersih), `ProductionLaborAllocation` (jam kerja aktual operator dialokasikan ke SPK Manufaktur); `HcmService`; command `hcm:audit`; role `hcm_manager` | 18 |
| PLM | `plm_` (4 tabel) | `/plm` | R&D & Product Lifecycle: `PlmProject` (Stage-Gate: ideation s/d launch, budget & ROI), `EngineeringBom` (EBOM vs MBOM components schema), `ChangeOrder` (ECO tamper-evident hash-chain SHA-256, disposisi scrap/rework), `LabNotebook` (ELN formula rahasia terenkripsi, uji stabilitas & sensori); `PlmService`; command `plm:audit`; role `rnd_specialist` | 18 |
| ESG | `esg_` (4 tabel) | `/esg` | ESG & Emisi Karbon: `EsgEmission` (Scope 1 BBM armada/genset, Scope 2 listrik PLN grid, Scope 3 freight/agri), `CarbonCredit` (IDX Carbon/Verra sertifikat per tonase IDR), `OffsetRetirement` (pensiun kuota emisi per entitas anti-over-retire), `SupplierScore` (skor ESG 3 pilar: Env 40%, Soc 30%, Gov 30% standar GRI); `EsgService`; command `esg:audit`; role `esg_officer` | 18 |
| B2B | `b2b_` (4 tabel) | `/b2b` | Marketplace B2B & Lelang Surplus: `WholesaleCatalog` (katalog grosir tertutup, tiered pricing matrix, MOQ), `B2bRfq` (RFQ antar-badan usaha, TOP 30/60), `SurplusAuction` (lelang mesin pabrik/armada surplus, anti-sniping 5 mnt, lockForUpdate), `B2bEscrowAccount` (penguncian dana jaminan & rilis BAST bertahap); `B2bService`; command `b2b:audit`; role `b2b_buyer` | 18 |
| Agri | `agri_` (4 tabel) | `/agri` | Hulu Pertanian & Rantai Dingin: `AgriFarmer` (petani plasma/poktan, GIS poligon lahan, komoditas), `AgriContract` (kontrak budidaya, uang muka bibit/pupuk, garansi floor price), `AgriCollectionBatch` (pos pengumpul, grading A/B/C, amortisasi uang muka, instant payout), `AgriColdChainLog` (telemetri IoT reefer truck 2-8°C ke CK-01/pabrik); `AgriService`; command `agri:audit`; role `farmer` | 18 |
| EPC | `epc_` (4 tabel) | `/epc` | Konstruksi & Asset Capitalization: `EpcProject` (Mall extension, pabrik baru, RAB budget), `EpcWbsNode` (WBS aktivitas terbobot 100%), `EpcProgressCertificate` (Monthly Certificate MC konsultan pengawas, klaim termin - 5% retensi), `EpcCipCapitalization` (BAST Final, akumulasi CIP direklasifikasi ke modul Asset `ast:fixed_assets`); `EpcService`; command `epc:audit`; role `epc_manager` | 18 |
Tabel non-prefiks lama: `users`, `bookings`, `spareparts`, `services`, `cars`, `brands`, `garages`, `wishlists`, `platform_*`.

## 4. Role & akun demo

Role (`users.role` + RBAC tabel `roles`): `admin`, `customer`, `mekanik`, `tenant`, `outlet_manager`, `kitchen`, `cashier`, `shipper`, `driver`, `dispatcher`, `hub_operator`, `logistics_admin`, `party_manager`, `contract_manager`, `legal`, `asset_manager`, `auditor`, `supplier`, `procurement`, `planner`, `operator`, `qc_inspector`, `distributor`, `agent`, `partner`, `treasury`, `hcm_manager`, `rnd_specialist`, `esg_officer`, `b2b_buyer`, `farmer`, `epc_manager`. RBAC mendukung multi-role per user dengan scope entitas (contoh: cashier scoped ke outlet). 32 role tersemai di `RbacSeeder`.
Seeder: `DatabaseSeeder` → Banking, Platform, Crypto, Mall, Resto, Logistics (+ `LogisticsFinanceSeeder`). Akun demo contoh: `admin@autoserve.test`, `customer@autoserve.test`, `mekanik@autoserve.test` (password `password`). `DemoLargeSeeder` = data besar lintas modul; `LogisticsLargeSeeder` = skala logistik (Fase 25).

## 5. Ledger & uang (inti sistem)

- **Contract** `Modules\Banking\Contracts\Ledger`: `post(PostingDTO)` dengan `PostingEntryDTO[]`; Σ entri = 0 per aset; idempotency key unik; lock akun urut ascending (anti-deadlock).
- **Konvensi tanda:** kredit **+**, debit **−**. Akun piutang bersaldo negatif (mis. `loan_receivable:IDR`).
- `AccountKind`: wallet, revenue, escrow, clearing, collateral, exchange, loan_receivable, fee, expense, cash, inventory, ap, deposit, liability, asset, points.
- `TransactionType` (Banking enum, ±40 case; nilai ≤ 32 char; Logistik memakai `LOGISTICS_*`).
- Akun sistem Logistik (`Application/Services/LogisticsLedger.php`): `lgx:unearned_freight`, `lgx:freight_revenue`, `lgx:cod_fee_revenue`, `lgx:carrier_cost`, `lgx:claims_expense`, `lgx:dd_revenue`, `lgx:customs_duty_payable`, `lgx:fuel_expense`, `clearing:external:IDR`.
- Akun escrow pembayaran `escrow:payment:IDR`; kolateral `escrow:finance:collateral:{ASSET}`.
- Akun & tipe kontrak (Fase 29): `ctr:advance` (liabilitas uang muka), `ctr:retention_receivable`/`ctr:retention_payable`, `ctr:penalty_revenue:IDR`, `ctr:revenue:{contract_id}`; TransactionType `ctr_advance|ctr_payment|ctr_retention|ctr_penalty|ctr_expense`; aset `ast_acquire|ast_dispose|ast_transfer|ast_depreciation|ast_revaluation|ast_impairment|ast_work_order|ast_lease_amort` dengan akun `ast:fixed_assets`, `ast:accumulated_depreciation` (CONTRA_ASSET), `ast:depreciation_expense`, `ast:fiscal_*` (buku fiskal simulasi), `ast:right_of_use`, `ast:lease_liability`, `ast:lease_interest`, `maintenance:asset:IDR`.
- Akun subledger lintas modul baru (Fase 38–63): Manufaktur `inv:wip`, `inv:materials`, `inv:finished_goods`, `expense:mfg_variance`, `expense:mfg_cogs`; Distribusi `dist:receivable`, `dist:ar:{id}`, `dist:rebate_payable`; Agensi `agy:commission_payable`, `agy:tax_withheld`; Treasury `trs:bank_operational`, `trs:cash_pool_header`, `trs:fx_gain_loss`; Trade `trd:customs_duty_payable`, `trd:landed_cost_clearing`; Intercompany `ic:mirror_clearing`, `ic:elimination_holding`; B2B Escrow `b2b:escrow_held:{account}`; Agri `agri:farmer_advance_receivable`, `agri:collection_clearing`; EPC `ast:cip_project:{project_id}`, `epc:retention_payable`.
- Pembulatan: Brick Math `HalfUp`; PB1 resto 10% pembulatan Rp100; PPN 11% (config `logistics.vat_rate`).
- **Audit:** `bank:reconcile` (Σ=0 & saldo cache = agregat entri, 140 akun seimbang), `chain:audit-all` (18 audit subledger rantai nilai terekonsiliasi 0 selisih), `super:health-check` (10 pilar HEALTHY).

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

**Fase 24 (integrasi lintas lini):** kontrak `ShipmentBooking` (Store `OrderPaid` → `CreateShipmentOnOrderPaid` → `BookShipmentForOrderAction`, ongkir ke `unearned_freight`, resi di order) · `DeliverVehicleByCarrierAction` (event `DELIVERED_BY_CARRIER` ke Vehicle Passport) · `FleetServiceDue` → `HandleFleetServiceDue` → kontrak `FleetMaintenanceBooking` (impl. `AutoServe\...\AutoServeFleetMaintenanceBooking`), armada `MAINTENANCE` ditolak dispatch, `CompleteFleetMaintenanceAction` · cold-chain: `TemperatureReading`, `RecordTemperatureAction` (excursion → `ShipmentException`), `ReceiveReeferReplenishmentAction` (stok ke Inventory) · `DockAppointment` (slot dock Duta Mall tanpa overlap, check-in/out) · pilar Logistik di `super:health-check` (10 pilar). Migrasi `2026_10_02_240101_phase_24_cross_line_integration`.
**Fase 25 (skala & API):** `LogisticsLargeSeeder` · `ControlTowerQuery` + `ControlTowerController` (logistics_admin) · **API v1** `routes/api.php` (`/api/v1/logistics/{quotes,shipments,tracking}`, `Idempotency-Key`, throttle 60/30 per menit, `Http/Controllers/Api/LogisticsApiController`, dokumen `docs/API.md`) · **Webhook outbox**: `WebhookEndpoint`, `WebhookDelivery`, `DispatchWebhookAction`, `lgx:retry-webhooks` (5 mnt; HMAC-SHA256, backoff, dead-letter), migrasi `2026_10_03_250401` · job `ProcessBulkShipmentUploadJob` (ShouldBeUnique) · `SecurityTest`/`RouteSmokeTest`/`QueryBudgetTest` mencakup rute logistik.
**Autentikasi API (Fase 26.1, Sanctum asli):** `laravel/sanctum ^4.3` terpasang; migrasi `personal_access_tokens`; `User` memakai trait `HasApiTokens`; `config/sanctum.php` `'guard' => []` sehingga session cookie **tidak** diterima di API (wajib bearer token). Rute kelola token: `POST|DELETE /profile/api-tokens` (`ApiTokenController`, UI `profile/partials/api-token-form.blade.php`). Pengecekan ability via `tokenCan()` di `LogisticsApiController`. `class_alias` palsu, guard `viaRequest('sanctum')`, dan `Domain/Support/Sanctum.php` sudah dihapus.

## 7. Modul lain — fakta penting

- **Banking:** `Ledger`, `VerifiesWalletPin` (contract); `Application/{Actions(6),DTOs,Queries,Services}`; PIN 6 digit, kunci 15 mnt setelah 5 salah; wallet otomatis via trait `HasLedgerAccounts`; UI wallet/transfer/mutasi/admin ledger.
- **Payment:** event `PaymentHeld/Captured/Released/Refunded`; `Payable` = `payableAmount()` + `revenueSplits()`; two-phase hold→capture dengan pengembalian remainder.
- **Core:** contract `AcquiresVehicle`, `TransfersVehicleOwnership`; event `VehicleAcquired`, `VehicleOwnershipTransferred`; passport publik via signed URL; notifikasi in-app, activity feed, dashboard per role.
- **Store:** C2C escrow, cart/checkout dengan reservasi stok, mobil sebagai produk (`dex_car → store_product`).
- **Crypto:** event `PricesTicked` → risk monitor Finance. **Finance:** margin_call LTV ≥ 80%, likuidasi ≥ 90%.
- **Resto:** satuan bahan & BOM bertingkat, MAC presisi tinggi, in-transit akuntansi antar-outlet, siklus etalase resirkulasi ≤ 3, royalti waralaba, tutup harian. **Mall:** contract `LoyaltyLedger`, `ParkingValidator`, `TenantSalesProvider`; anti-overlap lease; tagihan alokasi berurut; parkir progresif; poin PTS multi-aset; breakage voucher.
- **Agency:** skema komisi multi-tier & override upline bertingkat, atribusi first/last-touch, hold komisi masa retur produk, clawback negatif, payout four-eyes withheld PPh 21/23 simulasi (`agy:audit`).
- **Partner:** due diligence (threshold skor 70), Joint Business Plan (JBP), revenue share berbasis ledger `ptn:rev_share_expense`, co-selling katalog (`ptn:audit`).
- **Treasury:** multi-currency immutable exchange rate integer scaled 1e6, revaluasi selisih kurs akhir periode, cash pool sweeping, cash forecast 13 minggu, forward contract MTM, covenant DER monitoring (`treasury:audit`).
- **Trade:** Incoterms 2020 transfer of risk, HS Code landed cost bertingkat (BM, PPN, PPh 22, FTA), PEB/PIB, tracking perbatasan hash-chain SHA-256 (`trade:audit`).
- **Trade Finance:** Letter of Credit UCP 600 full-lifecycle, deteksi diskrepansi dokumen ekspor-impor, D/P & D/A collection, bank garansi tender/retensi, supply chain financing SCF (`tf:audit`).
- **International:** kemitraan PMA multi-yurisdiksi & AML check, joint venture equity/contractual, lisensi paten/merek dengan royalti & Minimum Annual Guarantee (MAG), OEM/ODM tolling fee, P3B/DTA tax treaty (`intl:audit`).
- **Intercompany:** konsolidasi grup holding-subsidiary, transaksi cermin otomatis (Mirror SO-PO & AR-AP), transfer pricing arm's length (CUP/CPM/RPM/TNMM), pinjaman antar-perusahaan, eliminasi saldo resiprokal, kepemilikan minoritas NCI (`group:audit`).
- **Control Tower:** visibilitas rantai pasok multi-eselon ABC/XYZ, demand forecast agregat S&OP (Moving Average, Exp Smoothing, MAPE), order promise bebas janji (ATP) & mampu janji (CTP), radar gangguan blast radius (`tower:audit`).
- **Enterprise Finance:** kontrol serapan anggaran korporat (hard-stop vs soft-stop encumbrance), kepatuhan kalender SPT Masa PPN/PPh, penegakan matriks pemisahan tugas Segregation of Duties (SoD) anti-fraud (`enterprise:audit`).
- **Integration:** B2B REST API v2 & Electronic Data Interchange (EDI: EDIFACT ORDERS/DESADV/INVOIC & ANSI X12 850/855/856/810), webhook bus HMAC-SHA256, tiered rate-limiting (`api:audit`).
- **HCM:** master karyawan PKWT/PKWTT/freelance ber-NIK terenkripsi, mesin payroll terotomasi dengan PPh 21 TER bulanan, BPJS TK 3%, BPJS Kes 1%, alokasi jam kerja operator ke SPK manufaktur (`hcm:audit`).
- **PLM:** pipeline riset Stage-Gate 6 tahap, transisi terkontrol EBOM purwarupa ke MBOM pabrik, Engineering Change Order (ECO) berantai hash SHA-256 tamper-evident, ELN formula rahasia terenkripsi & uji stabilitas ASLT (`plm:audit`).
- **ESG:** pelacak emisi GRK GHG Protocol Cakupan 1 (diesel/bensin), Cakupan 2 (grid PLN), Cakupan 3 (freight), portofolio kredit karbon IDX Carbon / Verra, offset retirement anti-over-retire, audit keberlanjutan pemasok 3 pilar GRI (Env 40%, Soc 30%, Gov 30%) (`esg:audit`).
- **B2B:** katalog grosir tertutup dengan Tiered Pricing Matrix & MOQ, negosiasi formal RFQ antar-badan usaha (TOP 30/45/60), balai lelang digital aset surplus & mesin pabrik dengan perlindungan anti-sniping 5 mnt, rekening escrow multi-pihak terproteksi rilis BAST (`b2b:audit`).
- **Agri:** kemitraan kelompok tani (Poktan), pemetaan poligon GIS lahan, kontrak tani bagi hasil (Contract Farming) ber-talangan bibit/pupuk & guaranteed floor price, pos pengumpul grading mutu A/B/C dengan amortisasi uang muka & instant payout, cold chain telemetri IoT reefer truck ke CK-01/pabrik (`agri:audit`).
- **EPC:** manajemen proyek konstruksi rekayasa, hierarki WBS bobot 100%, kurva-S deviasi progres lapangan, Monthly Certificate (MC) konsultan pengawas independen ber-retensi 5%, akumulasi Konstruksi Dalam Pengerjaan (CIP) dan kapitalisasi otomatis ke Aset Tetap modul Asset via BAST final (`epc:audit`).
- Detail alasan: `docs/DECISIONS.md` (≈ 48 entri bertanggal).

## 8. Peta command lengkap
 
`bank:reconcile` · `payment:release-expired-holds` · `store:cancel-stale-orders` · `store:auto-capture-c2c` · `crypto:tick` · `finance:charge-installments` · `resto:expire-display|close-day|post-royalty|check-stock` · `mall:generate-invoices|auto-debit|apply-penalties|renew-parking-members|audit-billing|expire-points|expire-vouchers|settle-vouchers|generate-pm-orders|simulate-footfall` · `core:verify-passports` · `super:health-check` (10 pilar) · `lgx:*` (§6; termasuk `lgx:retry-webhooks`, `lgx:capacity-check`, `lgx:verify-custody` yang kini terjadwal) · `party:backfill-links` · `party:remind-expiring-docs` · `contracts:verify-chain` · `ctr:remind` · `ctr:audit {--sync}` · `ast:verify-chain` · `ast:backfill-links` · `ast:depreciate {--period} {--book} {--asset}` · `ast:audit {--tco}` · `sup:scan-risks` · `sup:remind-certifications {--days}` · `proc:audit` · `mfg:run-mrp` · `mfg:wip` · `mfg:audit-costing` · `mfg:qms-audit` · (jadwal harian) OEE/K3 · `treasury:audit` · `trade:audit` · `tf:audit` · `intl:audit` · `group:audit` · `tower:audit` · `enterprise:audit` · `api:audit` · `hcm:audit` · `plm:audit` · `esg:audit` · `b2b:audit` · `agri:audit` · `epc:audit` · `chain:audit-all` (orkestrasi 18 audit rantai nilai menyeluruh). `ast:depreciate`/`ast:audit` bulanan (tgl 1), `sup:scan-risks` 06:45 & `sup:remind-certifications` 07:10 harian, `mfg:run-mrp` 04:45 harian.

## 9. Test

`tests/Architecture/ModuleBoundariesTest.php` · `tests/Feature/{RouteSmokeTest,SecurityTest,ActionConcurrencyRegressionTest,AssetCoreTest,AssetPhase31Test,SupplierManagementTest,ProcurementTest,ReceivingAndPayablesTest,ContractFeatureTest,ContractObligationsTest,CrossLineIntegrationTest,*Characterization*,Auth,Seed}` · `tests/Performance/QueryBudgetTest.php` · test per modul di `modules/*/tests/Feature` (Logistics: DispatchBoard, DriverApp, ExceptionAndSla, Phase22Integration, RevenueRecognition, CodFlow, CarrierSubcontract, ClaimWorkflow, DemurrageDetention, CustomsClearance, FuelLog, BillingAudit, …; Manufacturing: `ManufacturingPhase35Test` (7) & `ProductionPlanningTest` (10: forecast versioned, MPS fence, MRP idempoten+ledakan BOM, lot sizing, CRP bottleneck, reservasi hard/soft, konflik alokasi, usulan PR, what-if pulih, command); `ShopFloorTest` (14: state machine, FIFO/FEFO alokasi lot, shortage alert, operasi idempoten, backflush, FG guard, downtime, WIP, scrap→NCR, subkontrak+PR jasa, invarian); `ProductionCostingTest` (7: versi biaya four-eyes, jurnal WIP→FG, varians idempoten, settle closed, COGS event, margin, render); `QualityManagementTest` (12: AQL pass/fail, kalibrasi block, waiver four-eyes, sertifikat expiry, SPC/CpCpk, NCR→SCAR→CAPA, overdue, trace forward/backward, recall+ledger, render); `MaintenanceOeeHseTest` (8: OEE matematika+idempoten, WO guard, sensor alarm, part/pareto/backlog, insiden workflow, izin four-eyes+expiry, energi, render); `WmsTest` (10: hirarki, putaway/pick kapasitas, wave guard, transfer ship/receive idempoten+resi, cycle count four-eyes, replenishment, dock overlap, packing list, audit, render); `DistributionTest` (13) + `DistributionFulfilmentTest` (9: ATP/backorder, fair-share split, limit kredit, fulfilment+resi+POD+PPN, sell-out anomali, konsinyasi, retur credit note, rebate akrual+settlement four-eyes, HET margin, VMI); Pricing: `PricingEngineTest` (7: overlap+priority, waterfall+coupon, promo budget/evidence+four-eyes, immutable lock, margin-floor override, OrderPaid snapshot, analytics+audit)). Fixture: `tests/Support/MoneyFlowWorld.php`. Standar tiap fitur: test (a) happy path (b) validasi/otorisasi (c) idempotensi/retry (d) invarian ledger (e) edge case.

## 10. Quality gate (jalankan di akhir tiap fase)

`vendor/bin/pest` (0 gagal, 0 skipped) · `vendor/bin/pint --test` · `npm run build` · arch test · `php artisan bank:reconcile` (0 selisih) · `php artisan lgx:audit-billing` & `mall:audit-billing` (0 selisih) · `lgx:verify-custody` / `lgx:capacity-check` / `core:verify-passports` (data nyata) · `php artisan super:health-check` (10 pilar).

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

## 13. Rencana ke depan & status fase (ringkas; detail di PROGRESS.md)

- **Fase 1–25 (Pondasi Bisnis & Logistik):** Core, Bengkel AutoServe, Ensiklopedia AutoDex, Toko Online & Escrow C2C, Dompet Double-Entry Multi-Aset, HODL-to-Drive, Restoran RM Sari Ranah, Supermall Duta Mall, Sari Ranah Express Logistik End-to-End (Fase 20–25) selesai.
- **Fase 26–57 (Ekosistem Korporat Terpadu):** RBAC multi-role, Party master, Kontrak terstandarisasi, Manajemen Aset PSAK 16/73, Pengadaan Pemasok & 3-way match, Blok Manufaktur 6-fase (MRP/Shop Floor/Costing/QMS/OEE/WMS), Jaringan Distribusi, Dynamic Pricing & Waterfall Diskon, Agensi & Multi-Tier Commission, Kemitraan Strategis, Perbendaharaan Treasury & Revaluasi Valas, Tata Niaga Ekspor-Impor & Landed Cost, Trade Finance UCP 600, Joint Ventures Internasional & P3B, Transaksi Cermin & Konsolidasi Grup Intercompany, Supply Chain Control Tower S&OP, Tata Kelola Finance Grup & SoD, B2B Integration Engine & EDI, Simulasi Skala Besar & Serah Terima Final selesai.
- **Fase 58–63 (Ekspansi Strategis Konglomerasi Multi-Sektor):** Human Capital Management (HCM & PPh 21 TER), Product Lifecycle Management (PLM & Stage-Gate R&D), ESG & Akuntansi Karbon (GHG Scope 1-3 & IDX Carbon), B2B Marketplace & Lelang Surplus Mesin/Aset, Hulu Agribisnis & Rantai Pasok Petani (Contract Farming & Cold Chain), Konstruksi EPC & Kapitalisasi Aset (WBS Kurva-S & Monthly Certificate MC) selesai.
- **Backlog Mendatang (Fase 64+):** Fase 64 Analitik Prediktif & AI-Driven Revenue Management, Fase 65 Enterprise Mobile Suite (PWA/Offline-First), Fase 66 Resiliensi Global, Disaster Recovery Multi-Region & Kedaulatan Data.

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

### Party (`pty_`) — Fase 27

- **Tujuan:** Satu sumber kebenaran untuk semua pihak eksternal/internal (pemasok, produsen, distributor, agen, mitra, pelanggan, karyawan, perusahaan), tata kelola hukum, KYC/KYB workflow, penautan non-breaking identitas lintas modul.
- **Tabel:** `pty_parties`, `pty_legal_entities`, `pty_party_roles`, `pty_party_addresses`, `pty_party_contacts`, `pty_kyc_documents`, `pty_sanction_checks`, `pty_credit_profiles`, `pty_party_bank_accounts`, `pty_merge_logs`.
- **Service:** `PartyService` — manajemen profil/kontak, workflow KYC (pending→verified), legal entity (induk-anak perusahaan, NPWP, tahun fiskal, mata uang fungsional), deteksi duplikat & merge (reversible via `MergeLog`), credit scoring komposit (simulasi skor risiko/limit lintas modul), dan screening daftar hitam sanksi AML/CFT (menggunakan similar_text threshold & hash-check).
- **Rute:** `/party` (role `admin`, `party_manager`).
- **Catatan:** Integrasi backfill idempoten menghubungkan entitas legacy (`Carrier`, `ShipperAccount`, `Tenant`, `Seller`, `Supplier`) ke entitas `pty_parties` melalui kolom `party_id` nullable yang ditambahkan di berbagai tabel lama.

### Procurement (`prc_`) — Fase 33

- **Tujuan:** siklus pembelian PR → RFQ/Tender → PO dengan penguncian anggaran (encumbrance), versi PO, PO impor simulasi, dan jadwal pengiriman masuk.
- **Tabel:** `prc_budget_centers`, `prc_budget_encumbrances` (source_id string; unik per source_type+source_id), `prc_requisitions`(+lines), `prc_rfqs`(+invitations), `prc_quotes`, `prc_tenders`(+bids: seal_hash SHA-256, opened_at), `prc_purchase_orders`(+lines, versions, import_profiles).
- **Service:** `ProcurementService` (PR approval berjenjang ≥50jt → admin; encumbrance idempoten + warning >100%; RFQ matriks komposit harga50/lead20/skor30 dengan alasan wajib; tender segel-buta, buka bersamaan hanya setelah tenggat, evaluasi berbobot bobot=100; PO versi ordinal riwayat vs versi bisnis `version`, close/cancel melepas encumbrance; blanket → call-off; landed cost = nilai+freight+asuransi+bea) · `InboundShipmentService` (kontrak `ShipmentBooking`, guard replay per `procurement_po`+PO.id, `amount_idr` ≥1 karena ledger menolak 1 entri).
- **Rute:** `/procurement` (role `admin,procurement,supplier`, aksi mutasi dibatasi middleware `role:admin,procurement`): dashboard, PR, RFQ (termasuk supplier kirim quote), tender (seal/open/evaluate/award), PO (revise/close/cancel/import-profile/inbound).
- **Perubahan lintas modul:** `lgx_shipments.source_id` kini string (menampung UUID PO) dengan accessor yang mempertahankan int untuk sumber numerik lama; kontrak `ShipmentBooking::cancelForOrder` memakai `string|int`.
- **Aturan:** bursa/kurs/Incoterm/bea = simulasi; uang integer IDR.

### Supplier (`sup_`) — Fase 32

- **Tujuan:** profil pemasok/produsen, onboarding ber-guard, harga bertingkat + kontrak kerangka, portal ASN/COA, skor periodik & risiko.
- **Tabel:** `sup_suppliers` (owner_user_id isolasi portal), `sup_certifications`, `sup_qualifications`, `sup_status_histories`, `sup_items`, `sup_price_tiers` (contract_id), `sup_scorecards`, `sup_risk_flags`, `sup_asns`, `sup_documents`.
- **Service:** `SupplierService` — nomor gapless `SUP/{ENT}/`, state machine + riwayat wajib alasan, kualifikasi → ApprovalEngine, `resolvePrice` (kontrak > katalog, MOQ, periode), `score`/`saveScorecard` idempoten, `scanRisks` (4 jenis flag), `applyReferenceCost` → kontrak `ReferenceCostUpdater`.
- **Rute:** `/suppliers` (admin/procurement/party_manager) dan `/portal/suppliers` (role `supplier`/admin) — portal diisolasi via `owner_user_id`.
- **Command:** `sup:scan-risks`, `sup:remind-certifications` (jadwal harian).
- **Aturan:** sanksi & skor adalah simulasi; sertifikasi/lead-time/termin bersifat referensi.

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

### Manufacturing (`mfg_`) — Fase 35

- **Tujuan:** master data pabrik — plant, work center, material, BOM multi-level, routing, formula, tenaga kerja, dan jembatan Dapur Sentral CK-01 ke modul Resto.
- **Tabel:** `mfg_plants`, `mfg_plant_areas`, `mfg_working_calendars`, `mfg_shifts`, `mfg_work_centers`, `mfg_materials`, `mfg_uom_conversions`, `mfg_boms`, `mfg_bom_lines`, `mfg_routings`, `mfg_routing_operations`, `mfg_formulas`, `mfg_resto_adapters`, `mfg_workers`, `mfg_worker_shifts`.
- **Service:** `ManufacturingService` — BOM validasi (siklus multi-level via DFS leluhur, qty > 0, UoM konversi, alokasi co-product 100%); formula `draft → pending_approval (MFG_FORMULA_CHANGE, four-eyes) → approved` dengan `prev_hash/hash` per versi + `verifyFormulaChain`; adapter CK-01 replay-guard per `last_sync_key` (tanpa duplikasi data Resto — query mentah `resto_outlets`); `WorkCenter::totalCostPerHour()` = ceil((mesin+tenaga+overhead)×100/efisiensi).
- **Rute:** `/manufacturing` (role `admin,planner,operator,qc_inspector`); mutasi (plant/material/BOM/routing/formula/worker) dibatasi `role:admin,planner`; approval formula `role:admin,qc_inspector`.
- **Aturan kunci:** angka integer IDR; tanpa ledger (Fase 37–38 menambah posting produksi); BOM Resto tetap berjalan sendiri (adapter hanya pointer stempel sinkronisasi); tenaga kerja tanpa payroll.

- **Fase 36 (perencanaan):** tabel `mfg_planning_params`, `mfg_forecast_scenarios`+lines, `mfg_mps_headers`+lines, `mfg_material_balances`, `mfg_scheduled_receipts`, `mfg_mrp_runs`+`mfg_mrp_requirements`, `mfg_planned_orders`, `mfg_material_reservations`, `mfg_capacity_loads`. `PlanningService::runMrp` — ledakan BOM bertingkat (level relaksasi), netting per bucket dengan bc-math 6 desimal, lot sizing l4l/fixed/periodic/eoq + MOQ floor, supersede planned order lama, CRP load vs kapasitas terbobot efisiensi; `firmPlannedOrder` (hard/soft), `resolveAllocationConflicts` (prioritas due date), `proposePurchases` via contract `Modules\Procurement\Contracts\MrpRequisitionProposer`, `whatIf` mode scenario tanpa data nyata. Rute `/manufacturing/planning`; command `mfg:run-mrp --horizon --bucket --scenario` terjadwal harian 04:45.

- **Fase 37 (shop floor):** tabel `mfg_production_orders`, `mfg_material_lots`, `mfg_material_issues`+`mfg_material_issue_lots`, `mfg_operation_reports`, `mfg_downtime_logs`, `mfg_fg_receipts`, `mfg_wip_transfers`, `mfg_rework_records`, `mfg_subcontract_receipts`. `ProductionService` — state machine `planned→released→in_progress→completed→closed|cancelled` (transisi lompat ditolak), issue FIFO (produced_at) / FEFO (expiry) dengan alokasi lot per issue + alert `shortage`/`no_lot` (stok tidak pernah negatif), backflush `kind=backflush` pada transisi completed, guard FG receipt ≤ qty_completed, downtime 5 kode alasan, WIP in_transit→received, scrap/rework flag NCR bila > `scrap_tolerance_percent`, subkontrak kirim/terima (hasil olahan menambah qty_completed) + PR jasa via contract, `checkInvariants` (Σ issue ≤ kebutuhan BOM + 5%, hasil+scrap vs target ± toleransi). Rute `/manufacturing/production`; command `mfg:wip {--order}`.

- **Fase 38 (costing):** tabel `mfg_cost_versions`, `mfg_standard_costs`, `mfg_order_costs`, `mfg_variances` + kolom `mfg_material_lots.unit_cost_idr` & `mfg_fg_receipts.unit_cost_idr`. `CostingService` — roll-up standard cost (bahan baku → BOM → routing×WC, level relaksasi), submit/approve versi via ApprovalEngine four-eyes; jurnal: issue `DR inv:wip / CR inv:materials`, konversi `DR inv:wip / CR clearing:external`, scrap `DR expense:mfg_scrap / CR inv:wip`, FG receipt `DR inv:finished_goods / CR inv:wip` (transfer proporsional terhadap qty_completed, receipt terakhir menyerap pembulatan), COGS `DR expense:mfg_cogs / CR inv:finished_goods`. Varians: harga = (lot−std)×qty, pemakaian = (qty aktual−qty std)×std (terpisah, tidak menumpuk), tenaga/overhead/yield; policy `post` → `expense:mfg_variance`, `capitalize` → `inv:wip`; idempoten per `mfg:variance:{order}:{kind}`. Listener `PostSaleCogsListener` pada event `OrderPaid` Store (query mentah SKU, FIFO lot FG). Settle order `closed`: sisa WIP → FG (`mfg:settle:{order}`). Rute `/manufacturing/costing`; command `mfg:audit-costing` (WIP subledger == ledger; order closed tanpa sisa WIP).

- **Fase 39 (QMS & traceability):** tabel `mfg_inspection_plans`, `mfg_inspections` (+`approval_id`), `mfg_spc_samples`, `mfg_gauges`, `mfg_ncrs`, `mfg_capas`, `mfg_lot_sales`, `mfg_recalls`, `mfg_recall_recipients`, `mfg_certificates`. `QualityService` — AQL sampling (simulasi), inspeksi receiving/in-process/final, alat ukur kalibrasi expired memblokir, waiver failed inspection via submit+approve four-eyes, rilis lot mensyaratkan inspeksi pass/waived + sertifikat valid COA default; SPC X-bar/R + Cp/Cpk (σ=R̄/d2 n=5), alarm mean di luar spec/Cpk<1; NCR→CAPA (due/overdue/effectiveness) + SCAR supplier via raw query `sup_risk_flags`; trace backward FG lot→production order→input lot/receipt source & forward `mfg_lot_sales` dari listener Store `OrderPaid`; recall per lot idempoten (quarantine `blocked`, capture recipients, notify, biaya WASTE journal, destruction note → lot consumed qty 0). Regulasi/sertifikat `SNI/Halal/BPOM/GMP` adalah simulasi.

- **Fase 40 (OEE & K3):** tabel `mfg_maintenance_orders`, `mfg_equipment_parts`, `mfg_maintenance_parts`, `mfg_sensor_readings`, `mfg_oee_summaries`, `mfg_hse_incidents`, `mfg_work_permits`, `mfg_resource_usages`. `MaintenanceService` — WO pemeliharaan (nomor gapless `MWO/{ENT}/`, guard state machine, `trigger_key` idempoten; bila `asset_id` ada → delegasi `AssetWorkOrderService::schedule` 31.5), konsumsi suku cadang menaikkan `parts_cost_idr` WO, sensor threshold → WO predictive idempoten per `(work_center, metric, tanggal)`, OEE `A=(planned−downtime)/planned`, `P=(ideal×total)/operating`, `Q=good/total`, `OEE=A×P×Q` + MTBF/MTTR (lookup `whereDate` — cast date tidak cocok `updateOrCreate`), pareto downtime kumulatif, backlog prioritas, intensitas resource per unit. `HseService` — insiden `reported→action→closed` (tutup wajib investigasi), izin kerja `PTW/{ENT}/` approval four-eyes + `valid_from/until`, `expirePermits()`. Rute `/manufacturing/maintenance`; regulasi sensor/K3 bersifat simulasi.

### WMS (`wms_`) — Fase 41

- **Tujuan:** lokasi gudang & pusat distribusi — hirarki gudang/zona/rak/bin, posisi stok per bin/lot/status, tugas pick/putaway, transfer in-transit, cycle count, dock & packing list.
- **Tabel:** `wms_warehouses`, `wms_zones`, `wms_racks`, `wms_bins`, `wms_bin_stocks`, `wms_tasks`, `wms_waves`, `wms_transfers`, `wms_transfer_lines`, `wms_cycle_counts`, `wms_cycle_count_lines`, `wms_replenishments`, `wms_slottings`, `wms_dock_appointments`, `wms_packing_lists`.
- **Service:** `WmsService` — putaway/pick menangani kapasitas bin & FIFO/FEFO tanpa membuat stok negatif; transfer `draft → in_transit → received` idempoten; ship memicu `ShipmentBooking` (resi Logistics, tanpa import Domain); cycle count via ApprovalEngine four-eyes; `InventoryService::adjust` contract untuk sinkronisasi variance.
- **Aturan:** stok bin = subledger posisi di atas saldo `store_products.cached_stock`; pergerakan internal tidak mengubah total stok; audit `wms:audit` gagal hanya bila stok bin negatif atau bin > saldo global.
- **Rute:** `/wms` (role `admin,hub_operator,procurement,auditor`).

### Distribution (`dist_`) — Fase 42

- **Tujuan:** jaringan distributor/agen/dealer — hirarki, teritori eksklusif, onboarding (jaminan + approval), piutang (AR) dengan denda & blokir otomatis, target/tier, outlet sell-out, scorecard.
- **Tabel:** `dist_distributors` (+`approval_id`), `dist_territories`, `dist_territory_coverage`, `dist_securities`, `dist_ar_invoices`, `dist_ar_payments`, `dist_targets`, `dist_tiers`, `dist_outlets`, `dist_scorecards`.
- **Service:** `DistributionService` — daftar distributor (hanya induk approved untuk sub), onboarding wajib jaminan aktif + `ApprovalEngine` four-eyes (procurement → admin, approval disimpan di `approval_id`, approve menunggu engine), teritori hierarchy prov→city→district + deteksi konflik eksklusif, AR `DR dist:receivable / CR dist:ar:{id}` lalu bayar `DR dist:ar / CR clearing`, denda 0.1%/hari cap 5% (`DR receivable / CR denda`), exposure sinkron dengan `amount+denda−paid`, blokir otomatis saat over-limit & overdue > 7 hari (unblock saat < limit), aging 0-30/31-60/61-90/90+, target sell-in/out + evaluasi tier Bronze/Silver/Gold, outlet wajib ter-cover, scorecard komposit 0–100 (achievement 40 + fill 20 + DSO 20 + price 20) + `recommended_tier`.
- **Rute:** `/distribution` dan `/distribution/{distributor}` (role `admin,procurement,distributor,auditor`); command `dist:audit` (exposure == Σ terbuka, ledger AR == eksposur absolut, tanpa overpaid/tier invalid).
- **Portal 42.6:** `GET /portal/distributors` (role `distributor,admin`) menampilkan tier/diskon/limit/eksposur, tagihan + aging, target & capaian, outlet; akses 403 bila akun belum terhubung `owner_user_id`.
- **Aturan:** angka integer IDR; denda/termin/PPh bersifat simulasi.

- **Fase 43 (eksekusi distribusi):** tabel `dist_orders`+lines, `dist_shipments`+lines, `dist_invoices`, `dist_sellout_reports`+lines, `dist_consignment_stocks`+sales, `dist_returns`, `dist_rebate_programs`+accruals, `dist_het_prices`, `dist_stock_levels`. `DistributionFulfilmentService` — `placeOrder` (validasi limit: eksposur+total ≤ limit, diskon tier otomatis, PPN 11% simulasi), `allocate` ATP (`InventoryService::reserve` per baris `dist_order_line`; priority vs fair_share yang membagi rata ke order belum teralokasi; backorder per baris + retry), `fulfil` pick→commit reservasi (SALE) + booking `ShipmentBooking` (source `dist_shipment`, resi), `recordPod`→delivered + `issueTaxInvoice` (`FTR/{ENT}/` + seri `010.001.yymm.…`, jurnal DR receivable/CR revenue), `submitSellout` (deteksi stuffing 3× rata 7 hari, price_violation <70% HET, capaian target sell_out), konsinyasi `receiveConsignment`/`reportConsignmentSale`/`invoiceConsignment` (jurnal DR consignment_ar/CR consignment), `requestReturn`/`approveReturn` (kredit nota DR revenue/CR receivable + reduksi exposure), rebate program `volume|growth|tiered` + `accrueRebate` idempoten per order+program (jurnal DR expense/CR payable `dist:rebate_payable`), `settleRebate`→approval four-eyes→`approveSettlement`, `marginReport` vs HET (tebus 85% simulasi), VMI `setStockLevel`/`computeVmiSuggestions` (buffer 7 hari), `dist:audit` += rebate payable & konsinyasi unbilled + faktur referensi. Rute & panel fulfillment mengikuti pola Fase 42 (service sudah teruji tes; UI menunggu permintaan).

### Pricing (`pric_`) — Fase 44

- **Tujuan:** price list deterministik per channel/segment/wilayah/currency; waterfall diskon, promo dagang, immutable price lock, margin floor & override, analitik.
- **Tabel:** `pric_price_lists`, `pric_price_list_items`, `pric_discount_rules`, `pric_promotions`, `pric_promotion_claims`, `pric_price_locks`, `pric_margin_policies`, `pric_price_overrides`, `pric_price_events`, `pric_analytics_snapshots`.
- **Service:** `PricingService`: aktivasi price list menolak overlap scope/periode; resolver region>segment>general lalu priority kecil; diskon urut `order,code`, kupon stack guard + audit waterfall; klaim promo wajib bukti, validasi budget termasuk klaim pending, settlement four-eyes; quote menerapkan contract price → price list → discount, menolak margin floor sampai override approved; `PriceLock` unik per subject+SKU (replay tidak menimpa), `PriceEvent` hash-key idempoten, analytics realisasi vs list/leakage.
- **Integrasi:** contract `Modules\Pricing\Contracts\PriceLocker`; listener Store `OrderPaid` membuat snapshot price lock (SKU tak dikenal price list fallback ke immutable `price_snapshot`). `pricing:audit` periksa anggaran, klaim settled+approval, override decided_at, kebocoran lock & overlap price list.
- **Rute:** `/pricing` (admin/procurement/auditor); `npm build` tak menambah asset khusus.

- **Fase 44 (pricing):** `PricingService::activatePriceList` menolak overlap channel+segment+region; resolver `resolveList` memakai spesifisitas (region > segment > general) lalu priority. `calculateWaterfall` deterministik (urut `order,code`), alokasi diskon proporsional per baris, kupon dicek stackability; hasil waterfall disimpan sebagai array JSON. Promo: `submitPromotionClaim` wajib bukti + budget pending-aware, `approvePromotionClaim` menyimpan approval, `settlePromotionClaim` four-eyes → `spent_idr` naik. `quote()` honor `$contractPriceIdr` (Fase 29.3) dulu, lalu price list, lalu discount; margin policy memakai `minAllowedPrice()` dan menolak sampai `decideOverride(approve)` mengisi `decided_at`. `lockPrice` unique `(subject_type, subject_id, sku)` immutable; `PriceEvent::firstOrCreate` by hash. `computeAnalytics(period, channel)` dari price locks: realization = applied/list, leakage = diskon tanpa `source_kind` dikenal. Command `pricing:audit`: budget, settled tanpa approval, override tanpa `decided_at`, lock `applied > list` tanpa `reason`, overlap list — exit 1 bila ada temuan. Grup permission `pricing.*` di `RbacSeeder` (admin kecil kecil semua).

### Agency (`agy_`) — Fase 45

- **Tujuan:** agen penjualan & komisi — agen individu/badan, kontrak keagenan, skema komisi berjenjang (override upline), atribusi first/last-touch, akrual komisi dengan periode hold masa retur, clawback komisi negatif, payout periodik ber-approval four-eyes & withholding tax PPh simulasi, statement komisi, audit ledger `agy:*`.
- **Tabel:** `agy_agents`, `agy_contracts`, `agy_commission_schemes`, `agy_attributions`, `agy_commission_accruals`, `agy_payouts`, `agy_payout_items`, `agy_statements`.
- **Service:** `AgencyService` — registrasi agen (upline wajib active, status `onboarding→active→suspended→terminated`), kontrak `createContract` (wilayah, scope produk, eksklusif, non-compete), skema komisi `saveScheme` (basis flat/percent/slab/target_bonus + override upline maks N level), atribusi `recordAttribution` (first-touch vs last-touch + expiry), `accrueSale` (hold sampai masa retur lewat, posting DR expense / CR payable idempoten), `recordClawback` (akrual negatif DR payable / CR expense), `releaseHolds` (melepas hold ke payable), `createPayout` (menghitung gross, PPh withholding, net), `approvePayout` (four-eyes ApprovalEngine), `payPayout` (posting DR payable / CR clearing & DR payable / CR tax withheld simulasi), `buildStatement` (opening + accrued - clawback - paid = closing balance).
- **Rute:** `/agency` dan `/agency/{agent}` (role `admin`, `agent`, `procurement`, `auditor`); mutasi aksi (create agent, transition, scheme, attribution, accrual, clawback, payout, approve) dibatasi `role:admin,procurement`.
- **Audit & Role:** command `agy:audit` (akrual payable = ledger payable, payout net = gross - tax, paid payout wajib ada ledger tx, tidak ada atribusi stale); role `agent` ke-24 di `RbacSeeder` dengan permission `agency.view` & `agency.portal.access`.

- **Fase 46 (ekosistem agensi):** tabel `agy_leads`, `agy_lead_activities`, `agy_certifications`, `agy_agent_tiers`, `agy_brand_agencies`, `agy_compliance_incidents`, `agy_fraud_checks` + kolom `tier_code`, `total_sales_volume_idr`, `total_deals_count` pada `agy_agents`. `AgencyService` diperluas: CRM leads (`createLead`, `recordLeadActivity`, `convertLead`), sertifikasi/lisensi (`addCertification`, `isValidAt`), tiering dinamis (`saveTier`, `evaluateTier`, `getLeaderboard`), APM brand agencies (`registerBrandAgency`), kepatuhan/sanksi (`reportIncident`, suspensi otomatis, `appealIncident`), deteksi kecurangan (`checkFraud`: self-referral blocking & spike detection), analitik performa agen (`calculateAnalytics`: ROI, deal count, commission paid). Rute `/agency/leads`, `/agency/leads/{lead}/convert`, `/agency/{agent}/certifications`, `/agency/{agent}/brand-agencies`, `/agency/{agent}/compliance`, `/agency/{agent}/fraud-checks`.

### Partner (`ptn_`) — Fase 47

- **Tabel:** `ptn_partners`, `ptn_due_diligences`, `ptn_joint_plans`, `ptn_revenue_shares`, `ptn_cosell_listings`, `ptn_scorecards`, `ptn_intellectual_properties`, `ptn_exit_transitions`.
- **Service:** `PartnerService` — siklus hidup `prospect→due_diligence→negotiation→active⇄review→exit` ber-guard, due diligence (skor ≥70 approved), JBP, bagi hasil idempoten per (mitra, periode) + posting ledger `ptn:rev_share_expense`/`clearing`, scorecard upsert, HKI, co-selling, exit.
- **Rute:** `/partners` (admin/procurement/auditor); role `partner` (ke-25) + permission `partner.*`; command `ptn:audit`.
- **Catatan jujur:** portal mitra (47.6) baru direktori/detail minimal; due diligence belum memakai ApprovalEngine (skor otomatis); HKI/aset bersama belum tertaut ke modul Asset.

### Treasury & Multi-Currency (`trs_`) — Fase 48

- **Tujuan:** perbendaharaan multi-mata uang terpusat, kurs versi immutable berpresisi tinggi (scaled 1e6 integer), revaluasi selisih kurs akhir periode, rekening bank operasional & sweeping kas pool, prakiraan arus kas 13 minggu, lindung nilai valas forward contract mark-to-market, dan monitoring batasan rasio utang/covenant DER.
- **Tabel:** `trs_currencies`, `trs_exchange_rates`, `trs_revaluations`, `trs_bank_accounts`, `trs_bank_statements`, `trs_cash_forecasts`, `trs_forward_contracts`, `trs_credit_facilities`, `trs_cash_pools`.
- **Service:** `Modules\Treasury\Application\Services\TreasuryService` — posting kurs harian, revaluasi aset moneter valas, sweeping kas otomatis antar-rekening entitas, pemantauan fasilitas plafon kredit dan debt-to-equity covenant.
- **Rute:** `/treasury` (role `admin`, `treasury`).
- **Command:** `treasury:audit` (revaluasi saldo valas vs ledger, batasan covenant, sweeping kas seimbang, 0 diskrepansi).

### Cross-Border Trade & Ekspor-Impor (`trd_`) — Fase 49

- **Tujuan:** otomasi tata niaga ekspor dan impor barang lintas negara, penegakan aturan Incoterms 2020, klasifikasi tarif bea masuk/keluar HS Code, landed cost bertingkat, kepabeanan PEB (Pemberitahuan Ekspor Barang) & PIB (Pemberitahuan Impor Barang), pelacakan hash-chain kargo SHA-256, dan resolusi sengketa dagang internasional.
- **Tabel:** `trd_countries`, `trd_ports`, `trd_incoterms`, `trd_hs_codes`, `trd_export_orders`, `trd_import_orders`, `trd_trade_documents`, `trd_shipment_legs`, `trd_trade_disputes`.
- **Service:** `Modules\Trade\Application\Services\TradeService` — kalkulasi bea masuk, PPN impor, PPh 22 impor, FTA duty preferences, transisi status ekspor saat penyerahan risiko Incoterms, pelacakan leg logistik internasional.
- **Rute:** `/trade` (role `admin`, `procurement`, `logistics_admin`).
- **Command:** `trade:audit` (integritas hash-chain tracking logistik perbatasan, kalkulasi landed cost, 0 selisih).

### Trade Finance & Supply Chain Financing (`tf_`) — Fase 50

- **Tujuan:** pembiayaan perdagangan internasional terstandarisasi UCP 600, siklus hidup Letter of Credit (L/C: issuance, advising, negotiation, settlement), diskrepansi dokumen ekspor-impor, penagihan dokumenter (D/P, D/A), bank garansi penerbitan jaminan tender/pelaksanaan/retensi, dan pinjaman modal kerja rantai pasok (SCF).
- **Tabel:** `tf_letters_of_credit`, `tf_lc_documents`, `tf_documentary_collections`, `tf_bank_guarantees`, `tf_trade_loans`.
- **Service:** `Modules\TradeFinance\Application\Services\TradeFinanceService` — penerbitan L/C, deteksi diskrepansi dan penolakan/waiver dokumen ekspor, monitoring jatuh tempo bank garansi, dan pencairan fasilitas SCF ke pemasok.
- **Rute:** `/trade-finance` (role `admin`, `treasury`).
- **Command:** `tf:audit` (rekonsiliasi eksposur komitmen L/C dan bank garansi aktif terhadap saldo agunan fasilitas, 0 diskrepansi).

### International Joint Ventures & Aliansi Global (`intl_`) — Fase 51

- **Tujuan:** kemitraan korporasi multi-yurisdiksi, pembentukan entitas joint venture (equity vs contractual), lisensi alih teknologi internasional dengan royalti dan jaminan minimum tahunan (MAG), kontrak tolling manufaktur OEM/ODM, perjanjian alih teknologi ber-milestone, dan penghindaran pajak berganda P3B/DTA.
- **Tabel:** `intl_foreign_entities`, `intl_joint_ventures`, `intl_technology_licenses`, `intl_oem_contracts`, `intl_tech_transfers`, `intl_tax_treaties`.
- **Service:** `Modules\International\Application\Services\InternationalService` — manajemen entitas asing ber-AML check, kalkulasi royalti paten/merek, pemotongan withholding tax (WHT) tarif P3B tereduksi, monitoring tolling fee pabrik.
- **Rute:** `/international` (role `admin`, `legal`, `treasury`).
- **Command:** `intl:audit` (verifikasi royalti vs MAG, konsistensi tarif pajak P3B, 0 diskrepansi).

### Intercompany Transactions & Konsolidasi Grup (`ic_`) — Fase 52

- **Tujuan:** konsolidasi keuangan multi-entitas holding & anak perusahaan, transaksi cermin otomatis (Mirror SO ↔ PO dan mirror AP ↔ AR), penetapan harga transfer wajar (*Arm's Length Transfer Pricing* metode CUP/CPM/RPM/TNMM), pinjaman antar-perusahaan, eliminasi saldo resiprokal, dan atribusi laba kepentingan non-pengendali (NCI).
- **Tabel:** `ic_intercompany_transactions`, `ic_intercompany_loans`, `ic_transfer_pricing_rules`, `ic_elimination_entries`, `ic_subsidiary_ncis`.
- **Service:** `Modules\Intercompany\Application\Services\IntercompanyService` — penjurnalan mirror transaksi, kalkulasi bunga arm's length pinjaman afiliasi, eliminasi saldo piutang-utang internal saat tutup buku, rekonsiliasi kepemilikan minoritas NCI.
- **Rute:** `/intercompany` (role `admin`, `auditor`, `treasury`).
- **Command:** `group:audit` (keseimbangan transaksi cermin PO-SO, eliminasi resiprokal seimbang, 0 selisih).

### Supply Chain Control Tower & S&OP (`sct_`) — Fase 53

- **Tujuan:** menara kendali rantai pasok multi-eselon terpusat, optimalisasi persediaan multi-eselon klasifikasi ABC/XYZ, peramalan permintaan agregat S&OP (Moving Average, Exponential Smoothing) dengan evaluasi MAPE, alokasi janji pemenuhan pesanan bebas janji (ATP) dan kapasitas produksi mampu janji (CTP), serta radar peringatan dini gangguan rantai pasok (*blast radius disruption*).
- **Tabel:** `sct_echelon_stocks`, `sct_demand_forecasts`, `sct_order_promises`, `sct_disruption_alerts`.
- **Service:** `Modules\ControlTower\Application\Services\ControlTowerService` — rebalancing safety stock multi-eselon, kalkulasi deviasi perkiraan permintaan S&OP, alokasi stok ATP/CTP tanpa membuat kuota negatif, evaluasi blast radius bencana atau kemacetan pelabuhan.
- **Rute:** `/control-tower` (role `admin`, `planner`, `logistics_admin`).
- **Command:** `tower:audit` (invarian kuota pemenuhan pesanan ATP/CTP ≤ saldo bebas fisik, validasi eselon persediaan, 0 diskrepansi).

### Enterprise Finance, Anggaran & Tata Kelola Grup (`ef_`) — Fase 54

- **Tujuan:** tata kelola keuangan korporat skala konglomerasi, kontrol anggaran multi-level (*hard-stop* vs *soft-stop encumbrance*), kepatuhan kalender regulasi perpajakan nasional, rekonsiliasi SPT Masa PPN & PPh grup, penegakan matriks pemisahan tugas (*Segregation of Duties - SoD*) multi-role anti-konflik kepentingan.
- **Tabel:** `ef_enterprise_budgets`, `ef_enterprise_tax_summaries`, `ef_sod_rules`, `ef_compliance_deadlines`.
- **Service:** `Modules\EnterpriseFinance\Application\Services\EnterpriseFinanceService` — validasi serapan anggaran unit bisnis, engine deteksi benturan peran SoD (misal pembuat PO dilarang menyetujui pembayaran), kalender kepatuhan pajak dan audit holding.
- **Rute:** `/enterprise-finance` (role `admin`, `auditor`, `treasury`).
- **Command:** `enterprise:audit` (verifikasi serapan anggaran tidak melampaui plafon hard-stop, ketiadaan pelanggaran aturan SoD aktif, 0 diskrepansi).

### B2B Integration Engine & EDI Gateway (`intg_`) — Fase 55

- **Tujuan:** pintu gerbang integrasi mitra strategis, distributor besar, dan perbankan via B2B REST API v2 dan Electronic Data Interchange (EDI), pertukaran dokumen standar industri (EDIFACT ORDERS/DESADV/INVOIC & ANSI X12 850/855/856/810), pengiriman event webhook ber-tanda tangan kriptografis HMAC SHA-256 dengan kuota bertingkat (*tiered rate-limiting*).
- **Tabel:** `intg_webhook_subscriptions`, `intg_webhook_deliveries`, `intg_edi_messages`, `intg_api_clients`.
- **Service:** `Modules\Integration\Application\Services\IntegrationService` — parsing & generasi pesan EDI, verifikasi kuota API per menit, dispatch webhook outbox dengan retry backoff eksponensial, dan verifikasi hash signature.
- **Rute:** `/integration` (role `admin`).
- **Command:** `api:audit` (integritas tanda tangan kriptografis webhook HMAC SHA-256, pelacakan pengiriman dead-letter, 0 diskrepansi).

### Human Capital Management (`hcm_`) — Fase 58

- **Tujuan:** manajemen sumber daya manusia terintegrasi, kompensasi & payroll, pajak PPh 21 TER, jaminan BPJS Ketenagakerjaan/Kesehatan, dan alokasi biaya tenaga kerja langsung ke Work Order SPK Manufaktur.
- **Tabel:** `hcm_employees`, `hcm_payrolls`, `hcm_production_labor_allocations`, `hcm_time_attendances`.
- **Service:** `Modules\Hcm\Application\Services\HcmService` — registrasi karyawan (PKWT/PKWTT/freelance, enkripsi NIK SHA-256), komputasi payroll otomatis (gaji pokok, tunjangan, BPJS TK 3%, BPJS Kes 1%, PPh 21 TER 5% simulasi, gaji bersih), alokasi biaya jam kerja aktual operator ke SPK manufaktur (`allocated_cost_idr`).
- **Rute:** `/hcm` (role `admin`, `hcm_manager`).
- **Command:** `hcm:audit` (verifikasi invarian gaji kotor − potongan = gaji bersih, Σ komponen potongan = total potongan, 0 selisih).

### Product Lifecycle Management (`plm_`) — Fase 59

- **Tujuan:** tata kelola inovasi riset & pengembangan produk baru, stage-gate review, pemisahan purwarupa R&D (EBOM) ke resep massal pabrik (MBOM), rekayasa perubahan spesifikasi (ECO) berantai hash kriptografis, dan Electronic Lab Notebook (ELN) formula rahasia terenkripsi.
- **Tabel:** `plm_projects`, `plm_engineering_boms`, `plm_change_orders`, `plm_lab_notebooks`.
- **Service:** `Modules\Plm\Application\Services\PlmService` — siklus proyek Stage-Gate (`ideation → scoping → business_case → development → testing → commercial_launch`), EBOM JSON schema validation, pengajuan ECO tamper-evident SHA-256 (`prev_hash` → `hash`), pencatatan ELN dengan enkripsi formula base64 & uji stabilitas ASLT.
- **Rute:** `/plm` (role `admin`, `rnd_specialist`).
- **Command:** `plm:audit` (verifikasi integritas hash-chain ECO SHA-256, 0 diskrepansi).

### ESG & Carbon Accounting (`esg_`) — Fase 60

- **Tujuan:** akuntansi emisi gas rumah kaca (GHG Protocol Scope 1, 2, dan 3), penatausahaan portofolio kredit karbon (IDX Carbon / Verra), pensiun sertifikat offset emisi net-zero, dan audit keberlanjutan rantai pasok (GRI Standards & OJK Hijau).
- **Tabel:** `esg_emissions`, `esg_carbon_credits`, `esg_offset_retirements`, `esg_supplier_scores`.
- **Service:** `Modules\Esg\Application\Services\EsgService` — kalkulasi emisi standar (solar diesel 2.68 kg/L, bensin 2.31 kg/L, listrik PLN 0.79 kg/kWh, freight darat 0.12 kg/ton-km), pencatatan sertifikat kredit karbon, pensiun kuota offset ber-guard anti-over-retire, evaluasi skor ESG pemasok berbobot (Env 40%, Soc 30%, Gov 30%).
- **Rute:** `/esg` (role `admin`, `esg_officer`).
- **Command:** `esg:audit` (verifikasi konsistensi kalkulasi emisi CO2e, sertifikat vs pensiun kuota, 0 diskrepansi).

### B2B Marketplace & Surplus Auction (`b2b_`) — Fase 61

- **Tujuan:** direktori marketplace grosir tertutup multi-vendor, alur penerbitan & negosiasi RFQ komersial dengan termin tempo (TOP 30/60), balai lelang digital aset surplus & mesin idle dengan perlindungan anti-sniping, serta rekening escrow platform terproteksi.
- **Tabel:** `b2b_wholesale_catalogs`, `b2b_rfqs`, `b2b_surplus_auctions`, `b2b_escrow_accounts`.
- **Service:** `Modules\B2b\Application\Services\B2bService` — katalog grosir bertingkat (*Tiered Pricing Matrix*), RFQ inter-company, lelang mesin pabrik & armada surplus dengan row-level lock (`lockForUpdate`) dan perpanjangan waktu otomatis 5 menit bila ada penawaran di menit akhir, penguncian deposit escrow serta pencairan bersyarat BAST.
- **Rute:** `/b2b` (role `admin`, `b2b_buyer`, `supplier`, `distributor`).
- **Command:** `b2b:audit` (verifikasi penahanan deposit escrow = komitmen aktif, timeline lelang valid, 0 diskrepansi).

### Agribusiness & Cold Chain (`agri_`) — Fase 62

- **Tujuan:** digitalisasi hulu pertanian & kemitraan petani plasma (Poktan), pemetaan spasial GIS lahan garapan, kontrak budidaya bagi hasil dengan uang muka bibit/pupuk, pos pengumpul panen pedesaan dengan grading mutu A/B/C & instant payout, serta rantai dingin IoT reefer truck ke Dapur Sentral CK-01 & pabrik pengolahan.
- **Tabel:** `agri_farmers`, `agri_contracts`, `agri_collection_batches`, `agri_cold_chain_logs`.
- **Service:** `Modules\Agri\Application\Services\AgriService` — registrasi petani plasma & poligon GeoJSON, penerbitan kontrak tani dengan proteksi guaranteed floor price, penerimaan panen di pos pengumpul dengan pemotongan otomatis piutang uang muka (tanpa melebihi hasil panen), pemantauan telemetri IoT cold chain (2°C - 8°C optimal, deteksi breach).
- **Rute:** `/agri` (role `admin`, `farmer`, `procurement`).
- **Command:** `agri:audit` (verifikasi neraca bagi hasil panen, pemotongan piutang bibit tepat, 0 selisih).

### EPC Construction & Asset Capitalization (`epc_`) — Fase 63

- **Tujuan:** manajemen proyek rekayasa konstruksi (ekstensi Duta Mall, pabrik baru, central kitchen), hierarki Work Breakdown Structure (WBS) dengan bobot 100%, kurva-S deviasi progres lapangan, penerbitan sertifikat prestasi bulanan (Monthly Certificate - MC) konsultan pengawas ber-retensi 5%, serta penutupan biaya Konstruksi Dalam Pengerjaan (CIP) menjadi Aset Tetap di modul Asset.
- **Tabel:** `epc_projects`, `epc_wbs_nodes`, `epc_progress_certificates`, `epc_cip_capitalizations`.
- **Service:** `Modules\Epc\Application\Services\EpcService` — inisiasi proyek konstruksi & RAB, pemecahan simpul paket pekerjaan WBS, penerbitan sertifikat bulanan MC (gross claim, retensi 5%, net payable), pemutakhiran akumulasi biaya CIP, dan kapitalisasi tuntas ke Aset Tetap (`ast:fixed_assets`) melalui BAST Final.
- **Rute:** `/epc` (role `admin`, `epc_manager`, `asset_manager`).
- **Command:** `epc:audit` (verifikasi klaim MC = akumulasi CIP + kapitalisasi, progres fisik ≤ 100%, 0 diskrepansi).

---

> **Catatan Fase Tanpa Modul Baru:**
> - **Fase 56** (Stress Testing Skala Ultra) dan **Fase 57** (Deep Audit, Hardening & Security) tidak menambahkan modul baru, melainkan berfokus pada seeder berkapasitas besar (`LogisticsLargeSeeder`), optimasi indeks database, dan pengetatan *Quality Gates*.

