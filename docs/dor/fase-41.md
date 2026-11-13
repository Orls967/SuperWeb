# Definition of Ready (DoR) — Fase 41: Gudang & Pusat Distribusi (WMS Lanjutan)

**ID Fase**: Fase 41
**Modul Pemilik**: `Wms` (`wms_`)
**Tautan Rencana**: `rencana-gelombang-8-25.md` (§W8-P6)
**Target Garis Kode (B1)**: >= 8.000 baris logika murni (target 8.400)
**Target Dokumentasi (B3)**: >= 1.800 baris tulis-tangan (target 1.950)

---

## 1. Identitas & Ruang Lingkup

- **Nama Modul**: Wms (Warehouse Management System)
- **Prefiks Tabel**: `wms_`
- **Prefiks Akun Ledger**: `wms:` (via `WmsAccounts`, 12 akun standar W8-D83 / D41-9)
- **Roles Berhak**: `admin`, `hub_operator`, `procurement`, `auditor`, `logistics_admin`
- **Tingkat Simulasi**: S2 (data gudang deterministik berversi; tidak ada feed real-time)
- **Tier Skala**: T1 (>= 3 gudang, >= 9 zona, >= 20 rak, >= 100 bin, >= 500 baris stok bin, >= 200 tugas, >= 50 transfer, >= 10 cycle count, >= 20 replenishment)

---

## 2. Rencana Anggaran Baris (RAB) — Tabel T-2

| Lapisan | Rentang Rencana (Baris) | Penjelasan Komponen |
|---|---|---|
| **Algoritma & Value Object** | 1.100–1.500 | `PutawaySlotter` (H1), `PickPathPlanner` (H2), `CycleCountScheduler` (H3), `QuantityNormalizer` (D81), + Certificate & Verifier per algoritma |
| **State Machine** | 500–800 | `WarehouseTaskStateMachine` (7 status: `created`, `assigned`, `in_progress`, `confirmed`, `exception`, `cancelled`, `closed`), Guard rules, Transition table, `wms_task_events` |
| **Aplikasi (Action & Service)** | 2.400–3.200 | `ExecuteDirectedPutawayAction`, `AllocateOutboundWavePickingAction`, `RecordCycleCountAdjustmentAction`, `ProcessCrossDockingTransferAction`, `Calculate3PlStorageHandlingBillingAction`, `PostOpeningBalanceAction`, `RevalueInventoryCostAction`, `ReceiveInboundShipmentAction`, `ReceivingToleranceEvaluator` (X41-1), `StockReservationService` (X41-2), `LotQuarantineService` (X41-3), `WarehouseUtilizationAnalyzer` + `SlottingScenarioPlanner` (X41-4), `ReplenishPickFaceAction`, `SweepInTransitAction` |
| **Kontrak & Adaptor** | 600–900 | `StockAvailabilityReader`, `WarehouseValuationReader`, `InventoryBalanceProvider`, `InTransitShipmentProvider`, `CarrierCutoffProvider`, `WmsSubledgerTotalAdapter` (`SubledgerTotalProvider`) |
| **Posting Ledger** | 700–1.000 | `WmsLedgerPoster`, `WmsAccounts` (12 akun W8-D83), `WmsTransactionTypes`, 12 kejadian uang D41-9, keseimbangan dua kaki per transaksi |
| **HTTP (Controller, FormRequest, ViewModel)** | 1.000–1.500 | Controller tipis untuk tasks, putaway, picking, cycle-counts, transfers, docks, valuation, receiving, quarantine, utilization; FormRequests; Policy otorisasi; CSV export |
| **Command & Audit** | 700–1.000 | `wms:replenish`, `wms:cycle-count-plan`, `wms:transit-sweep`, `wms:post-opening-balance`, `wms:revalue-cost`, `wms:reservation-sweep`, `wms:utilization-snapshot`, `wms:audit` (diperluas dengan rekonsiliasi ledger + `WmsAuditCorruptionFixture` X41-5) |
| **Model & Migrasi** | 500–800 | `UnitCost`, `TaskEvent`, `StockReservation`, `UtilizationSnapshot`, `ReceivingReceipt`, `QuarantineHold`, migrasi `wms_unit_costs`, `wms_task_events`, `wms_stock_reservations`, alter `wms_bin_stocks` (lot, expiry, serial, status, qty_base) |
| **UI (Blade — tidak masuk B1)** | 600–1.000 | 10+ halaman Blade (`x-ds.*`, 375px mobile handheld) |
| **Test (tidak masuk B1)** | 600–900 | Acceptance tests (5+), unit tests H1-H3 & X41-1..X41-4, integrasi posting ledger, L.5 sabotage |
| **Total Target B1 (PHP logika murni)** | **8.000–10.700** | Memenuhi ambang keras B1 >= 8.000 (target 8.400) |

---

## 3. Skema Basis Data (`wms_*`)

### Sudah Ada (tidak mengedit migrasi lama):
1. **`wms_warehouses`**, 2. **`wms_zones`**, 3. **`wms_racks`**, 4. **`wms_bins`**, 5. **`wms_bin_stocks`**, 6. **`wms_tasks`**, 7. **`wms_waves`**, 8. **`wms_transfers`**, 9. **`wms_transfer_lines`**, 10. **`wms_cycle_counts`**, 11. **`wms_cycle_count_lines`**, 12. **`wms_replenishments`**, 13. **`wms_slottings`**, 14. **`wms_dock_appointments`**, 15. **`wms_packing_lists`**.

### Baru (migrasi aditif baru):
16. **`wms_unit_costs`** — biaya standar berversi (`id`, `product_id`, `warehouse_id NULLABLE`, `cost_idr` bigint minor units, `method` enum/string avg/std/fifo, `effective_date`, `created_by_user_id`, `is_active`)
17. **`wms_task_events`** — log transisi state machine (`id`, `task_id`, `from_status`, `to_status`, `actor_user_id`, `reason`, `payload_json`, `created_at`)
18. **`wms_stock_reservations`** — soft/hard reservation dengan TTL (`id`, `warehouse_id`, `product_id`, `bin_id`, `lot_number`, `qty_base` bigint, `reference_type`, `reference_id`, `status` active/committed/released/expired, `expires_at`, `created_at`, `updated_at`)
19. **`wms_bin_stocks` (alter aditif)** — tambah `lot_number`, `expiry_date`, `serial_number`, `stock_status` (`available`/`quarantine`/`blocked`/`reserved`), `qty_base` bigint, `quarantine_reason`, `quarantined_at`, `released_at`
20. **`wms_utilization_snapshots`** — snapshot utilisasi slot & volume per gudang/zona (`id`, `warehouse_id`, `zone_id`, `snapshot_date`, `total_bins`, `occupied_bins`, `utilization_bps`, `dead_stock_sku_count`, `slotting_mismatch_count`)
21. **`wms_receiving_receipts`** — catatan penerimaan GRN & evaluasi toleransi (X41-1)

---

## 4. Posting Ledger (D41-9 / W8-D83) — Tabel T-3

**RAB Posting**: 12 kejadian uang (ada kejadian uang riil di Fase 41 — nilai persediaan gudang harus cocok dengan buku besar).
Kunci Idempotensi: `IDEM:WMS:{AKSI}:{ID}`.
Konvensi: Debit (−), Kredit (+), Σ = 0 per transaksi (KONSEP §A2.1). Semua nilai integer IDR minor units.

| No | Kejadian Uang | Akun Debit (−) | Akun Kredit (+) |
|---|---|---|---|
| D41-1 | Saldo awal persediaan gudang | `wms:inventory:{gudang}:IDR` | `wms:opening_balance_equity:IDR` |
| D41-2 | Penerimaan stok (inbound GRN) | `wms:inventory:{gudang}:IDR` | `wms:receiving_clearing:IDR` |
| D41-3 | Pengeluaran stok (outbound pick/ship) | `wms:issue_clearing:IDR` | `wms:inventory:{gudang}:IDR` |
| D41-4 | Transfer keluar ke gudang tujuan | `wms:inventory_in_transit:IDR` | `wms:inventory:{gudang_asal}:IDR` |
| D41-5 | Transfer masuk dari in-transit | `wms:inventory:{gudang_tujuan}:IDR` | `wms:inventory_in_transit:IDR` |
| D41-6 | Cycle count positif (gain) | `wms:inventory:{gudang}:IDR` | `wms:inventory_gain:IDR` |
| D41-7 | Cycle count negatif (shrinkage) | `wms:inventory_shrinkage:IDR` | `wms:inventory:{gudang}:IDR` |
| D41-8 | Write-off stok rusak / kadaluarsa | `wms:inventory_writeoff:IDR` | `wms:inventory:{gudang}:IDR` |
| D41-9 | Transit loss (selisih transfer hilang) | `wms:transit_loss:IDR` | `wms:inventory_in_transit:IDR` |
| D41-10 | Revaluasi harga pokok (mark-up) | `wms:inventory:{gudang}:IDR` | `wms:cost_revaluation_variance:IDR` |
| D41-11 | Revaluasi harga pokok (mark-down) | `wms:cost_revaluation_variance:IDR` | `wms:inventory:{gudang}:IDR` |
| D41-12 | Tagihan 3PL storage & handling | `wms:3pl_receivable:IDR` | `wms:3pl_revenue:IDR` |

---

## 5. Algoritma Baru Bersertifikat (T-4)

### H1 — `PutawaySlotter`
- **Input**: `q` (int > 0), `abcClass` (`A`|`B`|`C`), `bins` (`id`, `distance`, `freeCapacity`).
- **Logika**:
  - Jika ada kandidat tunggal (`freeCapacity >= q`):
    - Kelas A: min `(distance ASC, freeCapacity - q ASC, id ASC)`
    - Kelas B: min `(freeCapacity - q ASC, distance ASC, id ASC)`
    - Kelas C: min `(-distance ASC, freeCapacity - q ASC, id ASC)` (terjauh)
  - Jika tidak ada kandidat tunggal: split greedy memakai urutan prioritas kelas di atas hingga `q` terpenuhi; jika total free < `q`, lempar `NO_CAPACITY`. `q <= 0` lempar `INVALID_QUANTITY`.
- **Sertifikat**: `PutawaySuggestionCertificate` + `PutawaySuggestionCertificateVerifier`.

### H2 — `PickPathPlanner`
- **Input**: `aisleWidth` `w`, `aisleLength` `L`, `picks` (`aisle`, `pos`).
- **Logika**: Bandingkan biaya lintasan `S_SHAPE` vs `LARGEST_GAP` (semua integer). Tie-breaker: `S_SHAPE`. Picks kosong: `EMPTY_PICK_LIST`.
- **Sertifikat**: `PickSequenceCertificate` + `PickSequenceCertificateVerifier`.

### H3 — `CycleCountScheduler`
- **Input**: `today` (day int / date), `items` (`id`, `abcClass`, `lastCountedDay`), `capacity` (int >= 0).
- **Logika**: Interval A=30, B=90, C=360. `overdue = today - (lastCountedDay + interval) >= 0`. Urutkan `(overdue DESC, abcClass ASC, id ASC)`. Ambil `capacity` item teratas sebagai `selected`, sisanya `carried_over`.
- **Sertifikat**: `CycleCountScheduleCertificate` + `CycleCountScheduleCertificateVerifier`.
