# Algoritma Deterministik (I1–I3) & Skor Analitik (X53-1 .. X53-4) — Fase 53

Seluruh algoritma di modul `ControlTower` dirancang 100% deterministik dan bebas operasi *floating-point* pada nilai uang serta kuantitas inventori (`Aturan A3`). Perhitungan pecahan memakai aritmetika integer skala tetap (`scale = 10_000` atau basis points `bps` di mana `10_000 = 100%` dan `permil = 1_000`) serta pembulatan integer standar (`intdiv`).

---

## 1. Algoritma I1: Netting ATP & CTP Multi-Periode (`AtpCtpNetter` & `AtpCtpCalculator`)

### 1.1 Definisi Variabel per Periode $t \in \{1, \dots, T\}$
- $I_0$: stok siap alokasi awal periode 1, diambil dari `available = max(0, on_hand - quarantine - hold)` via `ItemAvailabilityReader`.
- $MPS_t$: penerimaan produksi terjadwal pada periode $t$ (`Manufacturing\Contracts\CapacityAndMaterialProvider::mpsReceipts()`).
- $C_t$: komitmen pesanan distribusi terbuka pada periode $t$ (`Distribution\Contracts\OpenDemandProvider::commitmentsByPeriod()`).
- $CapCum_t$: batas kapasitas produksi kumulatif sampai periode $t$.
- $MatCum_t$: batas ketersediaan material BOM kumulatif sampai periode $t$.

### 1.2 Persamaan Netting Deterministik
Untuk setiap periode $t = 1 \dots T$:
1. **Pasokan Kumulatif Bersih Sebelum Permintaan Baru:**
   $$SupplyCum_t = I_0 + \sum_{k=1}^{t} MPS_k$$
2. **Komitmen Terkunci Kumulatif:**
   $$CommitCum_t = \sum_{k=1}^{t} C_k$$
3. **Available-to-Promise (ATP) Kumulatif pada Periode $t$:**
   $$ATP_t = \max(0, \; SupplyCum_t - CommitCum_t)$$
   Dengan pengamanan mundur *Look-Ahead Stealing Protection* agar komitmen periode masa depan ($k > t$) tidak membuat periode lebih awal over-promised:
   $$ATP_{eff, t} = \min_{k \ge t} ATP_k$$
4. **Capable-to-Promise (CTP) Kumulatif pada Periode $t$:**
   Bila permintaan $Q_{req}$ pada periode minta $t^*$ melampaui $ATP_{eff, t^*}$, kemampuan manufaktur tambahan pada periode $t$ adalah:
   $$CTP_t = \min(CapCum_t, \; MatCum_t)$$
   Total pasokan gabungan kumulatif menjadi $TotalCapable_t = ATP_{eff, t} + CTP_t$.

### 1.3 Penentuan Status Janji & `reason_code`
- Cari periode paling awal $p \in [t^*, T]$ di mana $TotalCapable_p \ge Q_{req}$:
  - Jika $p == t^*$: `status = 'on_time'`, `promised_period = t^*`, `late_periods = 0`, `reason_code = null`.
  - Jika $p > t^*$: `status = 'late'`, `promised_period = p`, `late_periods = p - t^*`, `reason_code = null`.
  - Jika tidak ada $p \le T$ yang memenuhi $Q_{req}$: `status = 'cannot_promise'`, `promised_period = null`, `late_periods = 0`, dan `reason_code` ditetapkan sebagai:
    - `'INSUFFICIENT_CAPACITY'` bila $CapCum_T < Q_{req} - ATP_{eff, T}$ dan kapasitas lebih kecil dari material;
    - `'INSUFFICIENT_MATERIAL'` bila kapasitas cukup namun $MatCum_T$ membatasi;
    - `'HORIZON_EXCEEDED'` jika $t^* > T$ atau baru terpenuhi di luar horizon.

### 1.4 Vektor Verifikasi Numerik I1
Misal $I_0 = 50$, $MPS = [20, 30, 40]$, $C = [30, 10, 20]$, $CapCum = [25, 60, 90]$, $MatCum = [40, 50, 90]$:
- $t=1$: $ATP_1 = 50 + 20 - 30 = 40$; $CTP_1 = \min(25, 40) = 25$; $Total_1 = 65$.
- $t=2$: $ATP_2 = 50 + 50 - 40 = 60$; $CTP_2 = \min(60, 50) = 50$; $Total_2 = 110$.
- $t=3$: $ATP_3 = 50 + 90 - 60 = 80$; $CTP_3 = \min(90, 90) = 90$; $Total_3 = 170$.
- Kasus Uji 1: $Q_{req} = 60$, $t^* = 1 \implies Total_1 = 65 \ge 60 \implies$ `status = on_time`, `promised_period = 1`.
- Kasus Uji 2: $Q_{req} = 100$, $t^* = 1 \implies Total_1 < 100$, $Total_2 = 110 \ge 100 \implies$ `status = late`, `promised_period = 2`, `late_periods = 1`.
- Kasus Uji 3: $Q_{req} = 200$, $t^* = 1 \implies Total_3 = 170 < 200 \implies$ `status = cannot_promise`, reason terpetakan deterministik.

---

## 2. Algoritma I2: Seleksi Model Peramalan & Aritmetika Rasional (`ForecastModelSelector`)

### 2.1 Representasi Skala Tetap (`SCALE = 10_000`)
Seluruh kandidat dihitung dengan bilangan rasional berskala $S = 10\_000$ agar deterministik pada arsitektur ARM64 maupun x86_64:

1. **Kandidat 1 — Single Exponential Smoothing (`SES`, $\alpha = \frac{3}{10}$):**
   - Inisialisasi: $L_0 = y_0 \cdot S$.
   - Iterasi $t = 1 \dots n-1$:
     $$\hat{y}_t = \text{intdiv}(L_{t-1} + S/2, S)$$
     $$L_t = \text{intdiv}(3 \cdot (y_t \cdot S) + 7 \cdot L_{t-1} + 5, 10)$$
   - Proyeksi satu langkah ke depan: $F_{SES} = \text{intdiv}(L_{n-1} + S/2, S)$.

2. **Kandidat 2 — Holt Linear Trend (`HOLT`, $\alpha = \frac{3}{10}, \; \beta = \frac{1}{10}$):**
   - Inisialisasi: $L_0 = y_0 \cdot S$, $B_0 = (y_1 - y_0) \cdot S$.
   - Iterasi $t = 1 \dots n-1$:
     $$\hat{y}_t = \text{intdiv}(\max(0, L_{t-1} + B_{t-1}) + S/2, S)$$
     $$L_t = \text{intdiv}(3 \cdot (y_t \cdot S) + 7 \cdot (L_{t-1} + B_{t-1}) + 5, 10)$$
     $$B_t = \text{intdiv}(1 \cdot (L_t - L_{t-1}) + 9 \cdot B_{t-1} + 5, 10)$$
   - Proyeksi: $F_{HOLT} = \max(0, \text{intdiv}(L_{n-1} + B_{n-1} + S/2, S))$.

3. **Kandidat 3 — Croston Intermittent Demand (`CROSTON`, $\alpha = \frac{2}{10}$):**
   - Dirancang khusus untuk SKU dengan banyak periode bernilai nol (*intermittent / lumpy*).
   - Memisahkan ukuran permintaan non-nol $z_k$ dan interval antar-permintaan $p_k \ge 1$:
     - Saat $y_t > 0$:
       $$Z_{baru} = \text{intdiv}(2 \cdot (y_t \cdot S) + 8 \cdot Z_{lama} + 5, 10)$$
       $$P_{baru} = \text{intdiv}(2 \cdot (q \cdot S) + 8 \cdot P_{lama} + 5, 10)$$
       Reset pencacah interval $q = 1$.
     - Saat $y_t == 0$: $q \leftarrow q + 1$; $Z, P$ dipertahankan.
   - Prediksi laju permintaan rata-rata per periode:
     $$\hat{y}_t = \text{intdiv}(Z \cdot S + P/2, \max(1, P))$$

### 2.2 Backtest Walk-Forward 6 Titik & Pemilihan Pemenang
- Menggunakan 6 titik evaluasi terakhir (atau $\min(6, n - 1)$ jika $n < 7$):
  $$MAE_{scaled} = \text{intdiv}\left(\sum_{t \in E} |y_t - \hat{y}_t| \cdot S, \; |E|\right)$$
  $$MAPE_{bps} = \text{intdiv}\left(\sum_{t \in E, y_t > 0} \text{intdiv}(|y_t - \hat{y}_t| \cdot 10\_000, y_t), \; |E_{>0}|\right)$$
- **Urutan prioritas pemenang (`tie-breaker`):**
  1. Kandidat dengan `mae_scaled` terkecil menang.
  2. Jika nilai `mae_scaled` sama persis, pilih model terawal dalam urutan prioritas tetap: `SES` $\prec$ `HOLT` $\prec$ `CROSTON`.

---

## 3. Algoritma I3: Dynamic Multi-Echelon Safety Stock (`MultiEchelonSafetyStock`)

### 3.1 Klasifikasi Matriks ABC-XYZ & Lookup Faktor-Z
- **Dimensi ABC** (berdasarkan kontribusi nilai pemakaian tahunan kumulatif SKU):
  - Kelas `A`: pangsa kumulatif $\le 80\%$ (`8000 bps`) atau pangsa tunggal $\ge 20\%$
  - Kelas `B`: pangsa kumulatif $80.01\% \dots 95\%$ (`9500 bps`)
  - Kelas `C`: sisanya ($> 95\%$)
- **Dimensi XYZ** (berdasarkan *Coefficient of Variation* permintaan $CV = \frac{\sigma_D}{\mu_D} \times 10\_000$):
  - Kelas `X`: $CV < 2500\text{ bps}$ ($< 0.25$)
  - Kelas `Y`: $2500 \le CV \le 6500\text{ bps}$
  - Kelas `Z`: $CV > 6500\text{ bps}$ ($> 0.65$)
- **Tabel Faktor-Z Resmi (skala $10\_000$):**

| Kelas | X (`CV < 0.25`) | Y (`0.25 <= CV <= 0.65`) | Z (`CV > 0.65`) |
| :---: | :---: | :---: | :---: |
| **A** | `23300` ($Z=2.33$, ~99.0%) | `20500` ($Z=2.05$, ~98.0%) | `19600` ($Z=1.96$, ~97.5%) |
| **B** | `16500` ($Z=1.65$, ~95.0%) | `16500` ($Z=1.65$, ~95.0%) | `12800` ($Z=1.28$, ~90.0%) |
| **C** | `12800` ($Z=1.28$, ~90.0%) | `10400` ($Z=1.04$, ~85.0%) | `8400` ($Z=0.84$, ~80.0%) |

### 3.2 Variabilitas Terpadu & Reorder Point Integer
Menggunakan statistik *lead time* riil pemasok (`mean_days` = $L$, `sigma_days` = $\sigma_L$) dari `SupplierLeadTimeStatistics`:
$$\sigma_{combined}^2 = L \cdot \sigma_D^2 + \mu_D^2 \cdot \sigma_L^2$$
$$\sigma_{combined, scaled} = \text{isqrt}\left(L \cdot Var(D_{scaled}) + (\mu_D \cdot \sigma_L \cdot S)^2\right)$$
$$SafetyStock = \max\left(1, \; \text{intdiv}(Z_{bps} \cdot \sigma_{combined, scaled} + 10^8 - 1, \; 10^8)\right)$$
$$ROP = \mu_D \cdot L + SafetyStock$$

---

## 4. Model Skor Analitik Operasional (X53-1 .. X53-4)

### 4.1 `OrderRiskScorer` (`X53-1`) — Skor Komposit `0..1000`
Empat komponen risiko dihitung integer berbobot penuh tanpa float:
1. **Shortfall Rasio (maks. `400` poin):** $\min(400, \text{intdiv}(shortfall \cdot 400, \max(1, demand)))$
2. **Risiko Pemasok (`SupplierLeadTimeStatistics`, maks. `250` poin):** penalti dari $(1000 - on\_time\_permil)$ dan rasio $\sigma_L / \max(1, L)$.
3. **Kedaluwarsa / Umur Lot Tertua (`ItemAvailabilityReader`, maks. `200` poin):** proporsional terhadap kedekatan umur lot tertua ke ambang kedaluwarsa (mis. `>= 180` hari -> skor penuh `200`).
4. **Keterlambatan In-Transit (`InTransitShipmentProvider`, maks. `150` poin):** jumlah shipment tertunda $> 72$ jam menuju gudang tujuan.
- Tier: `CRITICAL` ($\ge 750$), `HIGH` ($500..749$), `MEDIUM` ($250..499$), `LOW` ($< 250$).

### 4.2 `SupplierScorecardService` (`X53-2`) — Skor Komposit `0..1000`
- Bobot resmi: **OTD On-Time Delivery (`45%`)** + **Konsistensi Lead Time `mean vs sigma` (`30%`)** + **Keandalan Volume Sampel Penerimaan (`25%`)**.
- Tier pemasok: `STRATEGIC` ($\ge 850$), `PREFERRED` ($700..849$), `WATCHLIST` ($500..699$), `AT_RISK` ($< 500$).

### 4.3 `InventoryHealthAnalyzer` (`X53-3`) — Klasifikasi Kesehatan & Eksposur Modal
- Bucket umur stok: `0-30`, `31-90`, `91-180`, `>180` hari berdasarkan `oldest_lot_age_days`.
- Status kondisi:
  - `DEAD_STOCK`: umur lot $> 180$ hari, atau `on_hand > 0` dengan `daily_demand == 0`.
  - `SLOW_MOVING`: $DOS > 90$ hari atau bucket `91-180`.
  - `EXCESS`: `on_hand > 3 * safety_stock` saat bukan `DEAD_STOCK`.
  - `SHORTAGE_RISK`: `available < safety_stock`.
  - `HEALTHY`: memenuhi kondisi operasional normal.
- Seluruh nilai `tied_capital_idr` dan `obsolescence_risk_idr` dihitung dalam `int64` satuan IDR.

### 4.4 `ForecastOverrideService` (`X53-4`)
- Memvalidasi perubahan manual kuantitas permintaan oleh planner (`new_qty >= 0`, `reason >= 5` karakter), menghitung `deviation_bps`, mengarahkan deviasi besar melalui `ApprovalEngineInterface`, serta menyediakan pemulihan deterministik (`revertOverride`) ke nilai proyeksi statistik awal.
