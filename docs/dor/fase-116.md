# Definition of Ready (DoR) — Fase 116: Secondary Ticket Market & Dynamic Bundling

**Domain:** Venue & Entertainment (`modules/Venue/`, sub-item 116.1 s/d 116.7)  
**Terkait Modul:** Hotel (`modules/Hotel/`), Logistics (`modules/Logistics/`), Core Security, Shared Settlement  
**Prasyarat:** Fase 89 & Fase 90 ✅ Merged ke `master` (`11e60076929a70abee011a7a62066c52b45976e6`)  
**Penanggung Jawab:** Lead Cryptographer & Lead Financial Architect `[G7-P5]`  
**Status DoR:** DISETUJUI & SIAP EKSEKUSI 🔵  

---

## 1. Latar Belakang & Pernyataan Masalah

Pasar sekunder tiket hiburan (*secondary ticket resale*) secara historis sarat dengan spekulasi liar calo (*scalpers*), penipuan tiket palsu atau ganda (*double-spending ticket*), penggembungan harga ekstrem (hingga 500% dari harga asli), serta kebocoran pajak daerah dan royalti penyelenggara/artis.

Fase 116 menghadirkan **Pasar Sekunder Resmi Terdesentralisasi dalam Monolith** dengan tata kelola ekonomi ketat:
1. **Batas Harga Anti-Scalping Keras (*Hard Price Cap 120%*):** Tiket resmi hanya dapat dijual kembali maksimal $120\%$ (12.000 bps) dari harga beli perdana (*original face value*). Penjualan di atas batas ini otomatis ditolak oleh algoritma V9 `AntiScalpingCapEngine`.
2. **Pembatasan Transfer Tunggal (*Single Transfer Rule*):** Setiap tiket hanya dapat dipindahtangankan paling banyak satu kali ($N=1$). Setelah tiket sekunder dibeli, status terkunci permanen ke pembeli baru dan dilarang masuk listing penjualan ulang.
3. **Pemberantasan Bot Scalper (*Velocity & Identity Check*):** Deteksi bot pembelian masif via kontrak aditif G7-K22 `IdentityVelocityRiskScorer` yang membatasi kuota kepemilikan tiket maksimal 4 lembar per NIK/identitas KYC terverifikasi, serta memblokir order dengan laju transaksi $> 2$ order/detik.
4. **Dynamic Event Bundling:** Penggabungan tiket pertunjukan dengan kamar hotel/glamping (kontrak aditif G7-K20 `HotelPackageAvailabilityValidator`), layanan shuttle bus VIP (kontrak aditif G7-K21 `EventShuttleFleetScheduler`), dan dining voucher resto. Penetapan harga paket dioptimalkan secara dinamis oleh algoritma V10 `DynamicBundlePricer` dengan lantai pengaman margin (*floor guardrail*).
5. **Antrean Waitlist Cerdas 15 Menit:** Saat suatu zona/kategori tiket ludes (*sold out*), peminat masuk antrean waitlist yang dikelola algoritma V11 `WaitlistPriorityGovernor`. Ketika terjadi pelepasan kursi/pembatalan, penawaran tiket otomatis diberikan ke peringkat teratas antrean dengan jendela reservasi eksklusif selama 15 menit (900 detik).
6. **Waterfall Pajak & Fee Pasar Sekunder:** Algoritma bersertifikat V12 `SecondaryMarketTaxWaterfallEngine` membagi perolehan penjualan sekunder secara deterministik ke dalam kas penjual, platform fee, potongan royalti promotor/artis, dan pajak hiburan PBJT sekunder.

---

## 2. Kriteria Penerimaan (Acceptance Criteria) per Sub-Item PROGRESS.md

### 116.1 Resale Marketplace Resmi & Transfer Terkontrol
- [x] Listing tiket sekunder memverifikasi keabsahan hash tiket awal dan membatasi `transfer_count <= 1`.
- [x] Harga penawaran dibatasi plafon $\le 120\%$ dari harga beli awal.
- [x] Pembeli baru menerima hash tiket baru yang diturunkan secara deterministik dan dicatat pada audit log.
- [x] Penjual wajib memiliki wallet rekening terverifikasi.

### 116.2 Anti-Scalping Enforcement & Deteksi Bot
- [x] Pengecekan limit kepemilikan tiket: maksimal 4 tiket per identitas KYC pada satu acara.
- [x] Velocity check mendeteksi pesanan mencurigakan dalam selang waktu $< 500\text{ ms}$.
- [x] Akun yang terindikasi calo/bot masuk ke daftar hitam (`ven_scalper_blacklist_logs`) dengan pembekuan akun instan.

### 116.3 Dynamic Bundling Event Lintas Modul
- [x] Orkestrasi paket bundle mencakup minimal 3 lini: Tiket Venue, Kamar Hotel (G7-K20), dan Shuttle Transportasi (G7-K21).
- [x] Harga bundle dihitung dinamis: $\text{Price}_{\text{bundle}} \ge \sum \text{FloorCost}_i$.
- [x] Algoritma V10 mengoreksi harga bundle secara otomatis seiring meningkatnya okupansi venue/hotel.

### 116.4 Waitlist & Seat Release Otomatis
- [x] Antrean waitlist berbobot tier membership (Infinity > Moon > Sun > Reguler) dan riwayat belanja.
- [x] Notifikasi penawaran kursi kosong menyertakan token reservasi berbatas waktu kedaluwarsa 15 menit.
- [x] Jika token tidak diklaim dalam 15 menit, status beralih ke `EXPIRED` dan penawaran dialihkan ke peminat berikutnya.

### 116.5 Rekonsiliasi Finansial & Pajak Hiburan Sekunder
- [x] Setiap transaksi resale membukukan:
  - Debit kas pembeli (`ven:cashless_clearing:IDR`)
  - Credit hutang kas penjual (`ven:resale_seller_payable:IDR`)
  - Credit pendapatan platform fee (`ven:secondary_market_fee_revenue:IDR`)
  - Credit kewajiban pajak hiburan PBJT sekunder (`ven:entertainment_tax_payable:IDR`)
  - Credit royalti promotor (`ven:promoter_resale_royalty_payable:IDR`)
- [x] Konsistensi double-entry zero-sum dipastikan $\sum \text{Debit} = \sum \text{Credit}$.

---

## 3. Komitmen Ambang Keras W8

- **B1 (Logika):** $\ge 6.000$ baris PHP bersih tanpa isian.
- **B2 (UI Blade):** $\ge 35\% \times B1$ ($\ge 2.100$ baris) memenuhi seluruh standar P-UI 1–10.
- **B3 (Dokumentasi):** $\ge 1.500$ baris teknis tulisan tangan di `docs/`.
- **Quality Gate:** 0 BARU, 0 BASI pada `arch:scan`.

