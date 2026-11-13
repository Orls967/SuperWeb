# Algoritma I1–I3 — Supply Chain Control Tower & S&OP (Fase 53)

## 1. I1: Demand Forecast Engine (`DemandForecastEngine`)
Seluruh proyeksi permintaan dieksekusi menggunakan aritmetika integer (`intdiv`) dengan parameter smoothing dalam basis points (`bps`, `10_000 = 100%`) untuk menjamin determinisme lintas arsitektur CPU dan kepatuhan zero-float.

### Model yang Didukung
1. **Weighted Moving Average (`MOVING_AVG`)**: bobot `1:2:3` pada 3 periode terakhir.
2. **Exponential Smoothing (`EXP_SMOOTHING`)**: `level_t = (alpha * y_t + (10000 - alpha) * level_{t-1}) / 10000`.
3. **Holt-Winters Additive (`HOLT_WINTERS`)**: mendekomposisi `level`, `trend`, dan `season` dengan parameter `alpha_bps`, `beta_bps`, `gamma_bps`.
4. **Linear Regression (`LINEAR_REGRESSION`)**: regresi kuadrat terkecil (`OLS`) pada indeks waktu `x = 1..n` untuk tren linear deterministik.

### Metrik Akurasi & Sertifikat
- `mape_bps`: rata-rata persentase galat absolut dalam basis points.
- `mad_units`: rata-rata deviasi absolut dalam unit integer.
- `tracking_signal_bps`: rasio jumlah galat bertanda terhadap MAD (`<= |40000| bps` menandakan model bebas bias).
- `ForecastAccuracyCertificate` memuat hash SHA-256 kanonik seluruh masukan dan keluaran untuk diverifikasi oleh `ForecastAccuracyCertificateVerifier`.

## 2. I2: ATP & CTP Order Promise Calculator (`AtpCtpCalculator`)
- **ATP (`Available-to-Promise`)**: `max(0, on_hand + scheduled_receipts - already_committed)`.
- **CTP (`Capable-to-Promise`)**: `max(0, min(bom_component_available, plant_free_capacity))` untuk memenuhi sisa permintaan yang tidak tertutupi ATP.
- **Invarian**: `atp_confirmed_qty <= available_atp_qty` dan `atp_confirmed_qty + ctp_manufacturing_qty <= requested_qty`.
- `OrderPromiseCertificate` menjamin tidak ada over-promise terhadap pasokan fisik dan kapasitas pabrik.

## 3. I3: ABC/XYZ & Dynamic Safety Stock (`SafetyStockAbcXyzOptimizer`)
- **Klasifikasi ABC**: pangsa nilai pemakaian tahunan (`>= 2000 bps` = A, `>= 500 bps` = B, sisanya C).
- **Klasifikasi XYZ**: koefisien variasi permintaan (`cv_bps <= 1500` = X, `<= 4000` = Y, sisanya Z).
- **Dynamic Safety Stock**: menggunakan faktor Z dalam basis points (`90% -> 12800`, `95% -> 16500`, `99% -> 23300`) dan akar kuadrat integer (`isqrt`) untuk kombinasi variabilitas permintaan dan variabilitas lead time pemasok.

