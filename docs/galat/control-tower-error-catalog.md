# Katalog Galat & Penanganan — Control Tower & S&OP (Fase 53)

| Kode Galat | Sumber Kelas | Penyebab | Resolusi Operasional |
| :--- | :--- | :--- | :--- |
| `INVALID_FORECAST_HISTORY` | `DemandForecastEngine` | Panjang deret historis permintaan `< 3` periode atau `itemCode` kosong. | Pastikan minimal 3 periode data penjualan historis tersedia sebelum menjalankan proyeksi. |
| `NEGATIVE_DEMAND_HISTORY` | `DemandForecastEngine` | Terdapat nilai kuantitas permintaan negatif dalam array historis. | Bersihkan data retur/koreksi sehingga deret permintaan bernilai `>= 0`. |
| `INVALID_PROMISE_REQUEST` | `AtpCtpCalculator` | `itemCode` kosong atau `requestedQty <= 0`. | Validasi masukan pesanan pelanggan sebelum memanggil evaluasi ATP/CTP. |
| `INVALID_SAFETY_STOCK_INPUT` | `SafetyStockAbcXyzOptimizer` | Deret permintaan kosong atau `avgLeadTimeDays < 1`. | Tetapkan lead time pemasok minimal 1 hari dan sediakan minimal 1 periode histori. |
| `INVALID_TWIN_INPUT` | `WhatIfTwinSimulator` | `durationDays < 1` atau `baselineOtifBps` di luar rentang `0..10000`. | Gunakan basis points `0..10000` (10000 = 100%) dan durasi positif. |
| `INVALID_SOP_TRANSITION` | `SopCycleStateMachine` | Percobaan transisi status S&OP melompati tahap wajib atau dari status terminal `archived`. | Ikuti urutan `draft -> demand_review -> supply_review -> pre_sop_balancing -> executive_signoff -> published -> archived`. |
| `INVALID_PERIOD` | `StartSopCycleAction` | Format periode tidak sesuai pola `YYYY-MM`. | Kirim periode dalam format `YYYY-MM`, misalnya `2026-11`. |
| `NEGATIVE_PLAN_QTY` | `StartSopCycleAction` | Nilai `demand_plan_qty` atau `supply_plan_qty` bernilai negatif. | Pastikan kuantitas rencana bernilai `>= 0`. |
| `INVALID_SCENARIO_TYPE` | `StartSopCycleAction` | `scenario_type` bukan `conservative`, `moderate`, atau `aggressive`. | Pilih salah satu dari tiga tipe skenario resmi. |
| `NEGATIVE_FORECAST_QTY` | `OverrideDemandForecastAction` | Kuantitas override bernilai negatif. | Masukkan kuantitas non-negatif (`>= 0`). |
| `OVERRIDE_REASON_REQUIRED` | `OverrideDemandForecastAction` | Alasan override kosong atau hanya spasi. | Wajib isi alasan bisnis yang jelas untuk jejak audit. |

## Penanganan Kegagalan Verifikasi Sertifikat

Jika `ForecastAccuracyCertificateVerifier::verify()`, `OrderPromiseCertificateVerifier::verify()`, atau `SafetyStockPolicyCertificateVerifier::verify()` mengembalikan `false`:
1. Periksa apakah ada manipulasi data antara perhitungan algoritma dan penyimpanan model.
2. Hitung ulang proyeksi / janji pesanan / kebijakan safety stock dari sumber data asli.
3. Jalankan `php artisan tower:audit` untuk memastikan integritas keseluruhan modul `ControlTower`.

