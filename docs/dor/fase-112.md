# Definition of Ready (DoR) — Fase 112: Hospitality & Entertainment: Global Loyalty & Travel Pass

## 1. Identifikasi Fase
- **Fase:** 112
- **Nama:** Hospitality & Entertainment: Global Loyalty & Travel Pass
- **Domain:** Perhotelan & Hiburan Global (Modul `Hotel`, prefix `htl_`)
- **Prasyarat:** Fase 91 (PMS, CRS, Dynamic Rate) ✅, Fase 92 (Folio Multi-Payer, Timeshare & Destination Package) ✅
- **Acuan Arsitektur:** KONSEP 11F, Standar Ketat W8 (Poles UI 35%), Prinsip Ledger Berimbang A14.5

## 2. Ruang Lingkup & Deliverables
Fase 112 mencakup 7 deliverable utama:
1. **112.1 Travel Pass Global:** Satu keanggotaan lintas properti hotel, venue, resto, airline partner, dan transportasi darat dengan sistem tier berjenjang (Silver, Gold, Platinum, Black) berbasis malam menginap dan pembelanjaan gabungan.
2. **112.2 Poin Lintas-Batas & FIFO Liability Clearing:** Akrual poin dari 17 lini ekosistem, penukaran melalui redemption matrix terpusat, alokasi pengurangan liabilitas berbasis FIFO batch, serta pengakuan breakage revenue otomatis sesuai standar akuntansi internasional (IFRS 15 / PSAK 72).
3. **112.3 Aliansi Maskapai & Hotel (Partner Point Conversion):** Transfer poin dua arah dengan mitra eksternal (maskapai, kereta api, ride-hailing), pemotongan transaction conversion fee, serta penyelesaian piutang/utang netting berkala.
4. **112.4 Dynamic Award Pricing:** Penentuan tarif award room night berbasis poin yang berfluktuasi secara dinamis terhadap kurva okupansi hotel, dengan guardrail perlindungan batas bawah (floor rate) 115% dari liabilitas dasar.
5. **112.5 Personalization & Recommendation Engine:** Profil riwayat 360°, vektor preferensi tamu (RFM, minat event, riwayat fasilitas), rekomendasi paket tujuan dan penawaran berbatas waktu dengan audit privasi dan kepatuhan preferensi komunikasi tamu.
6. **112.6 Pengujian & Rekonsiliasi Matematika:** Invarian kekekalan saldo poin ($\sum \text{Issued} = \text{Earned} - \text{Redeemed} - \text{Expired}$), keseimbangan buku besar liabilitas PTS, akurasi conversion fee, dan zero-discrepancy multi-lini reconciliation.
7. **112.7 Quality Gate Fase 112:** Verifikasi kepatuhan seluruh aturan arsitektur A1–A13, zero scan debt, formatting PSR-12 via Pint, dan coverage acceptance test.

## 3. Ambang Keras Metrik Kode & Standar W8
- **B1 (Total Baris Kode Produksi & UI):** $\ge 6.000$ baris (bersih tanpa komentar berulang atau whitespace buatan).
- **B2 (UI Views & Components):** $\ge 35\% \times \text{B1} = 2.100$ baris Blade interaktif.
- **B3 (Dokumentasi Arsitektur, DoR, Runbook):** $\ge 1.500$ baris.
- **M-2 (Kepadatan Logika Inti):** $\ge 55\%$.
- **M-3 (Struktur Tipis/Boilerplate):** $\le 20\%$.
- **Zero New Architecture Debt:** Baseline `arch-scan.json` tidak bertambah.

## 4. 4 Algoritma Bersertifikat Resmi (H10–H13)
1. **PointsLiabilityFifo (H10):** Mesin pelunasan liabilitas poin berbasis FIFO batch dengan sertifikat audit hash sha256 dan deteksi kedaluwarsa poin.
2. **AwardNightPricer (H11):** Mesin kalkulasi tarif poin dinamis dengan perlindungan floor multiplier 115% dan elastisitas okupansi musiman.
3. **PartnerPointConverter (H12):** Konversi poin aliansi bilateral dengan kurs konversi dinamis dan perhitungan amortisasi conversion fee.
4. **GlobalTierQualifier (H13):** Algoritma evaluasi kualifikasi tier global multi-metrik berbasis rolling 365 hari.

## 5. Saga Berkompensasi & State Machine
- **Saga:** `HotelPartnerPointTransferSaga` mengelola orkestrasi transfer poin ke mitra aliansi dengan langkah mitigasi rollback jika terjadi kegagalan konfirmasi mitra.
- **State Machine 1:** `HotelTravelPassTierStateMachine` (Active, GracePeriod, Downgraded, BlackListed, Requalified).
- **State Machine 2:** `HotelPointBatchStateMachine` (Active, PartiallyRedeemed, FullyRedeemed, Expired, WrittenOffBreakage).

## 6. Integrasi Buku Besar (Chart of Accounts)
- `htl:loyalty_points_liability:IDR` (Liabilitas poin beredar)
- `htl:loyalty_program_expense:IDR` (Beban pemasaran loyalitas)
- `htl:loyalty_breakage_revenue:IDR` (Pendapatan poin kedaluwarsa / breakage)
- `htl:partner_points_payable:IDR` (Utang transfer poin mitra aliansi)
- `htl:transfer_fee_revenue:IDR` (Pendapatan biaya konversi aliansi)
- `htl:loyalty_redemption_settlement:IDR` (Akun kliring penukaran reward)
- `clearing:external:IDR` (Akun perantara penyelesaian kas/bank eksternal)

## 7. Kriteria Selesai (Definition of Done)
1. 4 Algoritma bersertifikat lolos pengujian unit dan verifikasi hash deterministik.
2. 5 Acceptance tests mencakup seluruh skenario operasional dan regresi lolos Pest.
3. Pest modul Hotel dan Venue lolos 100%.
4. `php artisan arch:scan` lolos dengan 0 pelanggaran baru.
5. Format kode bersih sesuai standar Laravel Pint.
6. Ambang baris B1 $\ge 6.000$, B2 $\ge 2.100$, B3 $\ge 1.500$ terpenuhi.

