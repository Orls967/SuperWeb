# Kontrak API, Rute Web, & Integrasi Lintas Modul WMS

## 1. Rute Web & RBAC (`modules/Wms/routes/web.php`)

Seluruh rute berada di bawah middleware `['web', 'auth', 'verified']` dan prefiks `/wms` (`name('wms.')`).

| Method & URI | Nama Rute | Role Diizinkan | Controller & Aksi |
|---|---|---|---|
| `GET /wms` | `wms.index` | `admin, hub_operator, procurement, auditor, logistics_admin` | `WmsController@index` |
| `GET /wms/tasks` | `wms.tasks` | `admin, hub_operator, procurement, auditor, logistics_admin` | `WmsOperationsController@tasks` |
| `GET /wms/valuation` | `wms.valuation` | `admin, hub_operator, procurement, auditor, logistics_admin` | `WmsOperationsController@valuation` |
| `GET /wms/receiving` | `wms.receiving` | `admin, hub_operator, procurement, auditor, logistics_admin` | `WmsOperationsController@receiving` |
| `GET /wms/quarantine` | `wms.quarantine` | `admin, hub_operator, procurement, auditor, logistics_admin` | `WmsOperationsController@quarantine` |
| `GET /wms/utilization` | `wms.utilization` | `admin, hub_operator, procurement, auditor, logistics_admin` | `WmsOperationsController@utilization` |
| `GET /wms/export/valuation.csv` | `wms.export.valuation` | `admin, hub_operator, procurement, auditor, logistics_admin` | `WmsOperationsController@exportValuationCsv` |
| `GET /wms/export/bin-stocks.csv` | `wms.export.bin-stocks` | `admin, hub_operator, procurement, auditor, logistics_admin` | `WmsOperationsController@exportBinStocksCsv` |
| `POST /wms/operations/putaway` | `wms.operations.putaway` | `admin, hub_operator, logistics_admin` | `WmsOperationsController@executePutaway` |
| `POST /wms/operations/wave-picking` | `wms.operations.wave-picking` | `admin, hub_operator, logistics_admin` | `WmsOperationsController@allocateWavePicking` |
| `POST /wms/operations/receive-inbound` | `wms.operations.receive-inbound` | `admin, hub_operator, logistics_admin` | `WmsOperationsController@receiveInbound` |
| `POST /wms/operations/quarantine/{binStockId}/release` | `wms.operations.quarantine.release` | `admin, hub_operator, logistics_admin` | `WmsOperationsController@releaseQuarantine` |
| `POST /wms/operations/utilization/{warehouseId}/snapshot` | `wms.operations.utilization.snapshot` | `admin, hub_operator, logistics_admin` | `WmsOperationsController@captureUtilization` |
| `POST /wms/operations/revalue-cost` | `wms.operations.revalue-cost` | `admin` | `WmsOperationsController@revalueCost` |

---

## 2. Kontrak PHP Lintas Modul

### 2.1 `Modules\Wms\Contracts\StockAvailabilityReader`
Digunakan oleh modul `Store`, `Order`, dan `Logistics` untuk mengetahui stok layak jual di gudang:
```php
public function getAvailableQty(int $productId, string|int|null $warehouseId = null): int;
public function getBreakdown(int $productId, string|int $warehouseId): array;
```
Rumus deterministik: `available_base = max(0, on_hand_base - quarantined_base - reserved_base)`.

### 2.2 `Modules\Wms\Contracts\WarehouseValuationReader`
Digunakan oleh `EnterpriseFinance` dan `wms:audit` untuk rekonsiliasi aset persediaan:
```php
public function getWarehouseValuationIdr(string|int $warehouseId): array;
public function getAllWarehousesValuationIdr(): array;
```

### 2.3 `Modules\Inventory\Contracts\InventoryBalanceProvider`
Kontrak aditif pada modul `Inventory` (diimplementasikan oleh `InventoryBalanceProviderAdapter`) untuk memasok saldo `products.stock` saat audit rekonsiliasi lintas modul.

### 2.4 `Modules\Logistics\Contracts\InTransitShipmentProvider`
Kontrak aditif pada modul `Logistics` (diimplementasikan oleh `InTransitShipmentProviderAdapter`) untuk memantau pengiriman `in_transit` menuju/dari gudang serta mendeteksi keterlambatan SLA (`getOverdueInTransitShipments`).

### 2.5 `Modules\Logistics\Contracts\CarrierCutoffProvider`
Kontrak aditif pada modul `Logistics` (diimplementasikan oleh `CarrierCutoffProviderAdapter`) untuk membaca jadwal cut-off kurir (`JNE`, `SICEPAT`, `ANTERAJA`) per gudang.

---

## 3. Payload Event Domain (`v1`)

### 3.1 `warehouse.task.confirmed.v1`
```json
{
  "event": "warehouse.task.confirmed.v1",
  "task_id": 101,
  "warehouse_id": 1,
  "kind": "putaway",
  "actor_user_id": 7,
  "occurred_at": "2026-11-13T10:00:00+00:00"
}
```

### 3.2 `warehouse.stock.adjusted.v1`
```json
{
  "event": "warehouse.stock.adjusted.v1",
  "warehouse_id": 1,
  "product_id": 42,
  "delta_qty_base": -5,
  "delta_value_idr": -125000,
  "reason": "cycle_count:CC-20261113-01",
  "occurred_at": "2026-11-13T10:05:00+00:00"
}
```

### 3.3 `warehouse.transfer.received.v1`
```json
{
  "event": "warehouse.transfer.received.v1",
  "transfer_id": 88,
  "from_warehouse_id": 1,
  "to_warehouse_id": 2,
  "total_value_idr": 5000000,
  "occurred_at": "2026-11-13T11:30:00+00:00"
}
```
