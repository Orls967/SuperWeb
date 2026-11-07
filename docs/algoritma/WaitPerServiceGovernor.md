# Spesifikasi Algoritma V15: WaitPerServiceGovernor

## 1. Ikhtisar & Deskripsi Formal
`WaitPerServiceGovernor` adalah pengendali pengalaman antrean tamu bar dan gerai makanan-minuman di dalam venue. Menggunakan pendekatan simulasi antrean multi-server deterministik (M/M/c approximation), algoritma ini mengukur waktu tunggu rata-rata per transaksi, menghasilkan skor kepuasan layanan (WPS Score), dan memicu penambahan staf (shift reinforcement) secara otomatis bila antrean memburuk.

## 2. Parameter Input & Output
- **Input (`WpsGovernorInput`):**
  - `stationId` (int): Identitas stasiun bar / outlet F&B.
  - `activeServersCount` (int): Jumlah kasir / bartender yang sedang aktif bekerja.
  - `arrivalRatePerMinuteBps` (int): Laju kedatangan pesanan per menit dalam bps (misal 6000 bps = 6 pesanan/menit).
  - `serviceRatePerMinuteBps` (int): Kemampuan pemrosesan pesanan per kasir per menit dalam bps (misal 2000 bps = 2 pesanan/menit/kasir).
  - `maxTolerableWaitMinutes` (int, default: 15): Batas waktu tunggu maksimum yang dapat ditoleransi pengunjung sebelum komplain.
- **Output (`WpsGovernorResult`):**
  - `averageWaitTimeMinutes` (int): Estimasi waktu tunggu rata-rata (dibulatkan ke integer terdekat).
  - `utilizationRateBps` (int): Rasio beban kerja staf (0 - 10000 bps).
  - `wpsScore` (int): Skor kepuasan layanan (0 - 100).
  - `reinforcementNeeded` (bool): Apakah diperlukan penambahan staf segera.
  - `recommendedAdditionalServers` (int): Jumlah tambahan kasir/bartender yang disarankan.

## 3. Formulasi & Invarian Matematis
1. **System Utilization Factor ($\rho$):**
   $$\rho = \frac{\lambda}{c \cdot \mu}$$
   Dalam basis poin integer:
   $$\rho_{\text{bps}} = \left\lfloor \frac{\lambda_{\text{bps}} \times 10000}{c \times \mu_{\text{bps}}} \right\rfloor$$
2. **Estimated Wait Time in Queue ($W_q$):**
   Untuk $\rho < 1.0$:
   $$W_q = \max\left(1, \left\lfloor \frac{\rho_{\text{bps}} \times 10}{10000 - \rho_{\text{bps}}} \right\rfloor \right) \text{ (menit)}$$
   Untuk $\rho \ge 1.0$ (sistem kelebihan beban / jenuh):
   $$W_q = \text{maxTolerableWaitMinutes} \times 2$$
3. **Wait Per Service (WPS) Score (0 - 100):**
   $$\text{WPS} = \max\left(0, 100 - \left\lfloor \frac{W_q \times 100}{\text{maxTolerableWaitMinutes}} \right\rfloor \right)$$
4. **Reinforcement Condition:**
   Jika $\text{WPS} < 60 \lor \rho_{\text{bps}} \ge 8500$, `reinforcementNeeded = true`.
   Disarankan tambahan kasir:
   $$\Delta c = \max\left(1, \left\lceil \frac{\lambda_{\text{bps}}}{\mu_{\text{bps}} \times 0.7} \right\rceil - c\right)$$

## 4. Vektor Uji Algoritma
| Staf Aktif ($c$) | Kedatangan $\lambda$ | Kapasitas Staf $\mu$ | Utilisasi $\rho$ | WPS Score | Perlu Bantuan? |
|---|---|---|---|---|---|
| 3 kasir | 6 pesanan/m | 2 pesanan/m | $100\%$ (Jenuh) | 0 | Ya ($\Delta c = 2$) |
| 4 kasir | 4 pesanan/m | 2 pesanan/m | $50\%$ | 90 | Tidak ($\Delta c = 0$) |
| 2 kasir | 3 pesanan/m | 2 pesanan/m | $75\%$ | 70 | Tidak ($\Delta c = 0$) |

