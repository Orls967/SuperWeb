# Panduan Operasional Control Tower & S&OP (Fase 53)

## 1. Navigasi Antarmuka Web

Seluruh antarmuka operasional Control Tower berada di bawah prefiks `/control-tower` dan dilindungi oleh `ControlTowerOperationPolicy` serta matriks peran `tests/Architecture/route-roles.php`:

| Rute | Nama Rute | Peran Diizinkan | Fungsi Utama |
| :--- | :--- | :--- | :--- |
| `GET /control-tower` | `control_tower.index` | `admin`, `logistics_admin`, `planner`, `auditor` | Dasbor ringkasan multi-eselon & alert disrupsi |
| `GET /control-tower/sop` | `control_tower.sop.index` | `admin`, `logistics_admin`, `planner`, `auditor` | Daftar siklus S&OP dan status tahap |
| `POST /control_tower/sop` | `control_tower.sop.store` | `admin`, `logistics_admin`, `planner` | Inisiasi siklus S&OP baru |
| `POST /control_tower/sop/{cycle}/transition` | `control_tower.sop.transition` | `admin`, `logistics_admin`, `planner` | Transisi tahap S&OP 7-status |
| `GET /control_tower/forecasting` | `control_tower.forecasting.index` | `admin`, `logistics_admin`, `planner`, `auditor` | Daftar proyeksi permintaan multi-model |
| `POST /control_tower/forecasting/override` | `control_tower.forecasting.override` | `admin`, `logistics_admin`, `planner` | Override forecast dengan alasan audit wajib |
| `GET /control_tower/twin` | `control_tower.twin.index` | `admin`, `logistics_admin`, `planner`, `auditor` | Daftar hasil simulasi What-If Digital Twin |
| `POST /control_tower/twin/simulate` | `control_tower.twin.simulate` | `admin`, `logistics_admin`, `planner` | Eksekusi simulasi skenario kejut baru |
| `GET /control_tower/kpi` | `control_tower.kpi.index` | `admin`, `logistics_admin`, `planner`, `auditor` | Snapshot KPI eksekutif (OTIF, C2C, DOS) |
| `GET /control_tower/safety-stock` | `control_tower.safety_stock.index` | `admin`, `logistics_admin`, `planner`, `auditor` | Matriks ABC/XYZ & kebijakan safety stock |
| `GET /control_tower/export/echelon.csv` | `control_tower.export.echelon` | `admin`, `logistics_admin`, `planner`, `auditor` | Ekspor CSV posisi stok multi-eselon |

## 2. Daftar Perintah Artisan CLI

1. **`php artisan tower:audit`**:
   - Menjalankan audit menyeluruh atas konsistensi stok eselon, invarian ATP/CTP, dan validitas status S&OP.
   - Mengembalikan kode keluar `0` bila bersih dan `1` bila ditemukan pelanggaran.
2. **`php artisan tower:safety-stock-recalc`**:
   - Menghitung ulang klasifikasi ABC/XYZ dan *Dynamic Safety Stock* untuk seluruh SKU yang terdaftar di `sct_echelon_stocks`.
3. **`php artisan tower:twin-simulate {--shock=PORT_CLOSURE} {--magnitude=2000} {--days=14}`**:
   - Menjalankan simulasi What-If Digital Twin melalui CLI dan menyimpan hasilnya ke `sct_twin_scenarios`.
4. **`php artisan tower:kpi-snapshot {--otif-orders=96} {--total-orders=100}`**:
   - Merekam snapshot KPI harian (`otif_bps`, `cash_to_cash_days`, `inventory_dos_days`, `fulfillment_lead_time_hours`, `logistics_cost_ratio_bps`).

## 3. Alur Kerja Standar Tim Planner & Eksekutif

### A. Siklus Bulanan S&OP
1. Planner memulai siklus baru (`draft`) untuk periode `YYYY-MM` dengan memilih tipe skenario (`conservative`, `moderate`, atau `aggressive`).
2. Tim Penjualan meninjau forecast sistem dan melakukan override jika ada kampanye/promo khusus (`demand_review`).
3. Tim Produksi & Pengadaan mengevaluasi kapasitas lini kerja (`ControlTowerCapacityPlanningService`) dan risiko keterlambatan pemasok (`ControlTowerSupplierRiskService`) pada tahap `supply_review`.
4. Tim Keuangan meninjau keseimbangan margin kotor dan modal kerja (`pre_sop_balancing`).
5. Eksekutif memberikan persetujuan akhir (`executive_signoff` -> `published`).

### B. Penanganan Gangguan Rantai Pasok
1. Saat terjadi keterlambatan kapal atau gangguan pabrik, jalankan `DisruptionResponsePlaybookService::raiseDisruptionWithBlastRadius()`.
2. Sistem otomatis memetakan pesanan pelanggan yang terdampak dan menghitung nilai pendapatan berisiko.
3. Gunakan `EchelonRebalancingPlanner` untuk merencanakan transfer stok antar simpul guna meminimalkan dampak keterlambatan.

