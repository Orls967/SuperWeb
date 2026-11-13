# Kamus Data, Skema Tabel, & Kebijakan Migrasi WMS (`wms_*`)

Dokumen ini mendefinisikan struktur tabel basis data modul `Wms`, pembagian antara tabel bawaan dan tabel aditif Fase 41, serta aturan integritas skema.

---

## 1. Kebijakan Migrasi Fase 41
- **Dilarang mengedit migrasi lama** (`2026_10_05_410100_create_wms_tables.php` dan `2026_10_16_000001_alter_wms_annual_value_idr_to_bigint.php`).
- Seluruh perubahan kolom atau penambahan tabel pada Fase 41 wajib ditempatkan pada migrasi aditif baru:
  1. `2026_11_13_000001_create_wms_unit_costs_and_task_events_tables.php`
  2. `2026_11_13_000002_create_wms_reservations_utilization_and_alter_bin_stocks.php`
- Seluruh kolom referensi UUID (`warehouse_id`, `zone_id`, `bin_id`, `task_id`) menggunakan `string(..., 64)` agar kompatibel penuh dengan model `HasUuids` di SQLite in-memory (pengujian) maupun PostgreSQL/MySQL (produksi).

---

## 2. Daftar Tabel Bawaan (`wms_*`)

| No | Nama Tabel | Primary Key | Peran Utama |
|---|---|---|---|
| 1 | `wms_warehouses` | `uuid` | Master pusat distribusi / gudang (`code`, `name`, `kind`, `city`, `is_active`) |
| 2 | `wms_zones` | `uuid` | Zona fungsional per gudang (`putaway`, `pick`, `storage`, `bulk`, `cold`, `quarantine`) |
| 3 | `wms_racks` | `uuid` | Struktur rak dan nomor lorong (*aisle*) per zona |
| 4 | `wms_bins` | `uuid` | Lokasi bin terkecil dengan `code` dan `capacity` |
| 5 | `wms_bin_stocks` | `bigint` | Saldo stok per `(bin_id, product_id, lot_number)` |
| 6 | `wms_tasks` | `uuid` | Tugas operasional (`putaway`, `pick`, `pack`, `stage`, `replenish`) |
| 7 | `wms_waves` | `uuid` | Header gelombang *wave picking* outbound |
| 8 | `wms_transfers` | `uuid` | Header transfer stok antar-gudang |
| 9 | `wms_transfer_lines` | `bigint` | Rincian baris item transfer antar-gudang |
| 10 | `wms_cycle_counts` | `uuid` | Header sesi *cycle count* |
| 11 | `wms_cycle_count_lines` | `bigint` | Baris hasil penghitungan fisik per bin & SKU |
| 12 | `wms_replenishments` | `bigint` | Permintaan pengisian ulang *pick-face* dari zona *reserve* |
| 13 | `wms_slottings` | `bigint` | Rekomendasi dan status klasifikasi ABC per produk & gudang |
| 14 | `wms_dock_appointments` | `bigint` | Jadwal kedatangan dan keberangkatan truk pada pintu *dock* |
| 15 | `wms_packing_lists` | `bigint` | Daftar pengepakan karton dan label pengiriman |

---

## 3. Tabel & Kolom Aditif Fase 41

### 3.1 `wms_unit_costs`
Menyimpan riwayat harga pokok per produk dan gudang dalam integer minor units IDR.
- `id`: `bigint` PK
- `product_id`: `unsignedBigInteger` (indexed)
- `warehouse_id`: `string(64)` nullable (indexed; `NULL` berarti berlaku global lintas gudang)
- `cost_idr`: `unsignedBigInteger` (nominal integer minor units IDR)
- `method`: `string(16)` (`std`, `avg`, `fifo`)
- `effective_date`: `date` (indexed)
- `created_by_user_id`: `unsignedBigInteger` nullable
- `is_active`: `boolean` (indexed)
- **Unique constraint**: `wms_unit_costs_prod_wh_date_method_uq` pada `(product_id, warehouse_id, effective_date, method)`.

### 3.2 `wms_task_events`
Log transisi *append-only* untuk `WarehouseTaskStateMachine` (D41-1).
- `id`: `bigint` PK
- `task_id`: `string(64)` (indexed)
- `from_status`: `string(32)`
- `to_status`: `string(32)`
- `action`: `string(48)` (`assign`, `start`, `confirm`, `flag_exception`, `retry`, `resolve`, `close`, `cancel`)
- `actor_user_id`: `unsignedBigInteger` nullable (indexed)
- `reason`: `string(255)` nullable
- `payload_json`: `json` nullable
- `created_at`: `timestamp`

### 3.3 Kolom Tambahan pada `wms_bin_stocks`
- `qty_base`: `bigInteger` default `0` (kuantitas dalam integer base unit)
- `lot_number`: `string(64)` nullable (indexed)
- `expiry_date`: `date` nullable (indexed untuk seleksi FEFO)
- `serial_number`: `string(64)` nullable (indexed)
- `stock_status`: `string(24)` default `'available'` (`available`, `quarantine`, `blocked`, `reserved`)
- `quarantine_reason`: `string(255)` nullable
- `quarantined_at`: `timestamp` nullable
- `released_at`: `timestamp` nullable

### 3.4 `wms_stock_reservations` (X41-2)
- `id`: `bigint` PK
- `warehouse_id`: `string(64)` (indexed)
- `product_id`: `unsignedBigInteger` (indexed)
- `bin_id`: `string(64)` nullable (indexed)
- `lot_number`: `string(64)` nullable
- `qty_base`: `unsignedBigInteger`
- `reference_type`: `string(64)` & `reference_id`: `string(64)` (composite index `wms_res_ref_idx`)
- `status`: `string(24)` (`active`, `committed`, `released`, `expired`)
- `expires_at`: `timestamp` (indexed untuk `wms:reservation-sweep`)

### 3.5 `wms_receiving_receipts` (X41-1)
- `id`: `bigint` PK
- `receipt_number`: `string(64)` unique
- `warehouse_id`: `string(64)` (indexed)
- `product_id`: `unsignedBigInteger` (indexed)
- `expected_qty_base`: `unsignedBigInteger`
- `received_qty_base`: `unsignedBigInteger`
- `variance_bps`: `integer`
- `tolerance_decision`: `string(32)` (`within_tolerance`, `over_receipt`, `short_receipt`)
- `lot_number`: `string(64)` nullable
- `expiry_date`: `date` nullable
- `status`: `string(32)`
- `received_by_user_id`: `unsignedBigInteger` nullable

### 3.6 `wms_utilization_snapshots` (X41-4)
- `id`: `bigint` PK
- `warehouse_id`: `string(64)` (indexed)
- `zone_id`: `string(64)` nullable (indexed)
- `snapshot_date`: `date` (indexed)
- `total_bins`: `unsignedInteger`
- `occupied_bins`: `unsignedInteger`
- `utilization_bps`: `unsignedInteger` (`0..10000`)
- `dead_stock_sku_count`: `unsignedInteger`
- `slotting_mismatch_count`: `unsignedInteger`

---

## 4. Invarian Integritas Basis Data
1. Tidak boleh ada baris `wms_bin_stocks` dengan `qty < 0` atau `qty_base < 0`.
2. Jumlah reservasi berstatus `active` yang belum kedaluwarsa tidak boleh melebihi total `qty_base` berstatus `available` pada `(warehouse_id, product_id)`.
3. Total nilai subledger `Σ(qty_base × cost_idr)` per gudang wajib sama dengan `-cached_balance` pada akun `wms:inventory:{kode}:IDR` di `bank_ledger_accounts`.
