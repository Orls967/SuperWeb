# Dokumentasi Algoritma: `TransferPricingMarkupCalculator` (D4)

**Namespace**: `Modules\Intercompany\Domain\Algorithms\TransferPricingMarkupCalculator`  
**Fase Terkait**: Fase 52 — Intercompany, Transfer Pricing & Konsolidasi Keuangan Grup  
**Sertifikat Terkait**: `TransferPricingMarkupCertificate`, `TransferPricingMarkupVerifier`  

---

## 1. Intuisi Bisnis & Landasan Teori
Berdasarkan Pasal 18 ayat (3) UU PPh, Peraturan Menteri Keuangan Nomor 172 Tahun 2023 (PMK 172/2023), serta OECD Transfer Pricing Guidelines 2022, penentuan harga transfer antar-pihak yang memiliki hubungan istimewa wajib memenuhi Prinsip Kewajaran dan Kelaziman Usaha (*Arm's Length Principle* - ALP).

Untuk menguji apakah harga atau margin transfer wajar, otoritas pajak mensyaratkan penggunaan **Rentang Kewajaran (Arm's Length Range)** berbasis statistik **Rentang Interkuartil (Interquartile Range - IQR)** dari perusahaan-perusahaan pembanding independen (*comparable independent companies*).
- Jika margin transaksi terafiliasi berada di dalam rentang interkuartil $[Q_1, Q_3]$, maka harga transaksi dianggap wajar dan tidak dilakukan koreksi fiskal.
- Jika margin berada di luar rentang (di bawah $Q_1$ atau di atas $Q_3$), maka margin harus disesuaikan (*true-up adjustment*) ke titik **Median ($Q_2$)**.

Seluruh perhitungan dilakukan menggunakan aritmetika integer rasional murni dalam satuan basis points (bps, di mana 100 bps = 1,00% dan 10.000 bps = 100,00%) untuk mencegah galat pembulatan floating point yang dapat memicu sengketa pajak.

---

## 2. Formulasi Matematis

### A. Metode Transfer Pricing
1. **Cost Plus Method (CPM)**:
   $$\text{MarginBps} = \left\lfloor \frac{(\text{Price} - \text{Cost}) \times 10000}{\text{Cost}} \right\rfloor$$
2. **Resale Price Method (RPM)**:
   $$\text{MarginBps} = \left\lfloor \frac{(\text{Price} - \text{PurchaseCost}) \times 10000}{\text{Price}} \right\rfloor$$
3. **Transactional Net Margin Method (TNMM)**:
   $$\text{OperatingMarginBps} = \left\lfloor \frac{\text{OperatingProfit} \times 10000}{\text{Revenue}} \right\rfloor$$
4. **Comparable Uncontrolled Price (CUP)**:
   $$\text{PriceVarianceBps} = \left\lfloor \frac{(\text{ActualPrice} - \text{BenchmarkPrice}) \times 10000}{\text{BenchmarkPrice}} \right\rfloor$$

### B. Perhitungan Rentang Interkuartil (IQR)
Diberikan himpunan data pembanding independen terurut naik $B = [b_1, b_2, \dots, b_n]$ dengan $n \ge 4$:
1. Kuartil Bawah ($Q_1$):
   $$\text{pos}_{Q1} = \frac{n + 1}{4}$$
   Bila $\text{pos}_{Q1}$ bukan integer bulat, interpolasi linier integer dilakukan:
   $$i = \lfloor \text{pos}_{Q1} \rfloor, \quad f = (\text{pos}_{Q1} - i)$$
   $$Q_1 = b_i + \lfloor f \times (b_{i+1} - b_i) \rfloor$$
2. Median ($Q_2$):
   $$\text{pos}_{\text{med}} = \frac{n + 1}{2}$$
   $$j = \lfloor \text{pos}_{\text{med}} \rfloor, \quad f_m = (\text{pos}_{\text{med}} - j)$$
   $$\text{Median} = b_j + \lfloor f_m \times (b_{j+1} - b_j) \rfloor$$
3. Kuartil Atas ($Q_3$):
   $$\text{pos}_{Q3} = \frac{3 \times (n + 1)}{4}$$
   $$k = \lfloor \text{pos}_{Q3} \rfloor, \quad f_u = (\text{pos}_{Q3} - k)$$
   $$Q_3 = b_k + \lfloor f_u \times (b_{k+1} - b_k) \rfloor$$

### C. Evaluasi Kepatuhan & Penyesuaian True-Up
- Status Kepatuhan:
  - $\text{COMPLIANT}$: Jika $Q_1 \le \text{MarginBps} \le Q_3$.
  - $\text{BELOW_ARM_LENGTH}$: Jika $\text{MarginBps} < Q_1$.
  - $\text{ABOVE_ARM_LENGTH}$: Jika $\text{MarginBps} > Q_3$.
- Nilai Penyesuaian True-Up ke Median (untuk CPM):
  $$\text{TargetPrice} = \left\lfloor \text{Cost} + \frac{\text{Cost} \times \text{Median}}{10000} \right\rfloor$$
  $$\text{AdjustmentAmount} = \text{TargetPrice} - \text{ActualPrice}$$

---

## 3. Vektor Uji Algoritma

### Vektor 1: Uji Benchmark 7 Perusahaan Pembanding (Lulus Uji Arm's Length)
- Data Benchmark ($n = 7$ pembanding, margin dalam bps):
  $B = [400, 600, 800, 1000, 1200, 1400, 1600]$ (4,00% s/d 16,00%)
- Perhitungan Posisi:
  - $\text{pos}_{Q1} = \frac{7+1}{4} = 2 \implies Q_1 = B[2] = 600 \text{ bps}$ (6,00%).
  - $\text{pos}_{\text{med}} = \frac{7+1}{2} = 4 \implies \text{Median} = B[4] = 1000 \text{ bps}$ (10,00%).
  - $\text{pos}_{Q3} = \frac{3 \times 8}{4} = 6 \implies Q_3 = B[6] = 1400 \text{ bps}$ (14,00%).
- Transaksi Diuji (Metode CPM):
  - Biaya Produksi ($\text{Cost}$) = Rp 10.000.000.
  - Harga Transfer ($\text{Price}$) = Rp 11.200.000.
  - $\text{MarginBps} = \frac{(11.200.000 - 10.000.000) \times 10000}{10.000.000} = 1200 \text{ bps}$ (12,00%).
- Hasil: $600 \le 1200 \le 1400 \implies$ **COMPLIANT**, $\text{Adjustment} = 0$.

### Vektor 2: Margin di Bawah Rentang (Under-priced, Penyesuaian ke Median)
- Data Benchmark sama: $Q_1 = 600, \text{Median} = 1000, Q_3 = 1400$.
- Transaksi Diuji:
  - $\text{Cost}$ = Rp 10.000.000, $\text{Price}$ = Rp 10.400.000.
  - $\text{MarginBps} = 400 \text{ bps}$ (4,00%).
- Hasil: $400 < 600 \implies$ **BELOW_ARM_LENGTH**.
- Penyesuaian ke Median (1000 bps = 10%):
  - $\text{TargetPrice} = 10.000.000 + 1.000.000 = 11.000.000$.
  - $\text{AdjustmentAmount} = +600.000$ (koreksi positif pendapatan penjual).

### Vektor 3: Margin di Atas Rentang (Over-priced)
- Data Benchmark sama: $Q_1 = 600, \text{Median} = 1000, Q_3 = 1400$.
- Transaksi Diuji:
  - $\text{Cost}$ = Rp 10.000.000, $\text{Price}$ = Rp 11.800.000.
  - $\text{MarginBps} = 1800 \text{ bps}$ (18,00%).
- Hasil: $1800 > 1400 \implies$ **ABOVE_ARM_LENGTH**.
- Penyesuaian ke Median:
  - $\text{TargetPrice} = 11.000.000$.
  - $\text{AdjustmentAmount} = -800.000$ (koreksi penurunan harga transfer).

### Vektor 4: Interpolasi Pecahan Kuartil (Data Genap $n = 8$)
- Benchmark: $B = [500, 700, 900, 1100, 1300, 1500, 1700, 1900]$.
- $\text{pos}_{Q1} = \frac{9}{4} = 2.25 \implies Q_1 = 700 + \lfloor 0.25 \times (900 - 700) \rfloor = 700 + 50 = 750 \text{ bps}$.
- $\text{pos}_{\text{med}} = \frac{9}{2} = 4.5 \implies \text{Median} = 1100 + \lfloor 0.5 \times (1300 - 1100) \rfloor = 1200 \text{ bps}$.
- $\text{pos}_{Q3} = \frac{27}{4} = 6.75 \implies Q_3 = 1500 + \lfloor 0.75 \times (1700 - 1500) \rfloor = 1500 + 150 = 1650 \text{ bps}$.

---

## 4. Sertifikat Hash SHA-256
Sertifikat hasil perhitungan memuat:
- `rule_code`, `tp_method`, `cost_minor`, `actual_price_minor`, `actual_margin_bps`
- `q1_bps`, `median_bps`, `q3_bps`, `status`, `adjustment_minor`
- `certificate_hash` = $\text{SHA-256}(\text{JSON canonical terurut})$
- `verified_at` dan verifikasi integritas oleh `TransferPricingMarkupVerifier`.

