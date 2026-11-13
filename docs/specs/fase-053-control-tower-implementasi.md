# Spesifikasi Perilaku, Kontrak Data & Kriteria Penerimaan — Fase 53: Supply Chain Control Tower & S&OP

**Modul Pemilik:** `Modules\ControlTower` (`sct_`)  
**Cakupan:** D53-1 .. D53-10, I1 .. I3, X53-1 .. X53-4, P1–P4 Quality Gates  
**Acuan Non-Ledger:** `ADR-053` (`RAB Posting Ledger = 0`)  

---

## 1. Spesifikasi Kontrak Lintas Modul (Boundary & Payload)

### 1.1 `Modules\ControlTower\Contracts\ItemAvailabilityReader`
- **Method:** `positionFor(string $itemCode, string $asOfDate): array`
- **Skema Kembalian Wajib:**
  - `on_hand` (`int >= 0`): agregat fisik seluruh bin pada seluruh gudang yang berelasi ke SKU `$itemCode`.
  - `available` (`int >= 0`): kuantitas bersih setelah dikurangi karantina, blokir/hold, dan reservasi aktif pada tanggal `$asOfDate`.
  - `quarantine` (`int >= 0`): stok berstatus karantina/inspeksi QMS yang **wajib dikecualikan** dari kalkulasi `available` dan ATP.
  - `hold` (`int >= 0`): stok yang ditahan/diblokir untuk keperluan kualitas atau reservasi aktif.
  - `oldest_lot_age_days` (`int >= 0`): umur lot tertua dalam hari bulat terhadap `$asOfDate` sebagai masukan `InventoryHealthAnalyzer` dan `OrderRiskScorer`.
- **Invarian Batas Modul:** Implementasi `WmsItemAvailabilityReader` wajib berkomunikasi lewat `Modules\Wms\Contracts\StockAvailabilityReader`, `Modules\Store\Contracts\Product`, `Modules\Logistics\Contracts\InTransitShipmentProvider`, dan `EchelonStock`, tanpa menyentuh tabel berprefiks `wms_*`, `store_*`, maupun `lgx_*` via `DB::table`.

### 1.2 `Modules\Manufacturing\Contracts\CapacityAndMaterialProvider`
- **Method 1:** `cumulativeByPeriod(string $itemCode, int $periods, string $asOfDate): array`
  - Mengembalikan `['capacity_cumulative' => list<int>, 'material_cumulative' => list<int>]`.
  - Panjang kedua daftar wajib tepat sama dengan `$periods`; indeks `0` merepresentasikan periode `1`.
  - Barisan nilai wajib monoton tidak menurun ($\forall k > 0, \; x_k \ge x_{k-1}$).
- **Method 2:** `mpsReceipts(string $itemCode, int $periods, string $asOfDate): array`
  - Mengembalikan daftar integer sepanjang `$periods` dari jadwal penerimaan produksi (`mfg_mps_lines` + `mfg_scheduled_receipts`) milik material terkait.
- **Isolasi A2:** Implementasi `ManufacturingCapacityAndMaterialProvider` berada di `Modules\Manufacturing\Infrastructure\Adapters\` dan hanya memanggil tabel berprefiks `mfg_*`.

### 1.3 `Modules\Distribution\Contracts\OpenDemandProvider`
- **Method 1:** `commitmentsByPeriod(string $itemCode, int $periods, string $asOfDate): array`
  - Mengagregasi sisa kuantitas pesanan distribusi terbuka (`draft`, `allocated`, `partial`, `backordered`) dari `dist_orders` dan `dist_order_lines` ke dalam bucket periode `1..$periods` berdasar selisih minggu dari `$asOfDate`.
- **Method 2:** `fulfillmentLines(string $fromDate, string $toDate): array`
  - Mengembalikan daftar terurut `(due_date, order_ref)` berstruktur:
    `order_ref`, `customer_ref`, `item_code`, `ordered_qty` (`int`), `delivered_qty` (`int`), `due_date` (`YYYY-MM-DD`), `delivered_date` (`?YYYY-MM-DD`).
- **Isolasi A2:** Implementasi `DistributionOpenDemandProvider` berada di `Modules\Distribution\Infrastructure\Adapters\` dan hanya membaca tabel berprefiks `dist_*`.

### 1.4 `Modules\Procurement\Contracts\SupplierLeadTimeStatistics`
- **Method 1:** `forSupplierAndItem(string $supplierRef, string $itemCode): array` -> `mean_days`, `sigma_days`, `samples`, `on_time_permil`. Jika belum ada sampel penerimaan barang (`samples === 0`), seluruh nilai dikembalikan sebagai `0`.
- **Method 2:** `primarySupplierForItem(string $itemCode): ?string` -> mengembalikan pemasok dengan jumlah sampel terbanyak, dan apabila seri (*tie*), memilih `supplier_ref` terkecil secara leksikografis.
- **Method 3:** `allStatistics(): array` -> daftar seluruh pasangan pemasok-SKU terurut `(supplier_ref, item_code)`.
- **Method 4:** `monthlyHistory(string $supplierRef, int $months, string $asOfDate): array` -> riwayat bulanan dari lama ke baru hingga `$months` bulan terakhir sebelum/pada `$asOfDate`.
- **Isolasi A2:** Implementasi `ProcurementSupplierLeadTimeStatistics` di `Modules\Procurement\Infrastructure\Adapters\` hanya meng-query tabel `prc_*` serta membaca nama pemasok lewat model kontrak `Modules\Supplier\Contracts\Supplier`.

### 1.5 `Modules\ControlTower\Contracts\PromiseQuoteInterface`
- **Method:** `quote(string $itemCode, int $quantity, int $requestedPeriod, string $idempotencyKey, ?string $orderReference = null, ?string $asOfDate = null): array`
- **Idempotency & Concurrency:**
  - Dieksekusi di dalam `DB::transaction`.
  - Kunci `idempotency_key` sama dengan `payload_hash` identik mengembalikan hasil janji persis sama dari baris `sct_order_promises` tanpa membuat duplikat atau memancarkan event ulang.
  - Kunci `idempotency_key` sama dengan `payload_hash` berbeda melempar pengecualian konflik idempotensi deterministik.

---

## 2. Spesifikasi State Machine S&OP 7-Tahap (`SopCycleStateMachine`)

### 2.1 Peta Transisi Kanonik & Kompatibilitas Historis
1. **Jalur Resmi 7-Tahap (`canonicalStates()`):**
   - `data_gathering` -> `demand_review`
   - `demand_review` -> `supply_review` | `data_gathering` (mundur ber-alasan)
   - `supply_review` -> `pre_sop` | `demand_review` (mundur ber-alasan)
   - `pre_sop` -> `exec_sop` | `supply_review` (mundur ber-alasan)
   - `exec_sop` -> `published` | `pre_sop` (mundur ber-alasan)
   - `published` -> `closed`
   - `closed` -> `[]` (terminal; tidak dapat berpindah lagi)
2. **Jalur Kompatibilitas (`draft..archived`):**
   - `draft` -> `demand_review`
   - `demand_review` -> `draft` | `supply_review`
   - `supply_review` -> `pre_sop_balancing`
   - `pre_sop_balancing` -> `executive_signoff` | `supply_review`
   - `executive_signoff` -> `published` | `pre_sop_balancing`
   - `published` -> `archived`
   - `archived` -> `[]` (terminal)

### 2.2 Aturan Governance & Integrasi Core
- **Alasan Mundur Wajib (`assertTransitionWithReason`):** Setiap transisi ke tahap berperingkat lebih rendah mewajibkan string `$reason` non-kosong.
- **Kotak Masuk Tugas (`TaskInboxService`):** Saat siklus memasuki `demand_review`, `supply_review`, atau `pre_sop`, orkestrator menerbitkan tugas kerja ke kotak masuk peran terkait agar tinjauan bulanan terlacak dengan SLA jelas.
- **Four-Eyes Sign-Off (`ApprovalEngineInterface`):** Transisi ke `published` wajib menyertakan penyetuju berbeda dari `created_by_user_id` jika keduanya bernilai non-null. Pelanggaran menaikkan `FOUR_EYES_VIOLATION`.
- **Publikasi Event:** Perpindahan berhasil ke status `published` menyimpan stempel waktu `published_at` berbasis `ClockInterface` dan menerbitkan `tower.sop.published.v1`.

---

## 3. Spesifikasi Fungsional X53-1 .. X53-4 & Twin Sandbox

### 3.1 `X53-1` — `OrderRiskScorer`
- Masukan mencakup `item_code`, `requested_qty`, `available_qty`, `oldest_lot_age_days`, `supplier_ref`, `warehouse_code`, `order_reference`.
- Skor akhir merupakan penjumlahan 4 komponen integer (`0..400` + `0..250` + `0..200` + `0..150`) dengan batas atas keras `1000`.
- Rekomendasi mitigasi tersusun deterministik menyebut faktor dominan tertinggi (`Percepat PO pemasok`, `Prioritaskan rotasi FEFO lot tertua`, `Kirim ulang in-transit atau realokasi DC`).
- `AlertScannerService` menduplikasi deteksi berdasarkan `dedup_key = sha256(...)` sehingga menjalankan `tower:alerts-scan` berulang kali bersikap idempoten 100%.
- Pemindahan stok antar-gudang (`ExecuteAutonomousReallocationPlaybookAction::proposeReallocation`) membentuk proposal teraudit yang baru mengubah saldo node setelah dipanggil melalui `approveReallocation`.

### 3.2 `X53-2` — `SupplierScorecardService`
- Menghitung empat sub-skor integer `0..1000`:
  - `otd_score`: langsung memetakan `on_time_permil` (`0..1000`).
  - `reliability_score`: `1000` bila `sigma_days == 0 && samples > 0`, berkurang proporsional sesuai rasio `sigma_days / max(1, mean_days)`.
  - `volume_score`: berdasar kecukupan `samples` (`>= 10` sampel -> `1000`, `1..9` sampel -> `samples * 100`, `0` -> `0`).
  - `composite_score = intdiv(45 * otd_score + 30 * reliability_score + 25 * volume_score, 100)`.
- Jika `samples === 0`, `composite_score = 0` dan tier ditetapkan menjadi `AT_RISK` dengan rekomendasi onboarding evaluasi pemasok.

### 3.3 `X53-3` — `InventoryHealthAnalyzer`
- Menghitung `dos_days`: bila `daily_demand_qty > 0`, maka `intdiv(on_hand_qty, daily_demand_qty)`; bila `daily_demand_qty == 0` dan `on_hand_qty > 0`, dianggap tidak berputar (`DEAD_STOCK`).
- Pembagian bucket umur (`0-30`, `31-90`, `91-180`, `>180`) selalu menggunakan batas inklusif yang saling lepas sehingga setiap baris masuk ke tepat satu bucket.
- Eksposur obsolet (`obsolescence_risk_idr`) dihitung memakai pembobot integer (`100%` dari nilai tertahan untuk `DEAD_STOCK`, `50%` untuk `SLOW_MOVING`, `20%` untuk `EXCESS`, `0` untuk `SHORTAGE_RISK`/`HEALTHY`).

### 3.4 `X53-4` — `ForecastOverrideService`
- Menyimpan catatan historis sebelum dan sesudah pada `sct_forecast_overrides` dengan `previous_qty` asli tetap terjaga agar fungsi `revertOverride` mampu memulihkan `DemandForecast::forecast_qty` secara eksak.
- Deviasi dihitung dalam `bps`: `intdiv(abs(newQty - baselineQty) * 10_000, max(1, baselineQty))`.

### 3.5 Digital Twin Zero-Mutation Guarantee
- Tabel yang di-hash sebelum dan sesudah `TwinWhatIfOrchestratorService::runScenario()`:
  1. `sct_echelon_stocks` (`id, item_code, echelon_node, on_hand_qty, in_transit_qty, reserved_qty, safety_stock_qty`)
  2. `sct_order_promises` (`id, promise_code, requested_qty, atp_confirmed_qty, ctp_manufacturing_qty, promise_status`)
  3. `sct_disruption_alerts` (`id, alert_code, severity, status`)
- Setiap eksekusi skenario mengembalikan struktur laporan yang memuat `before_hash`, `after_hash`, dan `mutated_live_tables: false`.

---

## 4. Kriteria Penerimaan Pengujian (Verification Checklist)

1. Seluruh fitur pengujian di `modules/ControlTower/tests/Feature/Slice{1..7}*Test.php`, `ControlTowerTest.php`, `ControlTowerOperationsSliceTest.php`, dan `tests/Acceptance/ControlTower/ControlTowerSopAndTwinAcceptanceTest.php` lulus `100%` (`0` skipped, `0` failed).
2. Eksekusi `T-1c` (`/tmp/spek2.sh 53`) menghasilkan `HILANG: 0` dari `44` item kode, tabel, CLI command, event, dan rute web yang diaudit.
3. Seluruh views web Control Tower lulus `php artisan ui:lint --render --responsive` pada lebar viewport `375px` tanpa overflow horizontal dan memakai komponen `<x-ds.*>`.
4. `php artisan arch:scan --json` tidak menambah 1 pun pelanggaran di luar baseline terpasang (`A1`, `A2`, `A12`), dengan `A3=0`, `A6=0`, `A8=0`, `A11=0`.
5. `php artisan tower:audit` lulus bersih exit code `0` dalam kondisi sehat dan gagal deterministik exit code `1` setelah fixture sabotase disuntikkan.
