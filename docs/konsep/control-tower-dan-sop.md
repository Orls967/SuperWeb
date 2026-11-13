# Konsep Arsitektur Supply Chain Control Tower & S&OP (Fase 53)

## 1. Visi dan Peran dalam Rantai Pasok Terpadu

Supply Chain Control Tower (`modules/ControlTower/`, prefiks `sct_`) bertindak sebagai menara pengawas terpadu yang menghubungkan simpul hulu hingga hilir:
1. **Pemasok Tier-1 & Tier-2 (`SUPPLIER`)**: memantau ketepatan jadwal pengiriman, variabilitas *lead time*, dan risiko keterlambatan pasokan bahan baku.
2. **Pelabuhan & Kepabeanan (`PORT`)**: melacak kargo impor dalam perjalanan (*in-transit*) serta mendeteksi kemacetan pelabuhan atau penundaan bea cukai.
3. **Pabrik Manufaktur (`PLANT`)**: mengagregasi kapasitas kerja mesin bebas untuk kebutuhan perhitungan *Capable-to-Promise* (CTP) dan penyeimbangan pasokan S&OP.
4. **Gudang Sentral / Pusat Distribusi (`DC`)**: membaca stok tersedia riil dari modul `Wms` melalui kontrak `StockAvailabilityReader` tanpa mengakses tabel `wms_*` secara langsung.
5. **Distributor & Toko/Outlet (`OUTLET`)**: menghitung janji pengiriman pelanggan (*Available-to-Promise* / ATP) serta memantau rasio *On-Time In-Full* (OTIF).

## 2. Prinsip Desain Non-Ledger (D98)

Sesuai Keputusan Arsitektur `ADR-053` dan aturan D98, `ControlTower` tidak memiliki kejadian uang langsung (`RAB posting = 0`). Seluruh angka moneter pada `sct_sop_cycles` (proyeksi margin kotor, dampak arus kas modal kerja) dan `sct_twin_scenarios` (estimasi delta margin) bersifat analitis/perencanaan dalam satuan minor IDR (`int64` / `bigInteger`), tanpa pernah menggunakan `float`.

## 3. Visibilitas Inventori Multi-Eselon (53.1)

Setiap simpul menyimpan empat komponen kuantitas deterministik dalam `sct_echelon_stocks`:
- `on_hand_qty`: stok fisik yang siap dialokasikan di simpul terkait.
- `in_transit_qty`: stok dalam perjalanan antar simpul yang telah dikirim.
- `reserved_qty`: stok yang sudah dikunci untuk janji pesanan ATP terkonfirmasi.
- `safety_stock_qty`: ambang batas stok pengaman dinamis hasil optimasi ABC/XYZ.

Invarian wajib:
- `reserved_qty <= on_hand_qty + in_transit_qty` pada setiap baris.
- Seluruh nilai kuantitas tidak boleh negatif (`>= 0`).

## 4. Mesin Peramalan Permintaan Multi-Model (53.2)

`DemandForecastEngine` menjalankan empat metode proyeksi kuantitatif deterministik dalam aritmetika integer fixed-point (basis points `10_000 = 100%`):
- **Weighted Moving Average (`MOVING_AVG`)**: memberikan bobot progresif pada tiga periode terakhir (`1:2:3`).
- **Exponential Smoothing (`EXP_SMOOTHING`)**: pemulusan eksponensial tunggal dengan parameter `alpha_bps`.
- **Holt-Winters Additive (`HOLT_WINTERS`)**: memodelkan level, tren, dan musiman secara aditif dengan parameter `alpha_bps`, `beta_bps`, dan `gamma_bps`.
- **Linear Regression (`LINEAR_REGRESSION`)**: regresi kuadrat terkecil deterministik pada deret waktu historis.

`ForecastModelSelector` mengevaluasi keempat model dan memilih model dengan `mape_bps` terendah, kemudian menyimpan jejak evaluasi (`mape_bps`, `mad_units`, `tracking_signal_bps`) di dalam `ForecastAccuracyCertificate`. Tim penjualan dapat melakukan override kolaboratif melalui `OverrideDemandForecastAction` dengan syarat alasan override wajib diisi dan dicatat di `sct_forecast_overrides`.

## 5. Siklus Bulanan S&OP Kolaboratif (53.3)

Siklus S&OP dikelola oleh `SopCycleStateMachine` dengan tujuh status berurutan:
1. `draft` — inisiasi rencana awal beserta skenario (`conservative`, `moderate`, `aggressive`).
2. `demand_review` — tinjauan konsensus permintaan oleh tim penjualan & pemasaran.
3. `supply_review` — verifikasi kapasitas produksi pabrik dan ketersediaan bahan baku.
4. `pre_sop_balancing` — penyeimbangan finansial antara margin kotor, biaya simpan, dan dampak modal kerja oleh `SopFinancialBalancer`.
5. `executive_signoff` — persetujuan eksekutif dengan pencatatan `signed_off_by_user_id` dan stempel waktu dari `ClockInterface`.
6. `published` — rencana S&OP resmi dipublikasikan sebagai acuan eksekusi bulanan.
7. `archived` — penutupan siklus setelah periode berjalan selesai.

Setiap perpindahan status wajib melalui `TransitionSopCycleAction` dan tercatat permanen di `sct_sop_events`.

## 6. Mesin Janji Pesanan ATP & CTP (53.4)

Ketika pesanan pelanggan masuk, `EvaluateOrderPromiseAction` dan `AtpCtpCalculator` mengevaluasi:
1. **ATP (Available-to-Promise)**: menggunakan stok bebas (`on_hand + in_transit - reserved`).
2. **CTP (Capable-to-Promise)**: jika ATP tidak mencukupi, sistem memeriksa ketersediaan komponen BOM (`bom_component_available_qty`) dan kapasitas bebas pabrik (`plant_free_capacity_units`).
3. **Status Janji**:
   - `confirmed`: `atp_confirmed_qty + ctp_manufacturing_qty == requested_qty`
   - `partial`: `0 < atp_confirmed_qty + ctp_manufacturing_qty < requested_qty`
   - `rejected`: `atp_confirmed_qty + ctp_manufacturing_qty == 0`

## 7. Klasifikasi ABC/XYZ & Dynamic Safety Stock (53.5)

`SafetyStockAbcXyzOptimizer` mengklasifikasikan setiap SKU ke dalam matriks 3×3:
- **Dimensi ABC** (nilai pemakaian tahunan): `A` (`>= 20%`), `B` (`>= 5%`), `C` (`< 5%`).
- **Dimensi XYZ** (koefisien variasi permintaan): `X` (`CV <= 15%`), `Y` (`CV <= 40%`), `Z` (`CV > 40%`).
- **Dynamic Safety Stock**: dihitung menggunakan faktor Z (`90% = 1.28`, `95% = 1.65`, `99% = 2.33` dalam skala bps) dan akar kuadrat integer `isqrt`.
- **Deteksi Slow-Moving & Dead Stock**: `SlowMovingDeadStockDetector` menandai SKU yang tidak bergerak (`>= 60` hari untuk *slow-moving*, `>= 180` hari untuk *dead stock*).

## 8. Analisis Dampak Disrupsi & Digital Twin What-If (53.6 & 53.8)

- **Blast Radius Impact Analysis**: `BlastRadiusImpactAnalyzer` menelusuri jalur eselon setiap pesanan terbuka (`echelon_path`) untuk mengidentifikasi daftar pesanan terdampak dan total pendapatan berisiko (`revenue_at_risk_idr`) saat sebuah simpul (mis. `PORT-TANJUNG-PRIOK`) mengalami gangguan.
- **What-If Digital Twin**: `WhatIfTwinSimulator` dan `ControlTowerTwinTickHandler` (mengimplementasikan `SimTickHandlerInterface` pada sandbox P3) mensimulasikan skenario kejut (`PORT_CLOSURE`, `MATERIAL_INFLATION`, `DEMAND_SURGE`, `PLANT_EXPANSION`) tanpa mengubah data operasional riil.

