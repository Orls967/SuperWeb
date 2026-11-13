# Referensi DTO, Service, & Action Publik Modul WMS (Fase 41)

Dokumen ini menjadi referensi pengembang untuk seluruh kelas publik di lapisan **Application** dan **Domain** modul `Wms`.

---

## 1. Lapisan Application — Actions (`Modules\Wms\Application\Actions`)

### 1.1 `WmsLedgerPoster`
Orkestrator tunggal untuk 12 kejadian uang WMS:
- `postOpeningBalance(string $warehouseCode, int $amountIdr, string $referenceId, ?string $idempotencyKey = null): bool`
- `postInboundReceipt(string $warehouseCode, int $amountIdr, string $referenceId, ?string $idempotencyKey = null): bool`
- `postOutboundIssue(string $warehouseCode, int $amountIdr, string $referenceId, ?string $idempotencyKey = null): bool`
- `postTransferOut(string $originWarehouseCode, int $amountIdr, string $transferId, ?string $idempotencyKey = null): bool`
- `postTransferIn(string $destWarehouseCode, int $amountIdr, string $transferId, ?string $idempotencyKey = null): bool`
- `postCycleCountGain(string $warehouseCode, int $amountIdr, string $cycleCountId, ?string $idempotencyKey = null): bool`
- `postCycleCountShrinkage(string $warehouseCode, int $amountIdr, string $cycleCountId, ?string $idempotencyKey = null): bool`
- `postInventoryWriteOff(string $warehouseCode, int $amountIdr, string $referenceId, ?string $idempotencyKey = null): bool`
- `postTransitLoss(int $amountIdr, string $transferId, ?string $idempotencyKey = null): bool`
- `postCostRevaluationMarkUp(string $warehouseCode, int $amountIdr, string $referenceId, ?string $idempotencyKey = null): bool`
- `postCostRevaluationMarkDown(string $warehouseCode, int $amountIdr, string $referenceId, ?string $idempotencyKey = null): bool`
- `post3PlBilling(int $amountIdr, string $billingRef, ?string $idempotencyKey = null): bool`
- `getWarehouseLedgerBalanceIdr(string $warehouseCode): int`

### 1.2 Action Operasional Utama
- **`PostOpeningBalanceAction::execute(string|int $warehouseId, ?int $actorUserId = null, ?string $idempotencyKey = null)`**: Menghitung saldo awal gudang dan memposting D41-1 secara idempoten.
- **`RevalueInventoryCostAction::execute(int $productId, int $warehouseId, int $newCostIdr, string $method = 'std', ...)`**: Memperbarui `wms_unit_costs` dan memposting selisih D41-10 / D41-11.
- **`ReceiveInboundShipmentAction::execute(array $data)`**: Mencatat `ReceivingReceipt`, mengevaluasi toleransi X41-1, menambah `wms_bin_stocks`, dan memposting D41-2.
- **`ExecuteDirectedPutawayAction::execute(...)`**: Menjalankan `PutawaySlotter` (H1), membuat dan menutup `Task` via `WarehouseTaskStateMachine`, menerbitkan `WarehouseTaskConfirmedEvent`, dan memposting D41-2.
- **`AllocateOutboundWavePickingAction::execute(...)`**: Memilih stok FEFO, membuat dan meng-commit reservasi X41-2, menjalankan `PickPathPlanner` (H2), menerbitkan event, dan memposting D41-3.
- **`RecordCycleCountAdjustmentAction::execute(...)`**: Menghitung varians fisik vs sistem, menyesuaikan `wms_bin_stocks`, memposting D41-6 / D41-7, dan menerbitkan `WarehouseStockAdjustedEvent`.
- **`ProcessCrossDockingTransferAction::execute(...)`**: Memotong stok asal, menambah stok tujuan, memposting D41-4, D41-5, serta D41-9 (bila ada transit loss), dan menerbitkan `WarehouseTransferReceivedEvent`.
- **`Calculate3PlStorageHandlingBillingAction::execute(...)`**: Menghitung tagihan `pallet_days × storageRate + handlingUnits × handlingRate` dan memposting D41-12.
- **`ReplenishPickFaceAction::execute(...)`**: Membuat permintaan replenishment untuk zona `pick` di bawah ambang minimum.
- **`SweepInTransitAction::execute(int $maxHours = 72)`**: Memeriksa pengiriman in-transit overdue via `InTransitShipmentProvider`.
- **`ResolveTaskExceptionAction::flag(...)` / `resolve(...)`**: Menangani transisi status `exception` pada `WarehouseTaskStateMachine`.
- **`QuarantineExpiredLotsAction::execute()`**: Memindahkan lot kedaluwarsa otomatis ke status `quarantine`.

---

## 2. Lapisan Application — Services (`Modules\Wms\Application\Services`)

| Service | Tanggung Jawab |
|---|---|
| `ReceivingToleranceEvaluator` | Evaluasi selisih penerimaan GRN dalam integer `bps` (X41-1) |
| `StockReservationService` | Implementasi `StockAvailabilityReader` & reservasi stok TTL (X41-2) |
| `LotQuarantineService` | Karantina, pelepasan QA, dan write-off stok rusak/kadaluarsa (X41-3) |
| `WarehouseUtilizationAnalyzer` | Snapshot okupansi bin (`bps`) dan deteksi *dead stock* (X41-4) |
| `SlottingScenarioPlanner` | Simulasi *what-if* relokasi slotting berbasis `PutawaySlotter` (X41-4) |
| `WmsEventCatalog` | Penerbit outbox resmi untuk 3 event domain `v1` |
| `DockAppointmentScheduler` | Penjadwalan dock bay tanpa bentrok & cek `CarrierCutoffProvider` |
| `PackingListGenerator` | Kartonisasi deterministik dalam gram & cm³ |
| `CrossWarehouseRebalancer` | Proposal transfer stok untuk menyeimbangkan surplus & defisit antar-DC |
| `InventoryReconciliationService` | Rekonsiliasi menyeluruh stok WMS vs `InventoryBalanceProvider` & `WarehouseValuationReader` |
| `WmsKpiAggregator` | Agregasi KPI tugas, reservasi, dan karantina per gudang |
| `WmsCycleCountWorkflowService` | Orkestrasi jadwal harian H3 & persetujuan supervisor berbasis `CycleCountPolicy` |
| `WmsTransferWorkflowService` | Eksekusi multi-baris `TransferManifest` antar-gudang |
| `WmsTaskLifecycleService` | Antarmuka siklus hidup 7-state & kueri riwayat `wms_task_events` |
| `WmsAuditReportBuilder` | Penyusun laporan audit terstruktur untuk `wms:audit` |
| `WmsSlottingRecommendationService` | Penggabungan ABC velocity, slotting fitness, dan what-if planner |
| `WmsDockAndCutoffService` | Penjadwalan batch dock & ringkasan cut-off kurir |
| `WmsInboundReceivingWorkflowService` | Orkestrasi inbound GRN lengkap dengan evaluasi cross-dock |
| `WmsOutboundDispatchWorkflowService` | Orkestrasi wave picking, readiness check, alokasi, dan packing list |
| `WmsValuationReconciliationReportService` | Laporan gabungan nilai subledger vs buku besar seluruh gudang |
| `WmsReturnDispositionWorkflowService` | Disposisi barang retur (RMA): restock, quarantine, atau write-off |
| `WmsInventorySnapshotExporter` | Ringkasan ekspor stok & valuasi gudang |
| `WmsHandheldOperationService` | Validasi scan barcode bin/SKU & penyelesaian tugas handheld |
| `WmsWavePlanningBoardService` | Papan perencanaan wave picking & estimasi jam kerja |
| `WmsReplenishmentOrchestratorService` | Evaluasi min-max pick-face & pemicu replenishment |
| `WmsLotTraceabilityService` | Pelacakan jejak lot lintas GRN, bin stock, dan reservasi |
| `WmsZoneHeatmapService` | Peta panas kepadatan & okupansi per zona |
| `WmsCycleCountExecutionService` | Evaluasi hitung buta (*blind count*) & pemicu recount |
| `WmsCarrierManifestService` | Manifest serah-terima paket per kurir & status cut-off |
| `WmsStockExpiryAlertService` | Ringkasan eksposur nilai IDR untuk stok kedaluwarsa & mendekati kedaluwarsa |
| `WmsWeightedAverageCostService` | Kalkulator *moving weighted average* integer murni |
| `WmsShiftHandoverSummaryService` | Ringkasan operasional untuk serah-terima shift gudang |
| `WmsTaskAuditTrailService` | Ringkasan event transisi `wms_task_events` |
| `WmsSlottingAuditCommandHelper` | Ringkasan kesehatan zona, bin, biaya, dan exception per gudang |
| `WmsInventoryIntegrityVerifier` | Verifikasi integritas stok, over-reservation, missing cost, dan ledger |
| `WmsCrossModuleSyncService` | Snapshot sinkronisasi lintas modul WMS, Inventory, dan Logistics |

---

## 3. Lapisan Domain — Value Objects & Domain Services

- **Value Objects**: `BinCoordinate`, `ThreePlRateCard`, `WarehouseDimensions`, `AbcClassificationRule`, `PickingWaveSpec`, `CycleCountPolicy`, `TransferManifest`, `DockScheduleSlot`.
- **Domain Services**: `BinCapacityEvaluator`, `ShelfLifeGuard`, `StorageBillingCalculator`, `SlottingFitnessCalculator`, `ReceivingDiscrepancyClassifier`, `WaveReleaseReadinessChecker`, `TransitSlaEvaluator`, `PutawayRuleEngine`, `PickWavePriorityScorer`, `InventoryAgingAnalyzer`, `CycleCountVarianceAnalyzer`, `CrossDockEligibilityEvaluator`, `PalletizationPlanner`, `DockQueueDispatcher`, `ReturnGoodsInspectionService`, `LaborProductivityEstimator`, `CrossDockTransferValidator`.

