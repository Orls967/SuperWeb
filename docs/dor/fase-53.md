# Definition of Ready (DoR) — Fase 53: Supply Chain Control Tower & S&OP

- **Fase:** 53 (`modules/ControlTower/`, prefiks tabel `sct_`)
- **Gelombang / Paket:** W8-P6 & W8-P6-fix (`FIX-2`)
- **Tanggal Pembaruan:** 2026-11-14
- **Status Target:** `🔵 DIIMPLEMENTASIKAN` (non-PU)

---

## 1. Justifikasi RAB Posting Ledger = 0 (Aturan D98)

**RAB Posting Ledger = 0.**
**Alasan tertulis (D98):**
Modul `ControlTower` (`sct_`) adalah lapisan orkestrasi perencanaan rantai pasok terpadu (visibilitas stok multi-eselon, pemilihan model peramalan permintaan & override kolaboratif, siklus S&OP 7-tahap, janji pesanan ATP/CTP lintas periode, penilaian risiko pesanan, kartu skor pemasok, analisis kesehatan persediaan, KPI eksekutif OTIF/Fill Rate/DOS/C2C, dan simulasi What-If Digital Twin sandbox). Seluruh entitas di bawah prefiks `sct_` bersifat rencana kuantitatif, reservasi janji pengiriman non-moneter, skor analitik, atau skenario sandbox yang **tidak menghasilkan kejadian uang (money event)**. Seluruh pencatatan akuntansi riil tetap dimiliki dan di-posting secara eksklusif oleh modul transaksi operasional (`Wms`, `Procurement`, `Purchasing`, `Sales`, `Distribution`, `Manufacturing`, `Banking`). Sesuai `ADR-053`, `ControlTower` tidak mendaftarkan `TransactionTypeRegistry` maupun memanggil `LedgerPoster`.

---

## 2. Daftar Keputusan Arsitektur & Spesifikasi Terkunci (D53-1 .. D53-10)

### 2.1 Ruang Lingkup Deliverable (D53-1 .. D53-10)
1. **D53-1 — Visibilitas Stok Multi-Eselon & `ItemAvailabilityReader`**:
   - Pembaca posisi inventori real-time per item code (`on_hand`, `available`, `quarantine`, `hold`, `oldest_lot_age_days`) melalui `WmsItemAvailabilityReader` yang mengimplementasikan `Modules\ControlTower\Contracts\ItemAvailabilityReader`.
   - Agregasi lintas simpul `SUPPLIER`, `PORT`, `PLANT`, `DC`, `OUTLET` via `StockAvailabilityReader` (`Wms`) dan `InTransitShipmentProvider` (`Logistics`) tanpa query langsung ke `wms_*` atau `store_*`.
2. **D53-2 — Pemilihan Model Permintaan (`ForecastModelSelector` I2) & `ForecastOverrideService` (`X53-4`)**:
   - Evaluasi komparatif SES (`alpha = 3/10`), Holt Linear (`alpha = 3/10, beta = 1/10`), dan Croston Intermittent (`alpha = 2/10`) memakai aritmetika rasional skala `10_000` dengan backtest *walk-forward* 6 titik terakhir, memilih MAE terkecil serta *tie-breaker* deterministik `SES -> HOLT -> CROSTON`.
   - `ForecastOverrideService` mengelola penyesuaian peramalan oleh planner dengan alasan audit wajib, perhitungan deviasi `bps`, approval berjenjang untuk deviasi besar, serta kapabilitas *revert* ke baseline.
3. **D53-3 — Siklus Bulanan S&OP Kolaboratif 7-Status (`SopCycleStateMachine`)**:
   - Mendukung jalur resmi 7 tahap `data_gathering -> demand_review -> supply_review -> pre_sop -> exec_sop -> published -> closed` serta mempertahankan kompatibilitas jalur transisi historis (`draft..archived`).
   - Transisi mundur wajib menyertakan alasan tertulis non-kosong; tahap review membuat tugas di `TaskInboxService`; publikasi mewajibkan `ApprovalEngineInterface` four-eyes dan memancarkan event `tower.sop.published.v1`.
4. **D53-4 — Mesin Janji Pesanan ATP & CTP Netting (`AtpCtpNetter` I1 & `PromiseQuoteInterface`)**:
   - Netting kumulatif lintas periode (`1..T`) dari posisi `available`, `mpsReceipts` (`Manufacturing`), `commitments` (`Distribution`), serta batas kapasitas & ketersediaan bahan (`CapacityAndMaterialProvider`).
   - Implementasi `PromiseQuoteService` menegakkan idempotency per `idempotency_key` (`IdempotencyConflictException` jika payload berbeda) dan memancarkan `tower.promise.made.v1`.
5. **D53-5 — Klasifikasi ABC/XYZ & `MultiEchelonSafetyStock` (I3)**:
   - Matriks faktor-Z 3×3 (`AX=2.33 .. CZ=0.84` dalam `bps`), penggabungan variabilitas permintaan dan variabilitas *lead time* pemasok (`SupplierLeadTimeStatistics` dari `Procurement`) via `isqrt` integer, serta alur proposal-keputusan four-eyes sebelum pembaruan kebijakan safety stock aktif.
6. **D53-6 — `OrderRiskScorer` (`X53-1`), Pemindai Disrupsi & Usulan Reallocation Berpersetujuan**:
   - Skor komposit `0..1000` atas 4 komponen deterministik (shortfall rasio, variabilitas lead-time pemasok, umur lot tertua, dan keterlambatan in-transit).
   - `AlertScannerService` mendeteksi kondisi `CRITICAL`/`HIGH` secara idempoten (`dedup_key`), memancarkan `tower.alert.raised.v1`, dan menyusun proposal realokasi antar-eselon dengan gerbang persetujuan planner/operator (`proposeReallocation` / `approveReallocation`).
7. **D53-7 — Kartu Skor Pemasok (`SupplierScorecardService` `X53-2`) & Kesehatan Persediaan (`InventoryHealthAnalyzer` `X53-3`)**:
   - Skor komposit pemasok `0..1000` berbasis OTD (45%), konsistensi lead time (30%), dan reliabilitas volume sampel (25%) untuk tiering `STRATEGIC|PREFERRED|WATCHLIST|AT_RISK` beserta tren bulanan.
   - Bucket aging persediaan (`0-30`, `31-90`, `91-180`, `>180` hari) untuk klasifikasi `DEAD_STOCK`, `SLOW_MOVING`, `EXCESS`, `SHORTAGE_RISK`, dan `HEALTHY` beserta eksposur obsolet dalam satuan minor `IDR` (`int`).
8. **D53-8 — Digital Twin What-If Zero-Mutation Sandbox**:
   - Menjalankan simulasi kejut (`PORT_CLOSURE`, `MATERIAL_INFLATION`, `SUPPLY_DELAY`, `DEMAND_SPIKE`, `SUPPLIER_LOSS`, `WAREHOUSE_CAPACITY`) lewat `ControlTowerTwinTickHandler` dan `SimulationRunnerService` sandbox dengan verifikasi digest SHA-256 pra/pasca-simulasi (`before_hash === after_hash`) agar nol baris tabel operasional riil berubah.
9. **D53-9 — KPI Eksekutif Terukur dari Data Operasional (`OpenDemandProvider`)**:
   - Menghitung `otif_permil` (`0..1000`), `fill_rate_permil`, `inventory_dos_days`, `cash_to_cash_days`, serta jumlah SKU stockout langsung dari `fulfillmentLines()` dan posisi echelon, dilengkapi *drill-down* per pesanan dan ekspor CSV.
10. **D53-10 — Audit Dual-Source (`tower:audit`), CLI Lengkap, Event Catalog & Antarmuka `x-ds.*`**:
    - Seluruh 9 perintah CLI `tower:*` terdaftar aktif (`atp-refresh`, `alerts-scan`, `kpi`, `forecast-select`, `safety-stock`, `whatif`, `risk-scan`, `supplier-scorecard`, `audit`).
    - Tiga event domain (`tower.alert.raised.v1`, `tower.sop.published.v1`, `tower.promise.made.v1`) terdaftar di `EventCatalog` dan `EventSchemaRegistry`.
    - Sepuluh rute web utama `/control-tower/{promises,forecast,safety-stock,sop,alerts,twin,kpi,risk,suppliers,inventory-health}` serta ekspor `.csv` terproteksi `route-roles.php` dan lulus `ui:lint` viewport `375px`.

---

## 3. Peta Kontrak Lintas Modul (Zero A1 / Zero A2)

| Kontrak | Modul Pemilik Kontrak | Implementasi Resmi | Konsumen / Penggunaan |
| :--- | :--- | :--- | :--- |
| `ItemAvailabilityReader` | `ControlTower` | `WmsItemAvailabilityReader` | Netting ATP (`PromiseQuoteService`), Risk Scorer, Inventory Health |
| `PromiseQuoteInterface` | `ControlTower` | `PromiseQuoteService` | Pemanggilan dari modul pesanan / endpoint `POST /control-tower/promises/quote` |
| `CapacityAndMaterialProvider` | `Manufacturing` | `ManufacturingCapacityAndMaterialProvider` | Kapasitas & MPS kumulatif untuk CTP (`AtpCtpNetter`) |
| `OpenDemandProvider` | `Distribution` | `DistributionOpenDemandProvider` | Komitmen pesanan terbuka (`dist_orders`) untuk ATP & baris pemenuhan KPI OTIF/Fill Rate |
| `SupplierLeadTimeStatistics` | `Procurement` | `ProcurementSupplierLeadTimeStatistics` | Mean/sigma lead time & OTD pemasok (`prc_*`) untuk I3 Safety Stock, X53-1 Risk, X53-2 Scorecard |
| `StockAvailabilityReader` | `Wms` | `StockReservationService` | Pembacaan stok tersedia net gudang bebas karantina/reservasi |
| `InTransitShipmentProvider` | `Logistics` | `LogisticsInTransitAdapter` | Pelacakan keterlambatan pengiriman masuk (`> 72` jam) & stok transit |

---

## 4. Batasan Integritas Wajib (Prasyarat Merge Gate)
1. **Zero `float` pada Kuantitas & Uang (Aturan A3):** Seluruh bobot skor, basis points (`bps`), permil (`0..1000`), tarif rasional skala `10_000`, kuantitas unit dasar, dan eksposur nilai rupiah (`IDR`) menggunakan integer 64-bit (`int` / `bigInteger`).
2. **Determinisme Waktu (Aturan A8):** Tidak ada panggilan `now()`, `Carbon::now()`, atau `date()` di dalam lapisan Domain dan Application; seluruh stempel waktu memakai `Modules\Core\Contracts\ClockInterface`.
3. **Strict Types & FK Eksplisit (A11 & A12):** Setiap berkas PHP memakai `declare(strict_types=1);` dan setiap kolom `*_id` di migrasi MySQL/SQLite wajib bertipe identik dengan kolom target disertai constraint *foreign key* eksplisit.
4. **Kompatibilitas Skema Dua Mesin Database:** Seluruh migrasi `sct_*` berjalan deterministik baik di SQLite (unit/feature suite) maupun MySQL 8.4 (DB Portability Gate).
