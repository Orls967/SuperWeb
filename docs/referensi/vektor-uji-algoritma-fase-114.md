# Spesifikasi Vektor Uji Numerik: Algoritma Fase 114

## 1. Vektor Uji H14: BlockAllotmentReleaser

### 1.1 Kasus Uji H14-01: Masa Proteksi Penuh ($\Delta = 45$ Hari)
- **Input:**
  - $K_0 = 200$, $K_t = 200$, $P_t = 30$, $\Delta = 45$ hari.
- **Perhitungan:**
  - $U_t = 200 - 30 = 170$ kamar belum pickup.
  - Karena $\Delta > 30$, faktor pelepasan $\alpha_t = 0.0$.
  - $R_t = \lfloor 0.0 \times 170 \rfloor = 0$.
  - $K_{t+1} = 200 - 0 = 200$.
- **Ekspektasi Output:**
  - `cutoff_tier`: "PROTECTED"
  - `rooms_released`: 0
  - `remaining_contracted_rooms`: 200
  - `certificate_signature`: valid SHA-256

### 1.2 Kasus Uji H14-02: Cutoff Tier 1 H-30 ($\Delta = 25$ Hari)
- **Input:**
  - $K_0 = 200$, $K_t = 200$, $P_t = 60$, $\Delta = 25$ hari.
- **Perhitungan:**
  - $U_t = 200 - 60 = 140$ kamar.
  - Karena $14 < \Delta \le 30$, faktor $\alpha_t = 0.25$.
  - $R_t = \lfloor 0.25 \times 140 \rfloor = 35$ kamar dilepaskan ke inventori publik.
  - $K_{t+1} = 200 - 35 = 165$ kamar.
- **Ekspektasi Output:**
  - `cutoff_tier`: "H30"
  - `rooms_released`: 35
  - `remaining_contracted_rooms`: 165

### 1.3 Kasus Uji H14-03: Cutoff Tier 2 H-14 ($\Delta = 10$ Hari)
- **Input:**
  - $K_0 = 200$, $K_t = 165$, $P_t = 85$, $\Delta = 10$ hari.
- **Perhitungan:**
  - $U_t = 165 - 85 = 80$ kamar unpicked.
  - Karena $7 < \Delta \le 14$, faktor $\alpha_t = 0.50$.
  - $R_t = \lfloor 0.50 \times 80 \rfloor = 40$ kamar dilepaskan.
  - $K_{t+1} = 165 - 40 = 125$ kamar.
- **Ekspektasi Output:**
  - `cutoff_tier`: "H14"
  - `rooms_released`: 40
  - `remaining_contracted_rooms`: 125

### 1.4 Kasus Uji H14-04: Final Cutoff H-7 ($\Delta = 5$ Hari)
- **Input:**
  - $K_0 = 200$, $K_t = 125$, $P_t = 110$, $\Delta = 5$ hari.
- **Perhitungan:**
  - $U_t = 125 - 110 = 15$ kamar unpicked.
  - Karena $\Delta \le 7$, faktor $\alpha_t = 1.00$.
  - $R_t = \lfloor 1.00 \times 15 \rfloor = 15$ kamar dilepas habis.
  - $K_{t+1} = 125 - 15 = 110$ kamar.
- **Ekspektasi Output:**
  - `cutoff_tier`: "H7_FINAL"
  - `rooms_released`: 15
  - `remaining_contracted_rooms`: 110 (Tepat sama dengan $P_t$).

---

## 2. Vektor Uji H15: BanquetBomExploder

### 2.1 Kasus Uji H15-01: Perjamuan 300 Pax dengan Buffer 5%
- **Input:**
  - `guaranteedPax`: 300
  - `safetyBufferRatio`: 1.05
  - Menu 1: Beef Tenderloin (Porsi: 200 gram, Biaya: IDR 300/gram, Yield Loss: 0.10 atau 10% susut)
  - Menu 2: Jus Jeruk Segar (Porsi: 250 ml, Ukuran botol: 1.000 ml, Biaya: IDR 35.000/botol, Pour Shrinkage: 0.05 atau 5% tumpah)
  - `eventDurationHours`: 4 jam
  - `hourlyLaborRateIdr`: IDR 50.000 / jam
- **Perhitungan:**
  - Porsi Dapur $N_{\text{prep}} = \lceil 300 \times 1.05 \rceil = 315$ porsi.
  - Daging Sapi Mentah:
    $$Q_{\text{beef}} = \frac{315 \times 200}{1 - 0.10} = \frac{63.000}{0.90} = 70.000 \text{ gram} = 70 \text{ kg}$$
    Biaya Daging: $70.000 \times 300 = \text{IDR } 21.000.000$.
  - Jus Jeruk:
    $$N_{\text{bev\_servings}} = \lceil 315 \times 1.05 \rceil = 331 \text{ gelas}$$
    Volume Total: $331 \times 250\text{ ml} = 82.750\text{ ml}$.
    Jumlah Botol: $\lceil 82.750 / 1.000 \rceil = 83\text{ botol}$.
    Biaya Jus: $83 \times 35.000 = \text{IDR } 2.905.000$.
  - Tenaga Kerja:
    - Pramusaji: $\lceil 300 / 20 \rceil = 15$ orang.
    - Koki: $\lceil 300 / 40 \rceil = 8$ orang.
    - Total Jam Kerja: $(15 + 8) \times 4 = 92\text{ jam}$.
    - Biaya Upah: $92 \times 50.000 = \text{IDR } 4.600.000$.
  - Total Biaya Langsung: $21.000.000 + 2.905.000 + 4.600.000 = \text{IDR } 28.505.000$.

---

## 3. Vektor Uji H16: EventMarginCalculator

### 3.1 Kasus Uji H16-01: Proposal Menguntungkan (Margin 54,67%)
- **Input:**
  - Pendapatan: Sewa Hall IDR 50.000.000, F&B IDR 75.000.000, AV IDR 15.000.000, Kamar IDR 10.000.000.
  - Total Pendapatan $R_{\text{total}} = \text{IDR } 150.000.000$.
  - Biaya Langsung: Food COGS IDR 35.000.000, Bev COGS IDR 10.000.000, Labor IDR 8.000.000, Vendor AV IDR 15.000.000.
  - Total Biaya Langsung $C_{\text{direct}} = \text{IDR } 68.000.000$.
- **Perhitungan:**
  - Margin Bersih: $150.000.000 - 68.000.000 = \text{IDR } 82.000.000$.
  - Margin Persentase: $(82.000.000 / 150.000.000) \times 100\% = 54,67\%$.
  - Karena $54,67\% \ge 40,0\%$, status: `GUARDRAIL_COMPLIANT_APPROVED`. Shortfall = 0.

### 3.2 Kasus Uji H16-02: Proposal Pelanggaran Guardrail (Margin 30,0%)
- **Input:**
  - Pendapatan: IDR 100.000.000.
  - Biaya Langsung: IDR 70.000.000.
- **Perhitungan:**
  - Margin Bersih: IDR 30.000.000.
  - Margin Persentase: $30,0\% < 40,0\%$.
  - Status: `GUARDRAIL_BREACH_REJECTED`.
  - Harga Minimum Wajib: $\lceil 70.000.000 / 0,60 \rceil = \text{IDR } 116.666.667$.
  - Shortfall Penjualan: $116.666.667 - 100.000.000 = \text{IDR } 16.666.667$.

---

## 4. Vektor Uji H17: MilestoneScheduleBuilder

### 4.1 Kasus Uji H17-01: Kontrak Pernikahan IDR 250.000.000
- **Input:**
  - $V_{\text{contract}} = \text{IDR } 250.000.000$.
  - $D_{\text{sign}} = \text{2026-11-08}$, $D_{\text{event}} = \text{2027-06-15}$.
- **Perhitungan:**
  - Termin 1 (20%): $\lfloor 0.20 \times 250.000.000 \rfloor = \text{IDR } 50.000.000$. Jatuh tempo: 2026-11-15.
  - Termin 2 (30%): $\lfloor 0.30 \times 250.000.000 \rfloor = \text{IDR } 75.000.000$. Jatuh tempo: 2027-03-17 (H-90).
  - Termin 3 (30%): $\lfloor 0.30 \times 250.000.000 \rfloor = \text{IDR } 75.000.000$. Jatuh tempo: 2027-05-16 (H-30).
  - Termin 4 (Pelunasan Sisa): $250.000.000 - (50.000.000 + 75.000.000 + 75.000.000) = \text{IDR } 50.000.000$. Jatuh tempo: 2027-06-08 (H-7).
  - Total Akumulasi: Tepat IDR 250.000.000 (Selisih 0 rupiah).
