# Desain Arsitektur & Spesifikasi Detail — Fase 116: Secondary Ticket Market & Dynamic Bundling

**Domain:** Venue & Entertainment (`modules/Venue/`, sub-item 116.1 s/d 116.7)  
**Terkait Modul:** Hotel (`modules/Hotel/`), Logistics (`modules/Logistics/`), Core Security, Shared Settlement  
**Tujuan Dokumen:** Menetapkan arsitektur teknis lengkap, skema basis data, spesifikasi 4 algoritma bersertifikat (V9–V12), 3 state machine, alur saga kompensasi, integrasi subledger buku besar, dan katalog antarmuka UI.  
**Klasifikasi:** Dokumen Desain Teknis (SDD)  
**Versi:** 1.0.0-PROD  

---

## 1. Arsitektur Domain & Pilar Sistem

Fase 116 memperluas sistem hiburan malam dan festival SuperWeb ke pilar pasar sekunder dan dynamic bundling melalui 6 subsistem utama:

```
+---------------------------------------------------------------------------------------------------+
|                                   VENUE SECONDARY ECOSYSTEM                                        |
+------------------------------------+----------------------------------+---------------------------+
| (1) RESALE MARKETPLACE             | (2) DYNAMIC BUNDLING             | (3) WAITLIST ENGINE       |
| • AntiScalpingCapEngine (V9)       | • DynamicBundlePricer (V10)      | • WaitlistGovernor (V11)  |
| • Single Transfer Enforcement      | • Floor Margin Guardrail         | • 15-Minute Expiration    |
| • Ticket Hash Re-generation        | • Hotel & Logistics Sync (K20/21)| • Tier Priority Ranking   |
+------------------------------------+----------------------------------+---------------------------+
| (4) BOT MITIGATION & RISK          | (5) FINANCIAL WATERFALL          | (6) AUDIT & OBSERVE       |
| • IdentityVelocityRiskScorer (K22) | • SecondaryTaxWaterfall (V12)    | • venue:audit-secondary   |
| • Max 4 Tickets / KYC ID           | • Double-entry Ledger Posting    | • Discrepancy Zero Test   |
| • Bot Blacklist Registry           | • PBJT 10% & Platform Fee        | • Tamper Proof Audit Log  |
+------------------------------------+----------------------------------+---------------------------+
```

---

## 2. Spesifikasi 4 Algoritma Bersertifikat (V9–V12)

### 2.1 Algoritma V9: `AntiScalpingCapEngine`
- **Lokasi:** `modules/Venue/Domain/Algorithms/AntiScalping/`
- **Input:** `AntiScalpingInput` ($P_{\text{original}}$, $P_{\text{asking}}$, $\text{transferCount}$, $\text{userActiveTicketCount}$, $\text{velocitySeconds}$)
- **Aturan Validasi:**
  1. $\text{transferCount} < 1$: Menolak tiket yang pernah dipindahtangankan sebelumnya.
  2. $P_{\text{asking}} \le \lfloor P_{\text{original}} \times 1,20 \rfloor$: Menolak harga jual di atas $120\%$.
  3. $\text{userActiveTicketCount} < 4$: Menolak akumulasi kepemilikan $> 4$ tiket per identitas KYC.
  4. $\text{velocitySeconds} \ge 0,5\text{ s}$: Menolak pesanan beruntun $< 500\text{ ms}$ (indikasi bot).
- **Output:** `AntiScalpingResult` & `AntiScalpingCertificate` ber-hash SHA-256.

### 2.2 Algoritma V10: `DynamicBundlePricer`
- **Lokasi:** `modules/Venue/Domain/Algorithms/Bundling/`
- **Input:** `DynamicBundleInput` ($\text{Components} = \{(\text{basePrice}_i, \text{floorCost}_i)\}$, $\text{venueOccupancyRate}$, $\text{leadTimeDays}$)
- **Formula Dinamis:**
  $$\text{SurgeMultiplier} = 1 + \left\lfloor \frac{\max(0, \text{venueOccupancyRate} - 70) \times 150}{10.000} \right\rfloor$$
  $$\text{LeadTimeDiscount} = \left\lfloor \frac{\min(30, \text{leadTimeDays}) \times 50}{10.000} \right\rfloor$$
  $$\text{CalculatedPrice} = \left\lfloor \sum \text{basePrice}_i \times (\text{SurgeMultiplier} - \text{LeadTimeDiscount}) \right\rfloor$$
  $$\text{FinalPrice} = \max\left(\sum \text{floorCost}_i, \text{CalculatedPrice}\right)$$
- **Output:** `DynamicBundleResult` & `DynamicBundlePriceCertificate`.

### 2.3 Algoritma V11: `WaitlistPriorityGovernor`
- **Lokasi:** `modules/Venue/Domain/Algorithms/Waitlist/`
- **Input:** `WaitlistGovernorInput` ($\text{Memberships} = \{(\text{id}, \text{tier}, \text{spend365}, \text{registeredAt})\}$)
- **Scoring & Sorting:**
  $$\text{Score} = (\text{TierWeight} \times 10^7) + \text{Spend365} - (\text{ElapsedSeconds} \times 100)$$
  Kandidat dengan skor tertinggi menempati urutan pertama antrean dan menerima token penawaran berjangka 900 detik (15 menit).
- **Output:** `WaitlistGovernorResult` & `WaitlistPriorityCertificate`.

### 2.4 Algoritma V12: `SecondaryMarketTaxWaterfallEngine`
- **Lokasi:** `modules/Venue/Domain/Algorithms/Marketplace/`
- **Input:** `SecondaryMarketTaxWaterfallInput` ($P_{\text{resale}}$, $P_{\text{face\_value}}$, $\text{taxRateBps} = 1000$, $\text{platformFeeRateBps} = 800$, $\text{royaltyRateBps} = 200$)
- **Pembagian Moneter Integer Konservatif:**
  1. $\text{Tax}_{\text{PBJT}} = \lfloor (P_{\text{resale}} \times \text{taxRateBps}) / 10.000 \rfloor$
  2. $\text{Fee}_{\text{Platform}} = \lfloor (P_{\text{resale}} \times \text{platformFeeRateBps}) / 10.000 \rfloor$
  3. $\text{Royalty}_{\text{Promoter}} = \lfloor (P_{\text{resale}} \times \text{royaltyRateBps}) / 10.000 \rfloor$
  4. $\text{Payout}_{\text{Seller}} = P_{\text{resale}} - \text{Tax}_{\text{PBJT}} - \text{Fee}_{\text{Platform}} - \text{Royalty}_{\text{Promoter}}$
  Invarian: $\text{Tax} + \text{Fee} + \text{Royalty} + \text{Payout} = P_{\text{resale}}$ tanpa selisih sen.
- **Output:** `SecondaryMarketTaxWaterfallResult` & `SecondaryMarketTaxWaterfallCertificate`.

---

## 3. Skema Basis Data Migrasi Baru

Migrasi baru: `2026_11_07_116200_create_ven_secondary_market_and_dynamic_bundling_tables.php`:
1. `ven_resale_listings`: Memperluas siklus hidup penjualan tiket sekunder dengan status `LISTED`, `ESCROW_HOLD`, `SETTLED`, `CANCELLED`, `EXPIRED`.
2. `ven_dynamic_bundle_packages`: Definisi paket dinamis tiket + hotel + transport + dining.
3. `ven_dynamic_bundle_components`: Rincian komponen vendor dalam paket beserta harga dasar dan biaya lantai (*floor cost*).
4. `ven_waitlist_reservations`: Pengelolaan token reservasi 15 menit dan verifikasi klaim kursi.
5. `ven_scalper_blacklist_logs`: Catatan investigasi akun yang terdeteksi bot/calo beserta alasan pencekalan.

---

## 4. State Machine Domain

1. `TicketResaleStateMachine`:
   - `LISTED` $\to$ `ESCROW_HOLD` $\to$ `SETTLED`
   - `LISTED` $\to$ `CANCELLED`
   - `ESCROW_HOLD` $\to$ `LISTED` (Kompensasi jika pembayaran pembeli gagal)
2. `WaitlistStateMachine`:
   - `WAITING` $\to$ `OFFERED` $\to$ `CLAIMED`
   - `OFFERED` $\to$ `EXPIRED` (Timeout 15 menit)
   - `WAITING` $\to$ `CANCELLED`
3. `DynamicBundlePackageStateMachine`:
   - `DRAFT` $\to$ `PUBLISHED` $\to$ `SUSPENDED` $\to$ `ARCHIVED`

---

## 5. Kontrak Aditif Lintas Modul

1. **G7-K20: `HotelPackageAvailabilityValidator`** (`modules/Hotel/Contracts/`): Memeriksa alokasi kamar hotel dan mengonfirmasi ketersediaan paket.
2. **G7-K21: `EventShuttleFleetScheduler`** (`modules/Logistics/Contracts/`): Menjadwalkan armada shuttle bus festival dari bandara ke venue.
3. **G7-K22: `IdentityVelocityRiskScorer`** (`modules/Core/Contracts/` atau `Security/`): Menilai skor anomali transaksi dan kecepatan pemesanan tiket.

---

## 6. Antarmuka Pengguna Blade (Poles UI 35% & P-UI 1–10)

1. `resale/marketplace.blade.php`: Marketplace tiket sekunder resmi dengan verifikasi status transfer dan filter zona.
2. `resale/sell.blade.php`: Form penjualan tiket dengan simulasi live price ceiling 120% dan rincian potongan waterfall.
3. `resale/orders.blade.php`: Riwayat transaksi beli/jual sekunder, escrow status, dan token tiket baru.
4. `bundles/dynamic-builder.blade.php`: Creator paket bundle terpadu (tiket + hotel + shuttle + dining) dengan live marginal cost calculator.
5. `waitlist/queue.blade.php`: Portal waitlist interaktif dengan timer mundur 15 menit dan klaim kursi instan.
6. `reports/secondary-economics.blade.php`: Dashboard analitik ekonomi sekunder, total GMV resale, fee platform, dan log bot calo yang dicekal.

Dokumen desain ini menjadi acuan tunggal implementasi Fase 116.

