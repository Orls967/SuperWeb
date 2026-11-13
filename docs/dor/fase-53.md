# Definition of Ready (DoR) — Fase 53: Supply Chain Control Tower & S&OP

- **Fase:** 53 (`modules/ControlTower/`, prefiks tabel `sct_`)
- **Gelombang / Paket:** W8-P6.2
- **Tanggal:** 2026-11-13
- **Status Target:** `🔵 DIIMPLEMENTASIKAN` (non-PU)

## 1. Justifikasi RAB Posting Ledger = 0 (Aturan D98)

**RAB Posting Ledger = 0.**
**Alasan tertulis (D98):**
Modul `ControlTower` (`sct_`) adalah lapisan orkestrasi perencanaan rantai pasok (multi-echelon visibility, demand forecasting, siklus S&OP 4-tahap, janji pesanan ATP/CTP, deteksi bottleneck/disrupsi, KPI eksekutif OTIF/DOS/C2C, dan simulasi digital twin what-if). Seluruh entitas pada Fase 53 merupakan rencana kuantitatif, reservasi janji pengiriman non-moneter, atau skenario sandbox P3 yang **tidak menghasilkan kejadian uang (money event)** secara langsung. Kejadian akuntansi/buku besar riil tetap dieksekusi di modul hilir pemilik transaksi (`Purchasing`, `Sales`, `Wms`, `Manufacturing`, `Banking`). Oleh karena itu, modul `ControlTower` tidak mendaftarkan `TransactionTypeRegistry` maupun mem-posting jurnal ledger.

## 2. Daftar Keputusan Terkunci

1. **Algoritma Tersertifikasi (I1–I3)**:
   - **I1 (`DemandForecastEngine` + `ForecastAccuracyCertificate`)**: Moving Average, Exponential Smoothing, Holt-Winters Additive/Multiplicative, dan Linear Regression (seluruhnya berbasis integer fixed-point / basis points tanpa floating point pada nilai uang atau kuantitas stok).
   - **I2 (`AtpCtpCalculator` + `OrderPromiseCertificate`)**: Perhitungan Available-to-Promise (ATP) per bucket waktu dan Capable-to-Promise (CTP) multi-level (BOM & kapasitas produksi) dengan bukti invarian alokasi `<= stok + PO terkonfirmasi`.
   - **I3 (`SafetyStockAbcXyzOptimizer` + `SafetyStockPolicyCertificate`)**: Klasifikasi ABC/XYZ dan Dynamic Safety Stock berbasis Service Level (90%/95%/99%) dalam integer basis points.
2. **State Machine S&OP 7-Status (`SopCycleStateMachine`)**:
   - `draft` -> `demand_review` -> `supply_review` -> `pre_sop_balancing` -> `executive_signoff` -> `published` -> `archived` (dengan jalur `rejected` / `reopened` terkontrol & audit trail `sct_sop_events`).
3. **Kontrak Lintas Modul (A1/A2/P4)**:
   - Membaca stok gudang riil via `Modules\Wms\Contracts\StockAvailabilityReader` dan `WarehouseValuationReader` (bukan query langsung ke `wms_*`).
   - Menyediakan `ControlTowerTwinTickHandler` yang mengimplementasikan `Modules\Core\Contracts\SimTickHandlerInterface` pada sandbox P3 tanpa memutasi tabel riil saat simulasi what-if.
4. **Fitur X53-1..X53-4**:
   - **X53-1**: Forecast override kolaboratif dengan audit trail alasan wajib & tracking MAPE/MAD/Tracking Signal.
   - **X53-2**: Blast Radius Impact Analysis untuk disruption alerts (pemetaan node terdampak -> order terancam).
   - **X53-3**: Executive Supply Chain KPI Aggregator (OTIF bps, Cash-to-Cash days, Inventory DOS, Fulfillment Lead Time).
   - **X53-4**: `tower:audit` + `ControlTowerAuditCorruptionFixture` (sabotase invarian ATP > stok+PO & duplikasi janji agar `tower:audit` gagal deterministik saat data rusak).
5. **Integritas & Larangan**:
   - Zero `float` pada kuantitas/uang; zero `now()`/`Carbon::now()` di Domain/Application (`ClockInterface`); `declare(strict_types=1);` pada semua file PHP; semua FK `*_id` cocok tipe kolomnya (MySQL 8.4 compatible); Blade view memakai komponen `x-ds.*` dengan `php artisan ui:lint` = 0.

