# Panduan Antarmuka Pengguna (UI), Handheld 375px, & Ekspor CSV WMS

## 1. Daftar Halaman Blade (`modules/Wms/resources/views/wms/`)

Modul WMS menyediakan 12 halaman antarmuka berbasis `<x-app-layout>` dan komponen desain sistem `<x-ds.*>` yang lolos pemeriksaan `ui:lint` tanpa CSS inline atau kelas tidak standar:

| Berkas View | Rute | Sasaran Pengguna | Ringkasan Fitur |
|---|---|---|---|
| `index.blade.php` | `/wms` | Semua role WMS | Ringkasan gudang, zona, rak, bin, dan navigasi utama |
| `operations_dashboard.blade.php` | `/wms` (dashboard) | Admin & Operator | KPI kapasitas, okupansi rata-rata, throughput harian |
| `directed_putaway.blade.php` | `/wms` (putaway) | Operator | Formulir eksekusi directed putaway berbasis H1 |
| `wave_picking.blade.php` | `/wms` (wave) | Operator | Pembentukan dan pelepasan wave picking berbasis H2 |
| `cycle_count.blade.php` | `/wms` (cycle count) | Auditor & Operator | Jadwal cycle count H3 dan input hasil hitung fisik |
| `cross_dock.blade.php` | `/wms` (transfer) | Logistics & Operator | Transfer antar-gudang & cross-docking |
| `billing_3pl.blade.php` | `/wms` (3PL) | Admin | Kalkulator & tagihan jasa penyimpanan 3PL |
| `tasks.blade.php` | `/wms/tasks` | Operator Handheld | Kartu tugas responsif **375px** untuk pemindai barcode |
| `valuation.blade.php` | `/wms/valuation` | Admin & Auditor | Rekonsiliasi subledger vs buku besar per gudang + ekspor CSV |
| `receiving.blade.php` | `/wms/receiving` | Operator & Procurement | Daftar penerimaan GRN & status toleransi X41-1 |
| `quarantine.blade.php` | `/wms/quarantine` | Auditor & QA | Daftar stok berstatus `quarantine` / `blocked` beserta alasan |
| `utilization.blade.php` | `/wms/utilization` | Admin & Logistics | Snapshot utilisasi bin (`bps`) dan jumlah *dead stock* |

---

## 2. Desain Responsif Handheld 375px (`/wms/tasks`)
Halaman `/wms/tasks` dirancang khusus untuk perangkat genggam gudang (*RF handheld / smartphone 375px*):
- Layout kontainer menggunakan `px-3 sm:px-6` dan grid `grid-cols-1 sm:grid-cols-2 gap-3` sehingga kartu tugas menumpuk rapi secara vertikal tanpa *horizontal overflow* pada lebar `375px`.
- Setiap kartu menampilkan nomor tugas, jenis aksi (`PUTAWAY`, `PICK`, `PACK`, `STAGE`, `REPLENISH`), lencana status (`<x-ds.badge>`), ID produk, dan kuantitas target.
- Validasi pemindaian dilakukan melalui `WmsHandheldOperationService::confirmScanAndComplete()` agar kesalahan pemindaian bin/SKU langsung ditolak sebelum mengubah stok.

---

## 3. Skema Ekspor CSV

### 3.1 `GET /wms/export/valuation.csv`
Header kolom:
```text
warehouse_id,warehouse_code,account_code,subledger_value_idr,ledger_balance_idr,discrepancy_idr
```
Digunakan oleh tim akuntansi saat penutupan buku bulanan untuk mencocokkan saldo per pusat distribusi terhadap `bank_ledger_accounts`.

### 3.2 `GET /wms/export/bin-stocks.csv`
Header kolom:
```text
id,bin_id,product_id,qty_base,lot_number,expiry_date,stock_status
```
Digunakan oleh auditor stok untuk inspeksi lot kedaluwarsa, status karantina, dan verifikasi fisik di rak.

---

## 4. Validasi FormRequest & Kebijakan Otorisasi
Setiap aksi `POST` dilindungi oleh `FormRequest` khusus dan `WarehouseOperationPolicy`:
- `StoreDirectedPutawayRequest`: memvalidasi `abc_class` (`in:A,B,C`), `qty_base >= 1`, `unit_cost_idr >= 0`.
- `StoreWavePickingRequest`: memvalidasi `lines` minimal 1 item dengan `qty_base >= 1`.
- `StoreInboundReceivingRequest`: memvalidasi `expected_qty_base >= 1`, `received_qty_base >= 0`, dan flag `approve_over_receipt`.
- `RevalueInventoryCostRequest`: membatasi akses hanya untuk `role === 'admin'` dan metode `in:std,avg,fifo`.
