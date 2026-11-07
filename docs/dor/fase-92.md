# Definition of Ready (DoR) — Fase 92
## Perhotelan II: Folio Split, F&B/Banquet, Loyalty Nights, Timeshare & Destination Package

### 1. Informasi Umum
- **Fase**: 92
- **Nama Modul**: `Hotel` (`htl_`), berkolaborasi dengan `Resto`, `Venue`, `Logistics`, `Banking`, `Hcm`, dan `Rwa`
- **Tipe Fase**: Kelas Lebar (7 sub-item PROGRESS + 6 integrasi lintas modul)
- **Status DoR**: SIAP DIKERJAKAN (Approved)
- **Pelaksana**: G7-P6 (Perhotelan)

---

### 2. Ruang Lingkup dan Batasan
- **92.1 Folio & Upsell**:
  - Folio terpadu per kamar/tamu yang mengonsolidasi seluruh transaksi inap: kamar, room service F&B, spa, laundry, minibar, parkir valet, dan surcharge.
  - Multi-payer split settlement: pemisahan tagihan kamar & pajak ke City Ledger korporat (`htl:city_ledger:IDR`) dan pengeluaran insidental ke personal guest card/wallet/cash.
  - Credit limit dinamis per payer dengan pemantauan pagu piutang dan toleransi.
  - Prinsip Folio Saldo Nol: Checkout diblokir jika saldo salah satu sub-folio $\neq 0$.
- **92.2 F&B & Banquet**:
  - Banquet Event Order (BEO) terikat BOM dapur restoran hotel (HPP bahan baku, recipe yield, waste margin).
  - Minimum spend contract, durasi sewa ruang hall/meeting room, koordinasi katering per jam.
  - Gross Margin Guard: Penolakan pesanan banquet secara otomatis bila estimasi margin kotor $< 40\%$.
- **92.3 Spa & Wellness**:
  - Katalog treatment kesehatan & kecantikan, reservasi terapis (terhubung HCM gig shift bounty G7-K18).
  - Pembebanan konsumsi produk spa langsung ke folio tamu (`ROOM_CHARGE_SPA`).
- **92.4 Stay Passport & Loyalty Nights**:
  - Riwayat preferensi tamu (lantai, tipe bantal, alergi makanan).
  - Mesin tier loyalitas (Silver, Gold, Platinum) dengan aturan poin FIFO (`PTS`), benefit upgrade gratis, dan award night eligibility.
  - `ChurnRiskScorer` (H9): Penilaian probabilitas churn tamu berbasis recency, frequency, monetary, dan sentimen service recovery.
- **92.5 Timeshare & Fractional Ownership**:
  - Tokenisasi unit villa resort / serviced residence (terhubung RWA Fractional Registry).
  - Hak kuota jadwal inap tahunan (usage schedule) via `FractionalUsageScheduler` (H8).
  - Pengalihan minggu hak guna yang tidak dipakai (*unutilized nights*) ke rental pool umum properti.
  - Pembagian dividen sewa harian/bulanan secara pro-rata kepada pemegang token setelah dipotong management fee (15%).
- **92.6 Destination Package Engine**:
  - Paket wisata multi-vendor: Hotel Room + Beach Club Pass (Venue Fase 89) + Shuttle Transfer (Logistics Fase 45) + Tiket Konser/Festival (Venue Fase 90).
  - Satu harga bundel, satu pembayaran checkout, satu faktur terpadu.
  - Alokasi nilai wajar via `BundleSspAllocator` (Standar Akuntansi IFRS 15 / PSAK 72).
  - Escrow multi-vendor bertahap: pembayaran ditahan di clearing escrow bank, dilepas bertahap saat masing-masing vendor mengonfirmasi pemenuhan layanan (*service delivery milestone*).
- **92.7 MICE & Wedding Sales**:
  - Pipeline penjualan B2B, penawaran harga berjenjang, jadwal deposit milestone (uang muka 20%, progres 50%, pelunasan pra-acara).
  - Koordinasi venue lintas aset (hotel hall, venue beach club, atrium).

---

### 3. Algoritma Bersertifikat Wajib (H6–H9)
1. **H6: FolioSplitRouter**:
   - Mempartisi baris transaksi folio ke bucket penagihan (*Personal vs Corporate/Direct Billing*) berdasarkan aturan kontrak perusahaan dan kategori item.
   - Sertifikat: `FolioSplitCertificate` dengan verifikasi konservasi saldo total ($\sum SubFolio = \sum LineItems$).
2. **H7: LoyaltyNightTierEngine**:
   - Menghitung perolehan poin malam menginap, kualifikasi kenaikan tingkat membership, dan pemotongan poin FIFO.
   - Sertifikat: `LoyaltyTierCertificate` dengan validasi kepatuhan aturan kedaluwarsa dan tier threshold.
3. **H8: FractionalUsageScheduler**:
   - Menjadwalkan alokasi minggu tinggal timeshare berbasis poin fraksional dan pembagian kuota musim (Peak, High, Low).
   - Sertifikat: `FractionalScheduleCertificate` dengan jaminan bebas benturan jadwal (*zero-collision guarantee*).
4. **H9: ChurnRiskScorer**:
   - Menghitung skor risiko atrisi tamu hotel ($0.0 - 1.0$) berdasarkan RFM matriks dan log keluhan masa inap.
   - Sertifikat: `ChurnRiskCertificate` dengan ambang intervensi retensi proaktif.

---

### 4. Ambang Keras Fase 92 (Kelas Lebar)
- **B1 (Kode Bersih Tambah)**: $\ge 7.000$ baris
- **B2 (Poles UI Tambah)**: $\ge 35\% \times \text{B1} = 2.450$ baris
- **B3 (Dokumentasi Tambah)**: $\ge 1.500$ baris
- **M-2 (Logika Inti)**: $\ge 55\%$
- **M-3 (Struktur Tipis)**: $\le 20\%$
- **Acceptance Tests**: $\le 5$ tests komprehensif
- **Zero Arch Scan Debt**: 0 pelanggaran baru A1–A13

