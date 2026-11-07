# Spesifikasi Algoritma V14: CrowdDensitySurgePredictor

## 1. Ikhtisar & Deskripsi Formal
`CrowdDensitySurgePredictor` adalah mesin prediksi proaktif keselamatan massa yang memproyeksikan headcount dan densitas kerumunan per meter persegi untuk jendela waktu 15 menit ke depan. Dirancang untuk mendeteksi potensi crowd surge / crush di pintu masuk, lorong, dan area festival sebelum kepadatan fisik mencapai level kritis.

## 2. Parameter Input & Output
- **Input (`CrowdPredictionInput`):**
  - `zoneId` (int): Identitas zona venue.
  - `currentHeadcount` (int): Jumlah orang di dalam zona saat ini.
  - `zoneAreaSqm` (int): Luas area zona dalam meter persegi ($m^2$).
  - `legalCapacityLimit` (int): Kapasitas maksimum berizin zona.
  - `inflowVelocityPerMinute` (int): Laju kedatangan massa per menit.
  - `outflowVelocityPerMinute` (int): Laju kepulangan / keluar massa per menit.
  - `weatherFactorBps` (int, default: 10000 = 1.0): Koefisien cuaca (misal hujan lebat mempercepat desakan masuk).
- **Output (`CrowdPredictionResult`):**
  - `predictedHeadcount15m` (int): Proyeksi jumlah orang setelah 15 menit.
  - `predictedDensityBps` (int): Densitas orang per $m^2$ dalam basis poin (misal 42000 bps = 4.20 orang/$m^2$).
  - `recommendedAction` (string): Tindakan keselamatan yang direkomendasikan (`NORMAL`, `SLOW_ENTRY`, `RESTRICT_ACCESS`, `EMERGENCY_DIVERSION`).
  - `gateRestricted` (bool): Apakah gerbang masuk wajib dikunci/dibatasi otomatis.
  - `certificate` (CrowdPredictionCertificate): Sertifikat integritas verifikasi.

## 3. Formulasi & Invarian Matematis
1. **Net Flow Rate:**
   $$\Delta V = \left( V_{\text{in}} - V_{\text{out}} \right) \times \frac{\text{Weather Factor}}{10000}$$
2. **Projected 15-Minute Headcount:**
   $$\hat{H}_{t+15} = \max\left(0, H_t + (15 \times \Delta V)\right)$$
3. **Projected Density:**
   $$\hat{D}_{t+15} = \frac{\hat{H}_{t+15} \times 10000}{\text{Area Zone } m^2} \text{ (bps)}$$
4. **Action Determination Rules:**
   - $\hat{H}_{t+15} \ge \text{Capacity Limit} \lor \hat{D}_{t+15} \ge 45000 \text{ bps}$ ($4.5 \text{ org/m}^2$): `RESTRICT_ACCESS`, `gateRestricted = true`.
   - $\hat{D}_{t+15} \ge 35000 \text{ bps}$ ($3.5 \text{ org/m}^2$): `SLOW_ENTRY`, `gateRestricted = false`.
   - Default: `NORMAL`, `gateRestricted = false`.

## 4. Vektor Uji Algoritma
| Headcount Saat Ini | Limit Kapasitas | Inflow / Outflow | Luas $m^2$ | Harapan Prediksi 15m | Aksi |
|---|---|---|---|---|---|
| 450 orang | 500 orang | 10 in / 2 out | 100 $m^2$ | $450 + 15(8) = 570$ | `RESTRICT_ACCESS` (Gate Locked) |
| 200 orang | 500 orang | 5 in / 5 out | 100 $m^2$ | $200 + 15(0) = 200$ | `NORMAL` (Aman) |
| 350 orang | 500 orang | 8 in / 2 out | 100 $m^2$ | $350 + 15(6) = 440$ | `SLOW_ENTRY` (Waspada) |

