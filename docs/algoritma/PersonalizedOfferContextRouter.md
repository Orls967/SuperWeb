# Spesifikasi Algoritma V16: PersonalizedOfferContextRouter

## 1. Ikhtisar & Deskripsi Formal
`PersonalizedOfferContextRouter` adalah mesin penentuan dan pengiriman penawaran promosi kontekstual on-site (di lokasi) untuk anggota klub dan tamu VIP. Algoritma ini mencocokkan lokasi zona riil tamu, tingkat keanggotaan (membership tier), riwayat belanja, dan waktu istirahat (cooldown) untuk menyajikan diskon F&B dengan batas bawah margin yang aman (margin guardrail).

## 2. Parameter Input & Output
- **Input (`PersonalizedOfferInput`):**
  - `userId` (int): Identitas pengguna / tamu VIP.
  - `currentZoneId` (int): Zona venue tempat tamu terdeteksi berada saat ini.
  - `membershipTierCode` (string): Tier anggota (`VIP_PLATINUM`, `GOLD_ELITE`, `SILVER_MEMBER`, `STANDARD`).
  - `baseItemPriceIdr` (int): Harga normal item menu F&B dalam Rupiah.
  - `itemCogsFloorPriceIdr` (int): Harga pokok penjualan (HPP) item sebagai batas bawah proteksi margin.
  - `lastOfferTimestamp` (int): Timestamp Unix penawaran terakhir yang diterima tamu.
  - `offersSentTonightCount` (int): Jumlah total penawaran yang sudah diterima tamu malam ini.
  - `currentTimestamp` (int): Waktu saat ini (Unix).
- **Output (`PersonalizedOfferResult`):**
  - `isEligible` (bool): Apakah tamu memenuhi syarat menerima promo saat ini.
  - `discountBps` (int): Besaran diskon yang diberikan dalam basis poin (misal 2000 bps = 20%).
  - `discountedPriceIdr` (int): Harga akhir setelah diskon dalam Rupiah bulat.
  - `savingsAmountIdr` (int): Nilai penghematan yang dinikmati tamu dalam Rupiah.
  - `floorGuardrailTriggered` (bool): Apakah harga terpotong oleh batas lantai HPP.
  - `offerDispatchCode` (string): Kode dispatch unik deterministik untuk mencegah duplikasi.
  - `rejectionReason` (string, nullable): Alasan penolakan jika tidak memenuhi syarat.

## 3. Formulasi & Invarian Matematis
1. **Anti-Fatigue Guardrail Check:**
   Tamu ditolak menerima penawaran jika:
   - Jeda penawaran kurang dari 1 jam (3.600 detik):
     $$(t_{\text{now}} - t_{\text{last\_offer}}) < 3600 \implies \text{COOLDOWN\_ACTIVE}$$
   - Kuota malam ini sudah habis (maksimal 3 promo):
     $$N_{\text{tonight}} \ge 3 \implies \text{DAILY\_QUOTA\_EXHAUSTED}$$
2. **Tier-Based Discount Allocation:**
   - `VIP_PLATINUM`: 2.500 bps (25%).
   - `GOLD_ELITE`: 1.500 bps (15%).
   - `SILVER_MEMBER`: 1.000 bps (10%).
   - Default: 500 bps (5%).
3. **Price Calculation with Floor Guardrail:**
   $$\text{Raw Discount Amount} = \left\lfloor \frac{\text{Base Price} \times \text{Discount Bps}}{10000} \right\rfloor$$
   $$\text{Candidate Price} = \text{Base Price} - \text{Raw Discount Amount}$$
   Jika $\text{Candidate Price} < \text{COGS Floor Price}$:
   $$\text{Final Price} = \text{COGS Floor Price}, \quad \text{floorTriggered} = \text{true}$$
   Lainnya:
   $$\text{Final Price} = \text{Candidate Price}, \quad \text{floorTriggered} = \text{false}$$
4. **Idempotency Code:**
   $$\text{Code} = \text{PROMO-CTX-} \parallel \text{userId} \parallel \text{-} \parallel \text{zoneId} \parallel \text{-} \parallel \lfloor t_{\text{now}} / 3600 \rfloor$$

## 4. Vektor Uji Algoritma
| Tier Anggota | Base Price | Floor COGS | Cooldown | Tonight Count | Final Price | Status |
|---|---|---|---|---|---|---|
| `VIP_PLATINUM` (25%) | Rp 2.000.000 | Rp 1.600.000 | 4.000s | 1 promo | Rp 1.600.000 (Floor dipicu) | Eligible |
| `VIP_PLATINUM` (25%) | Rp 2.000.000 | Rp 1.200.000 | 4.000s | 1 promo | Rp 1.500.000 | Eligible |
| `GOLD_ELITE` (15%) | Rp 1.000.000 | Rp 700.000 | 1.200s | 0 promo | - | Ditolak (Cooldown) |
| `SILVER_MEMBER` (10%) | Rp 500.000 | Rp 300.000 | 5.000s | 3 promo | - | Ditolak (Quota Habis) |

