# Keamanan, Kontrol Akses (RBAC/Policy), & Validasi Input WMS

## 1. Lapisan Pertahanan Keamanan
Modul `Wms` menerapkan empat lapisan pertahanan (*defense-in-depth*) untuk setiap titik masuk HTTP dan konsol:
1. **Middleware Rute (`routes/web.php`)**:
   - Seluruh rute `GET` dan `POST` berada di dalam grup `['web', 'auth', 'verified']`.
   - Rute baca (`/wms`, `/wms/tasks`, `/wms/valuation`, `/wms/receiving`, `/wms/quarantine`, `/wms/utilization`, `/wms/export/*.csv`) mensyaratkan salah satu peran: `admin`, `hub_operator`, `procurement`, `auditor`, `logistics_admin`.
   - Rute mutasi operasional (`POST /wms/operations/*`) dibatasi hanya untuk `admin`, `hub_operator`, `logistics_admin`.
   - Rute finansial sensitif (`POST /wms/operations/revalue-cost`) dibatasi ketat hanya untuk `admin`.
2. **Policy Domain (`WarehouseOperationPolicy`)**:
   - `viewAny(User)`: mengizinkan 5 peran resmi WMS.
   - `executeTask(User)`: mengizinkan `admin`, `hub_operator`, `logistics_admin`.
   - `quarantineStock(User)`: mengizinkan `admin`, `hub_operator`, `auditor`.
   - `revalueCost(User)`: mengizinkan `admin` saja.
3. **FormRequest Validation**:
   - `StoreDirectedPutawayRequest`: memvalidasi `warehouse_id`, `product_id`, `qty_base` (integer `>= 1`), `abc_class` (`A|B|C`), `unit_cost_idr` (`>= 0`).
   - `StoreWavePickingRequest`: memvalidasi array `lines` (`min:1`) dan setiap elemen `qty_base >= 1`.
   - `StoreInboundReceivingRequest`: memvalidasi `expected_qty_base >= 1`, `received_qty_base >= 0`, dan boolean `approve_over_receipt`.
   - `RevalueInventoryCostRequest`: melakukan pengecekan `authorize()` (`role === 'admin'`) dan memvalidasi `method` (`std|avg|fifo`) serta `new_cost_idr >= 0`.
4. **Invarian Domain & Ledger**:
   - `WmsLedgerPoster` menolak nominal `< 0`.
   - `WarehouseTaskStateMachine` menolak transisi ilegal dan mencatat `actor_user_id` di `wms_task_events`.

---

## 2. Keamanan Data & Pencegahan Anomali
- **Tanpa `float` pada uang & kuantitas**: Menghindari manipulasi presisi atau selisih pembulatan pada perhitungan nilai persediaan dan tagihan 3PL.
- **Query Terparameterisasi**: Seluruh query `DB::table()` dan Eloquent menggunakan *parameter binding* penuh untuk mencegah SQL Injection.
- **Kontrol Ekspor CSV**: Endpoint `/wms/export/valuation.csv` dan `/wms/export/bin-stocks.csv` hanya mengekspos kolom operasional terstruktur (`warehouse_code`, `account_code`, `subledger_value_idr`, `ledger_balance_idr`, `discrepancy_idr`, `qty_base`, `lot_number`, `stock_status`) dan tidak pernah mengekspos kredensial maupun data PII pengguna.

---

## 3. Audit Trail & Non-Repudiation
Setiap tindakan krusial meninggalkan jejak permanen:
- Transisi tugas → `wms_task_events` (`actor_user_id`, `from_status`, `to_status`, `reason`, `created_at`).
- Penerimaan barang → `wms_receiving_receipts` (`received_by_user_id`, `variance_bps`, `tolerance_decision`).
- Perubahan harga pokok → `wms_unit_costs` (`created_by_user_id`, `effective_date`, `is_active`).
- Mutasi keuangan → `bank_ledger_transactions` (`idempotency_key`, `reference_type`, `reference_id`).
