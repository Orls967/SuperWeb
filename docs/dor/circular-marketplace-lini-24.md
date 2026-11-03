# Definition of Ready (DoR) — Fase 174: Lini 24 Waste, Recycling & Industrial Circularity Marketplace (P1.2)

#### DoR Fase 174 — Waste & Recycling Marketplace Vertical Slice
- **Disetujui pemilik:** Internal Gate G6-P1 (Keputusan G6-K1: modul Circular `cir_` disetujui)
- **Tujuan bisnis (1–2 kalimat):** Menyediakan platform marketplace B2B terpercaya untuk limbah industri dan bahan daur ulang sekunder dengan mekanisme lelang periodik harga seragam (Call Auction) bersertifikat matematis atau pembelian langsung (fixed price), pembayaran tertahan di escrow, pengiriman fisik wajib melalui Reverse Logistics P1.1 (reason `material_sekunder`), penerimaan aktual di pabrik pembeli lewat kontrak K1 Manufaktur, rekonsiliasi susut dan refund otomatis, serta penyelesaian (*settlement*) fee dan payout berimbang.
- **Kutipan Dokumen Rujukan:**
  - `docs/KONSEP.md` Lini 24 (tabel baris 1425): Waste & Recycling modul **Circular** (`cir_`), angkutan = Logistics reverse (Pilar 5), manfaat emisi = Esg, marketplace material sekunder: listing → pembelian → pengiriman reverse → penerimaan sebagai bahan baku, invarian: tonase dijual = tonase dikirim = tonase diterima.
  - `docs/KONSEP.md` §5F: Integrasi logistik sirkular, alur penerimaan dan penimbangan faktual.
  - `docs/KONSEP.md` §A1–§A4: Konvensi tabel prefiks `cir_`, double-entry balancing, mata uang integer IDR dan berat gram integer, idempotency key `{prefiks}:{aksi}:{id_bisnis}`.
  - `docs/PROGRESS.md` Fase 174 (baris 11537–11548): Deliverable 174.1–174.6.
- **Modul pemilik & prefiks:**
  - Modul Utama: `Circular` (`cir_`) — Modul baru yang disetujui lewat G6-K1.
  - Modul Terintegrasi: `Logistics` (`lgx_`) alur reverse, `Manufacturing` (`mfg_`) kontrak K1 `RecycledMaterialReceiver`, `Payment` (`pay_`) / `Banking` (`bank_`) escrow hold & ledger.
  - Modul Shared: `Modules\Shared\Domain\Algorithms\Market` (`CallAuctionClearing`, `CallAuctionClearingResult`, `CallAuctionClearingCertificateVerifier`).
- **Prasyarat & status:**
  - Trunk master hijau; P1.1 Fase 79 selesai penuh (commit `5364c0a9`).
  - Alur shipment reverse dan kontrak penerimaan K1 Manufaktur aktif dan terverifikasi.
- **Item yang dikerjakan sesi ini:**
  - D24-1: Modul Circular baru (`cir_`) berelasi FK nyata: `cir_listings`, `cir_bids`, `cir_auction_rounds`, `cir_orders`, `cir_order_lines`, `cir_settlements`.
  - D24-2: Alur transaksi 6 tahap: listing → pembelian/lelang → bayar ke escrow (hold) → pengiriman shipment reverse P1.1 → penerimaan faktual di pabrik via K1 → settlement penjual & payout.
  - D24-3: Lelang periodik harga seragam (`Market\CallAuctionClearing`): maksimalkan volume, minimalkan selisih permintaan-penawaran, midpoint half-up kelipatan Rp 10/kg; alokasi berbasis urutan harga dan timestamp, penawar marginal parsial; sertifikat matematis batas harga dan volume; idempotensi penutupan ronde ganda.
  - D24-4: Posting pembukuan double-entry: (a) hold escrow saat pesan (`wallet:user:{pembeli}` / `escrow:payment`), (b) settlement saat penerimaan faktual (`escrow:payment` / `ap:cir:seller:{party}` + `cir:fee_revenue`, fee bps default 250), (c) payout ke penjual (`ap:cir:seller:{party}` / `wallet:user:{penjual}`), (d) pengembalian dana susut timbangan (`escrow:payment` / `wallet:user:{pembeli}`).
  - D24-5: Audit dua sumber `cir:audit`: verifikasi integritas rantai tonase (terjual = dikirim = diterima), saldo escrow sama dengan nilai belum disettle, dan fee revenue konsisten terhadap basis poin.
- **Item yang ditunda (Register Minus):**
  - M-174-1: Multi-currency settlement (hanya IDR yang didukung pada slice ini).

