# Definition of Ready (DoR) — Fase 90: Artist Contracts, Supply Bar, Night Economics, Membership & Festival Economy

**Domain:** Venue & Entertainment (`modules/Venue/`, prefiks DB `ven_`)  
**Basis Git:** master  
**Penanggung Jawab:** Pelaksana `[G7-P5]`  
**Status:** DISALIN & DISETUJUI UNTUK EKSEKUSI VERTICAL SLICE  

---

## 1. Ruang Lingkup & Acuan Normatif
- **Acuan Utama:** `docs/KONSEP.md` §10F, Lini 15, dan `docs/PROGRESS.md` Sub-item 90.1 s/d 90.9.
- **Katalog Kontrak:**
  - `G7-K16` (`Modules\Hotel\Contracts\RoomBlockReserver`): Blok reservasi kamar hotel & glamping festival.
  - `G7-K17` (`Modules\Resto\Contracts\OutletSaleRecorder`): Penjualan outlet bar & supply F&B.
  - `G7-K18` (`Modules\Hcm\Contracts\ShiftBountyPublisher`): Publikasi bounty shift kru dadakan event.
  - `G7-K19` (`Modules\Trade\Contracts\ImportLandedCostQuoter`): Kuotasi bea masuk, cukai MMEA impor (PMK 160/2023), dan landed cost spirit.
- **Batasan Keras:**
  - Ambang Keras T-1: $B1 \ge 6.000$ baris logika bersih, $B2 \ge 35\% \times B1 \ge 2.100$ baris UI Blade/CSS/JS, $B3 \ge 1.500$ baris dokumentasi.
  - Metrik Anti-Isian T-3: M-2 $\ge 55\%$, M-3 $\le 20\%$, M-4 = 0 duplikasi 10-baris, M-5 = 0 kelas publik tanpa pemanggil.
  - P-UI 1–10: 10 state UI lengkap (loading, empty, validation, forbidden, toast, confirmation, partial, active, filter, error boundary).
  - Test Suite: $\le 5$ acceptance test terpadu di `tests/Acceptance/VenueEconomy`.

---

## 2. Empat Algoritma Bersertifikat Baru (V5 s/d V8)

### 2.1 V5: `ArtistDoorSplitCalculator` / `ArtistVersusDealSettlement`
- **Tujuan:** Menghitung penyelesaian kontrak artis berbasis kesepakatan versus (`guarantee` vs `door split`).
- **Formula:**
  $$\text{Gross Pay} = \max\left(\text{guarantee}, \frac{\text{share\_bps}}{10.000} \times (\text{door\_gross} - \text{approved\_expenses})\right)$$
  $$\text{WHT Minor} = \left\lfloor \frac{\text{Gross Pay} \times \text{wht\_bps}}{10.000} \right\rfloor$$
  $$\text{Net IDR} = \max(0, \text{Gross Pay} - \text{WHT Minor} - \text{advance\_paid\_idr})$$
  $$\text{Valas Minor (Cents)} = \left\lfloor \frac{\text{Net IDR} \times 100}{\text{exchange\_rate\_idr}} \right\rfloor$$
- **Vektor Acuan:**
  - Guarantee Rp 100.000.000; door gross Rp 600.000.000; expenses Rp 150.000.000; share 30% (3.000 bps) $\to$ Gross Rp 135.000.000.
  - Advance Rp 50.000.000 (PPh Rp 10.000.000) $\to$ Sisa Gross Rp 85.000.000, PPh 20% Rp 17.000.000, Neto Rp 68.000.000.
  - Kurs Rp 16.250 / USD $\to$ USD 4.184,61 (418.461 sen).
- **Sertifikat:** `AlgorithmCertificate` PR2 dengan verifier mandiri anti-manipulasi.

### 2.2 V6: `BundleSspAllocator` (`Modules\Shared\Domain\Algorithms\Settlement`)
- **Tujuan:** Mengalokasikan harga diskon/bundling festival ke masing-masing komponen vendor berdasarkan Standalone Selling Price (SSP) secara matematis tanpa selisih pembulatan (Largest Remainder Method / Hamilton-Hare).
- **Formula:**
  $$\text{Quota}_i = \frac{\text{Bundle Price} \times \text{SSP}_i}{\sum \text{SSP}}$$
  $$\text{Alloc}_i = \lfloor \text{Quota}_i \rfloor + \text{Remainder Allocation}$$
  $$\text{Platform Fee}_i = \left\lfloor \frac{\text{Alloc}_i \times \text{fee\_bps}}{10.000} \right\rfloor$$
  $$\text{Vendor Net}_i = \text{Alloc}_i - \text{Platform Fee}_i$$
  $$\text{Invarian:} \quad \sum \text{Alloc}_i = \text{Bundle Price}, \quad \sum (\text{Vendor Net}_i + \text{Platform Fee}_i) = \text{Bundle Price}$$
- **Vektor Acuan:**
  - Bundle Rp 2.999.999; SSP: [pass 3 hari 1.800.000, glamping 1.500.000, shuttle 300.000, beach day 400.000] (Total SSP = 4.000.000).
  - Alokasi SSP: [Rp 1.349.999, Rp 1.125.000, Rp 225.000, Rp 300.000].
  - Fee 500 bps: [Rp 67.499, Rp 56.250, Rp 11.250, Rp 15.000] = Rp 149.999.
  - Vendor Net: [Rp 1.282.500, Rp 1.068.750, Rp 213.750, Rp 285.000].

### 2.3 V7: `MembershipTierEngine` (`Modules\Venue\Domain\Algorithms`)
- **Tujuan:** Menentukan tingkat keanggotaan tahunan beach club berdasarkan pengeluaran dan kunjungan 12 bulan bergulir serta penerbitan poin loyalitas lintas ekosistem.
- **Aturan Tier:**
  - **Infinity:** Belanja 12 bulan $\ge$ Rp 75.000.000 **DAN** Kunjungan $\ge 24$ kali.
  - **Moon:** Belanja 12 bulan $\ge$ Rp 20.000.000 **ATAU** Kunjungan $\ge 12$ kali.
  - **Sun:** Tier dasar selain kondisi di atas.
  - **Downgrade Grace:** Evaluasi turun tingkat hanya terjadi pada tanggal ulang tahun keanggotaan (anniversary date) dengan grace 1 tingkat.
- **Formula Poin PTS:**
  $$\text{Points} = \left\lfloor \frac{\text{Belanja IDR}}{10.000} \right\rfloor \times \text{Multiplier} \quad (\text{Sun: } 1,0; \text{Moon: } 1,5; \text{Infinity: } 2,0)$$
- **Vektor Acuan:**
  - (80 jt, 20 kunjungan) $\to$ Moon; (18 jt, 14 kunjungan) $\to$ Moon; (75 jt, 24 kunjungan) $\to$ Infinity; (19.999.999, 11) $\to$ Sun.
  - Transaksi Rp 1.234.567 di tier Moon $\to \lfloor 123 \times 1,5 \rfloor = 184$ poin PTS.

### 2.4 V8: `SponsorExposureValuator` (`Modules\Venue\Domain\Algorithms`)
- **Tujuan:** Menilai eksposur merek terbobot (weighted impressions) dari footfall zona dan jam tayang, serta penentuan kelayakan termin pembayaran milestone berbasis KPI.
- **Formula:**
  $$\text{Weighted Impressions} = \sum (\text{Zone Footfall} \times \text{Dwell Weight})$$
  $$\text{Exposure Value} = \frac{\text{Weighted Impressions}}{1.000} \times \text{Equivalent CPM IDR}$$
  $$\text{Achievement Bps} = \left\lfloor \frac{\text{Achieved Impressions} \times 10.000}{\text{Target Impressions}} \right\rfloor$$
  $$\text{Payout Ratio} = \begin{cases} 100\% & \text{jika } \text{Achievement} \ge 10.000\text{ bps} \\ \frac{\text{Achievement}}{10.000} & \text{jika } 7.000 \le \text{Achievement} < 10.000\text{ bps} \\ 0\% & \text{jika } \text{Achievement} < 7.000\text{ bps} \end{cases}$$
- **Vektor Acuan:**
  - Footfall: [Main Stage 40.000, Beachfront 15.000, VIP 2.000] $\times$ Bobot [1,0; 0,6; 2,5] $\to 40.000 + 9.000 + 5.000 = 54.000$ impresi terbobot.
  - CPM Rp 60.000 $\to$ Nilai Rp 3.240.000.
  - Target 50.000 impresi: Capaian 54.000 $\to$ 10.800 bps $\to$ 100% termin cair. Kasus 40.000 impresi $\to$ 8.000 bps $\to$ 80% pro-rata.

---

## 3. Empat State Machine Formal

1. **`ArtistEngagementStateMachine` (7 status):**
   `offered` $\to$ `contracted` $\to$ `advance_paid` $\to$ `performed` $\to$ `settled` $\to$ `disputed` $\to$ `cancelled_force_majeure`.
2. **`MembershipStateMachine` (7 status):**
   `applied` $\to$ `kyc_pending` $\to$ `active` $\to$ `grace` $\to$ `downgraded` $\to$ `suspended` $\to$ `cancelled`.
3. **`FestivalBundleStateMachine` (8 status):**
   `quoted` $\to$ `reserved` $\to$ `paid` $\to$ `fulfilled_partial` $\to$ `fulfilled` $\to$ `settled` $\to$ `refunded_partial` $\to$ `cancelled`.
4. **`SponsorDealStateMachine` (7 status):**
   `proposed` $\to$ `signed` $\to$ `activated` $\to$ `milestone_due` $\to$ `invoiced` $\to$ `paid` $\to$ `closed`.

---

## 4. Jurnal Akuntansi Buku Besar Double-Entry (D90-8)

| Kejadian Ekonomi | Akun Debit | Akun Kredit |
|---|---|---|
| Advance Artis | `ven:artist_advance:IDR` | `ven:creator_payable:IDR` (Bank) + `ven:wht_payable:IDR` (PPh) |
| Pelunasan Artis (Final) | `ven:artist_fee_expense:IDR` | `ven:artist_advance:IDR` + `ven:creator_payable:IDR` + `ven:wht_payable:IDR` |
| Penjualan Bundle Festival | `ven:ticket_clearing:IDR` | `ven:bundle_unearned:IDR` |
| Settlement Multi-Vendor Bundle | `ven:bundle_unearned:IDR` | `ven:organizer_payable:IDR` (per vendor) + `ven:platform_fee_revenue:IDR` |
| Penagihan Milestone Sponsor | `ven:sponsor_receivable:IDR` | `ven:sponsorship_revenue:IDR` |
| Penerimaan Pembayaran Sponsor | `ven:ticket_clearing:IDR` | `ven:sponsor_receivable:IDR` |
| Akrual Cukai MMEA & Bea Impor | `ven:beverage_inventory:IDR` | `ven:customs_duty_payable:IDR` + `ven:excise_tax_payable:IDR` + `ven:vat_import_payable:IDR` |

---

## 5. Audit Mandiri & Verifikasi Dua Sumber (`venue:audit-economy`)
Perintah konsol `php artisan venue:audit-economy` memvalidasi:
1. Payout artis tepat sama dengan formula V5 dan bukti transfer/jurnal.
2. Total alokasi komponen bundle multi-vendor sama dengan harga bundle (V6) tanpa selisih pembulatan.
3. Total penerbitan poin loyalitas sinkron dengan subledger `LoyaltyLedger` di modul Mall.
4. Nilai tagihan termin sponsor berbanding lurus dengan capaian KPI impresi (V8).
5. Keseimbangan debit-kredit nol ($\sum \text{Debit} - \sum \text{Kredit} = 0$) pada seluruh akun buku besar venue ekonomi.
