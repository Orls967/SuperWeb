# ADR-0116: Arsitektur Pasar Sekunder Tiket Anti-Scalping, Dynamic Event Bundling & Waitlist Otomatis

**Status:** DITERIMA / AKTIF  
**Tanggal:** 2026-11-07  
**Pembuat:** Tim Arsitektur Gelombang 7 `[G7-P5]` — Venue & Entertainment  
**Konteks Bisnis:** Fase 116 (`KONSEP.md` §10F, Lini 15, sub-item 116.1 s/d 116.7)  

---

## 1. Konteks & Latar Belakang

Pasar sekunder tiket acara hiburan sering kali dieksploitasi oleh calo yang membeli tiket massal menggunakan skrip otomatis, lalu menjualnya kembali dengan margin spekulatif 300%–500%. Hal ini merugikan penggemar sejati, merusak reputasi acara, serta menghilangkan hak pajak daerah dan royalti artis/venue.

Di sisi lain, paket wisata festival terpadu membutuhkan bundling dinamis antara tiket masuk, akomodasi hotel, armada transportasi, dan konsumsi makanan tanpa memicu kebocoran margin di bawah biaya marjinal (*marginal cost*).

---

## 2. Keputusan Arsitektur

### 2.1 Algoritma V9 Anti-Scalping Cap Engine & Single Transfer Enforcer
- **Plafon Harga Keras:** Harga jual sekunder dibatasi secara matematis pada $\le 120\%$ dari harga nominal beli awal (*original face value*). Penjualan di atas nilai ini langsung ditolak pada validasi domain.
- **Satu Kali Transfer (*Single Transfer Limit*):** Tiket yang dibeli melalui pasar sekunder menerima hash tiket baru dan status `is_secondary_transfer = true`. Sistem menolak penerbitan listing penjualan ulang kedua untuk tiket yang sama.
- **Sertifikasi Kriptografis:** Setiap evaluasi harga dan batas transfer menghasilkan sertifikat SHA-256 (`AntiScalpingCertificate`) yang diverifikasi oleh `AntiScalpingVerifier`.

### 2.2 Algoritma V10 Dynamic Bundle Pricer dengan Floor Guardrail
- **Formula Penetapan Harga Dinamis:**
  $$\text{BundlePrice} = \max\left(\sum_{i=1}^n \text{FloorCost}_i, \left\lfloor \sum_{i=1}^n \text{BasePrice}_i \times (1 + \text{SurgeBps}_{\text{occ}} - \text{DiscountBps}_{\text{lead}}) \right\rfloor\right)$$
- **Guardrail Floor:** Harga paket tidak pernah boleh berada di bawah total biaya lantai komponen penyusunnya ($\sum \text{FloorCost}_i$), memastikan venue dan mitra tidak pernah menjual paket dalam kondisi rugi marjinal.

### 2.3 Algoritma V11 Waitlist Priority Governor & 15-Minute Expiry
- **Pembobotan Antrean Waitlist:**
  $$\text{Score} = (\text{TierWeight} \times 1.000.000) + \left\lfloor \frac{\text{Spend365}}{10.000} \right\rfloor - \text{RegistrationAgeSeconds}$$
  di mana tier `Infinity` berbobot 300, `Moon` berbobot 200, `Sun` berbobot 100, dan non-member berbobot 0.
- **Jendela Reservasi Eksklusif:** Peminat terpilih diberikan token reservasi berjangka waktu persis 15 menit (900 detik). Jika tidak diselesaikan dalam 15 menit, token kedaluwarsa dan kursi ditawarkan ke kandidat urutan berikutnya.

### 2.4 Algoritma V12 Secondary Market Tax Waterfall Engine
- Pembagian pendapatan kotor penjualan sekunder mengikuti urutan waterfall konservasi integer:
  1. Pajak Hiburan Daerah PBJT Sekunder: $10\%$ dari selisih margin atau total harga sesuai perda.
  2. Platform Marketplace Fee: $8\%$ dari total transaksi.
  3. Royalty Withholding Promotor/Artis: $2\%$ dari total transaksi.
  4. Seller Net Payout: Sisa nominal bersih ditransfer ke rekening dompet penjual.

---

## 3. Konsekuensi

### Konsekuensi Positif
- Menghilangkan praktik percaloan tiket dan spekulasi liar secara deterministik.
- Memberikan transparansi harga dan jaminan tiket 100% otentik kepada pengunjung.
- Mengoptimalkan okupansi venue melalui antrean waitlist otomatis dan dynamic bundling terpadu.
- Menjamin kepatuhan perpajakan dan hak royalti promotor pada setiap transaksi sekunder.

### Mitigasi Risiko
- **Anomali Bot Masif:** Dilindungi oleh kontrak G7-K22 `IdentityVelocityRiskScorer` yang membatasi pesanan per NIK dan kecepatan request.
- **Overbooking Kamar Paket:** Dilindungi oleh kontrak G7-K20 `HotelPackageAvailabilityValidator` dengan reservasi blok atomik.

