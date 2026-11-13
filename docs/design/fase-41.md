# Desain Fase 41 — WMS Lanjutan: Gudang, Penilaian Persediaan & Robotika Dasar

## 1. Ringkasan

Fase 41 melengkapi modul `Wms` dengan:
- **Algoritma** H1 (`PutawaySlotter`), H2 (`PickPathPlanner`), H3 (`CycleCountScheduler`) + Sertifikat & Verifier
- **Normalisasi Kuantitas** `QuantityNormalizer` (D81, integer base unit, bebas `float`)
- **State Machine** `WarehouseTaskStateMachine` 7-state (`created -> assigned -> in_progress -> confirmed -> exception -> cancelled -> closed`) + log append-only `wms_task_events`
- **Posting Ledger** 12 kejadian uang via `WmsAccounts` (W8-D83) + `WmsLedgerPoster`
- **Kontrak** `StockAvailabilityReader`, `WarehouseValuationReader`, `InventoryBalanceProvider`, `InTransitShipmentProvider`, `CarrierCutoffProvider`
- **Adaptor** `WmsSubledgerTotalAdapter` (`SubledgerTotalProvider` untuk period closing EnterpriseFinance)
- **Fitur Lanjutan (X41-1..X41-5)**: `ReceivingToleranceEvaluator`, `StockReservationService`, `LotQuarantineService`, `WarehouseUtilizationAnalyzer` + `SlottingScenarioPlanner`, `WmsAuditCorruptionFixture`

## 2. Diagram Alur Putaway (H1)

```
GRN tiba di Dock
    │
    ▼
ReceivingToleranceEvaluator (X41-1: within_tolerance / over_receipt / short_receipt)
    │
    ▼
PutawaySlotter.plan(q, abcClass, bins)
    │  ① Single-bin candidate (free >= q):
    │     - A: min (distance, free-q, id)
    │     - B: min (free-q, distance, id)
    │     - C: min (-distance, free-q, id)
    │  ② Fallback split greedy jika tidak ada single bin cukup
    ▼
ExecuteDirectedPutawayAction
    │  ③ Validasi kapasitas & update wms_bin_stocks (qty_base integer +=)
    │  ④ Transisi task via WarehouseTaskStateMachine -> confirmed
    │  ⑤ Publish warehouse.task.confirmed.v1
    │  ⑥ POST ledger D41-2: Debit wms:inventory:{gudang}:IDR / Kredit wms:receiving_clearing:IDR
    ▼
Task closed
```

## 3. Diagram Alur Pick (H2)

```
Wave dialokasi + StockReservationService (X41-2)
    │
    ▼
PickPathPlanner.plan(w, L, picks)
    │  ① Evaluasi S_SHAPE vs LARGEST_GAP (integer arithmetic)
    │  ② Tie-breaking: S_SHAPE
    │  ③ Output ordered picks + total_distance + strategy + Certificate
    ▼
Operator pick per urutan
    │  ④ Update wms_bin_stocks (qty_base integer -=) & commit reservation
    │  ⑤ Publish warehouse.task.confirmed.v1
    │  ⑥ POST ledger D41-3: Debit wms:issue_clearing:IDR / Kredit wms:inventory:{gudang}:IDR
    ▼
Pack → Packing List → Dispatch ke Logistics
```

## 4. State Machine WarehouseTask (D41-1, 7-State)

```
created ──(assign)──► assigned ──(start)──► in_progress ──(confirm)──► confirmed ──(close)──► closed
   │                     │                       │                         │
   │                     │                  (flag_exception)               │
   │                     │                       ▼                         │
   │                     │                   exception ──(resolve/retry)───┘
   │                     │                       │
   └──────(cancel)───────┴──────(cancel)─────────┴──► cancelled
```

Terminal states: `closed`, `cancelled`. Semua transisi dicatat ke `wms_task_events`.

## 5. Penilaian Persediaan & Rekonsiliasi Ledger

```
Σ(wms_bin_stocks.qty_base × wms_unit_costs.cost_idr) per gudang
    ==
Saldo Akun Ledger `wms:inventory:{kode_gudang}:IDR`
```

Setiap mutasi stok (`OpeningBalance`, `Inbound`, `Outbound`, `TransferOut`, `TransferIn`, `CycleCountGain`, `CycleCountShrinkage`, `WriteOff`, `TransitLoss`, `CostRevaluationMarkUp/MarkDown`, `3PlBilling`) mengeksekusi `WmsLedgerPoster` secara atomik dalam `DB::transaction` dengan idempotency key `IDEM:WMS:{AKSI}:{ID}`.


## 7. Matriks Rekonsiliasi Ledger & Subledger (D41-9)

Setiap mutasi nilai persediaan di modul `Wms` wajib mem-posting jurnal berpasangan melalui `WmsLedgerPoster` sehingga saldo akun buku besar `wms_inventory_{warehouseId}` (dengan konvensi single-sign `debit = -`, `credit = +`) selalu sama dengan `-(int) $cached_balance === sum(wms_bin_stocks.qty_base * wms_unit_costs.unit_cost_idr)`.

| Kode Kejadian | TransactionType (<=32 char) | Akun Debit (`-`) | Akun Kredit (`+`) | Dampak Nilai Gudang |
| :--- | :--- | :--- | :--- | :--- |
| `opening` | `WMS_OPENING_BALANCE` | `wms_inventory_{id}` | `wms_opening_offset` | Bertambah sebesar `qty_base * unit_cost_idr` |
| `receive` | `WMS_GOODS_RECEIPT` | `wms_inventory_{id}` | `wms_grni_clearing` | Bertambah sesuai nilai penerimaan PO/ASN |
| `receive_var` | `WMS_RECEIVING_VARIANCE` | `wms_receiving_variance` / `wms_inventory_{id}` | `wms_inventory_{id}` / `wms_receiving_variance` | Penyesuaian selisih toleransi X41-1 |
| `pick_ship` | `WMS_OUTBOUND_PICK_SHIP` | `wms_cogs_outbound` | `wms_inventory_{id}` | Berkurang saat dispatch/picking keluar |
| `cycle_pos` | `WMS_CYCLE_COUNT_GAIN` | `wms_inventory_{id}` | `wms_cycle_count_gain` | Bertambah akibat selisih lebih hitung siklus |
| `cycle_neg` | `WMS_CYCLE_COUNT_LOSS` | `wms_cycle_count_loss` | `wms_inventory_{id}` | Berkurang akibat selisih kurang hitung siklus |
| `scrap` | `WMS_STOCK_SCRAP_WRITEOFF` | `wms_scrap_writeoff` | `wms_inventory_{id}` | Berkurang akibat pemusnahan lot rusak/kedaluwarsa |
| `revalue` | `WMS_COST_REVALUATION` | `wms_inventory_{id}` / `wms_revaluation_reserve` | `wms_revaluation_reserve` / `wms_inventory_{id}` | Penyesuaian nilai per unit biaya standar/WAC |
| `transfer_out` | `WMS_INTER_WH_TRANSFER_OUT` | `wms_intransit_clearing` | `wms_inventory_{src}` | Berkurang di gudang asal, masuk clearing |
| `transfer_in` | `WMS_INTER_WH_TRANSFER_IN` | `wms_inventory_{dst}` | `wms_intransit_clearing` | Bertambah di gudang tujuan, clearing kembali 0 |
| `billing_3pl` | `WMS_3PL_STORAGE_BILLING` | `wms_3pl_receivable` | `wms_3pl_revenue` | Pendapatan jasa penyimpanan & handling 3PL |
| `transit_sweep` | `WMS_TRANSIT_SWEEP_WRITEOFF` | `wms_transit_loss` | `wms_intransit_clearing` | Penghapusan barang in-transit kadaluwarsa/hilang |

## 8. Daftar Rute HTTP & Akses CLI

### Rute Web (`modules/Wms/routes/web.php`)
- `GET /wms/operations/tasks` — Daftar tugas gudang & antrean handheld (responsif 375px).
- `POST /wms/operations/tasks/{task}/transition` — Transisi state machine 7-tahap dengan RBAC `WarehouseOperationPolicy`.
- `GET /wms/operations/valuation` — Rekonsiliasi nilai persediaan fisik vs ledger `WmsSubledgerTotalAdapter`.
- `POST /wms/operations/valuation/reconcile` — Eksekusi rekonsiliasi & ekspor laporan varians.
- `GET /wms/operations/receiving` — Monitoring penerimaan & klasifikasi toleransi X41-1.
- `POST /wms/operations/receiving` — Pencatatan penerimaan ASN/PO dengan normalisasi unit D81.
- `GET /wms/operations/quarantine` — Daftar lot karantina FEFO & inspeksi retur X41-3.
- `POST /wms/operations/quarantine/release` — Pelepasan atau pemusnahan lot karantina (wajib `supervisor_user_id` jika write-off).
- `GET /wms/operations/utilization` — Analisis utilisasi kapasitas zona/rak/bin & simulasi skenario slotting X41-4.
- `GET /wms/operations/exports/{type}` — Ekspor CSV (`tasks`, `valuation`, `utilization`, `quarantine`).

### Perintah Artisan (`WmsServiceProvider`)
1. `php artisan wms:audit {--warehouse=} {--json}` — Audit integritas saldo negatif (`qty_base < 0`), task yatim, dan rekonsiliasi ledger (`WarehouseValuationReader`).
2. `php artisan wms:replenish {--warehouse=}` — Pembuatan tugas replenishment min-max pick-face.
3. `php artisan wms:cycle-count-plan {--warehouse=}` — Penjadwalan cycle count ABC tersertifikasi (H3).
4. `php artisan wms:transit-sweep {--hours=72}` — Penyapuan shipment in-transit yang melewati batas SLA.
5. `php artisan wms:post-opening-balance` — Posting saldo awal persediaan ke subledger & ledger.
6. `php artisan wms:revalue-cost` — Revaluasi biaya per unit gudang dengan jurnal selisih otomatis.
7. `php artisan wms:reservation-sweep` — Pembersihan reservasi stok yang kedaluwarsa (X41-2).
8. `php artisan wms:utilization-snapshot` — Pengambilan snapshot utilisasi harian ke `wms_utilization_snapshots`.

## 9. Mesin Status Tugas Gudang (`WarehouseTaskStateMachine`)

Transisi status tugas dikontrol secara deterministik tanpa melompati tahap wajib:
1. `created` -> `assigned` | `cancelled`
2. `assigned` -> `in_progress` | `cancelled`
3. `in_progress` -> `confirmed` | `exception` | `cancelled`
4. `exception` -> `in_progress` | `cancelled`
5. `confirmed` -> `closed`
6. `cancelled` -> (terminal)
7. `closed` -> (terminal)

Setiap transisi mencatat jejak audit ke `wms_task_events` lengkap dengan `actor_user_id`, `from_state`, `to_state`, dan `reason` (wajib diisi untuk `exception` dan `cancelled`).
