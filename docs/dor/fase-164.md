# Definition of Ready — Fase 164 (Lini 19: Islamic Trade Finance & Cross-Border Syariah)

#### DoR Fase 164
- **Disetujui pemilik:** Instruksi eksplisit pemilik pada prompt [G7-P2] (2026-11-06) — lingkup sub-item 164.1–164.6, daftar perluasan wajib, algoritma bersertifikat B7 (`SalamPositionBook`) dan B8 (`TawarruqFx`), sistem verifikasi qabdh lintas batas, dan ambang batas keras $\ge 4.000$ baris kode produksi substantif.
- **Tujuan bisnis:** Digitalisasi pembiayaan perdagangan internasional dan lintas batas syariah:
  1. Islamic Letter of Credit (LC) menggabungkan akad Istisna' (pembiayaan manufaktur bertahap dengan cost-to-cost percentage of completion) dan Wakalah bil Ujrah (keagenan dokumen/kepabeanan/distribusi) dengan settlement valas/stablecoin syariah.
  2. Pembiayaan komoditas Salam & Parallel Salam: pembayaran tunai penuh di muka (*muslam fiih*) kepada produsen dengan spesifikasi standar terikat kalender panen/produksi, di-hedge via parallel salam independen tanpa *safqatayn fi safqah*.
  3. Murabahah Supply Chain Finance (SCF) untuk modal kerja pengadaan bahan baku: verifikasi kepemilikan riil (*qabdh*) oleh bank sebelum akad penjualan tangguh (*bai' bi tsaman 'ajil*) kepada pembeli.
  4. Commodity Murabahah FX (Tawarruq Munazzam): konversi mata uang dan lindung nilai valas syariah bebas bunga/riba melalui transaksi bursa komoditas internasional riil dengan serial number fisik unik per batch untuk mencegah *bai' al-inah* (jual beli semu).
  5. Cross-border custody & verifikasi kepemilikan riil (*qabdh hussy / hukmy*): validasi digital Bill of Lading, Sea Waybill, Air Waybill, dan Warehouse Receipt dengan tanda tangan kriptografis SHA-256.
  6. Audit perdagangan syariah berkelanjutan via `syb:audit` dengan fixture korupsi `SybTradeFinanceCorruptionFixture`.
- **Modul pemilik & prefiks:** `Syariah` / `syb_` (KONSEP §A1 baris 107).
- **Prasyarat:** Fase 163 🔵 terimplementasi dan merged ke master.
- **Item dikerjakan:** 164.1–164.6 + DAFTAR PERLUASAN WAJIB (portal manajemen Islamic LC & tracking progress pabrikasi istisna', buku posisi komoditas salam & matching delivery, generator sertifikat tawarruq bursa komoditas, konsol validasi Bill of Lading lintas negara, kalkulator kurs tawarruq FX).
- **Entitas / tabel baru:**
  - `syb_letters_of_credit`: Islamic LC register (akad istisna' + wakalah bil ujrah).
  - `syb_istisna_contracts`: Kontrak pesanan manufaktur bertahap dengan milestone biaya aktual vs anggaran.
  - `syb_salam_contracts`: Kontrak komoditas salam (spesifikasi komoditas, kuantum, tanggal penyerahan, gudang penampung).
  - `syb_parallel_salam_hedges`: Kontrak parallel salam independen untuk matching penjualan komoditas.
  - `syb_scf_murabahah_facilities`: Fasilitas SCF murabahah pengadaan barang/bahan baku.
  - `syb_scf_invoices`: Tagihan SCF terverifikasi dokumen logistik fisik.
  - `syb_tawarruq_fx_orders`: Pesanan transaksi valas tawarruq munazzam.
  - `syb_commodity_lots`: Registri lot komoditas bursa dengan serial number fisik unik & lokasi gudang global.
  - `syb_trade_custody_documents`: Dokumen kepemilikan dan logistik (BL/AWB/WR) dengan hash verifikasi.
  - `syb_trade_settlements`: Settlement pembayaran perdagangan internasional dan fee ujrah.
- **Posting ledger:**
  - `syb:trade_lc_liability:IDR`, `syb:trade_lc_margin_deposit:IDR`
  - `syb:istisna_work_in_progress:IDR`, `syb:istisna_contract_revenue:IDR`
  - `syb:salam_financing_advance:IDR`, `syb:salam_inventory:IDR`, `syb:salam_hedging_reserve:IDR`
  - `syb:scf_murabahah_receivable:IDR`, `syb:scf_inventory:IDR`, `syb:scf_margin_income:IDR`
  - `syb:tawarruq_broker_payable:IDR`, `syb:tawarruq_commodity_holding:IDR`, `syb:tawarruq_fx_clearing:IDR`
  - `syb:trade_ujrah_fee_income:IDR`
- **Algoritma Bersertifikat:**
  - B7: `SalamPositionBook` (`SalamPositionBookCertificateVerifier`)
  - B8: `TawarruqFx` (`TawarruqFxCertificateVerifier`)
- **Role & Rute:** `syariah_officer`, `trade_officer`, `customs_agent`, `importer_party`, `exporter_party`; rute `/syb/trade/lc`, `/syb/trade/salam`, `/syb/trade/scf`, `/syb/trade/tawarruq`, `/syb/trade/custody`.
- **Tingkat simulasi:** S1 (kurs acuan komoditas & tarif ujrah berversi di `syb_parameters`). **Tier skala:** T1 (200 Islamic LC aktif, 500 kontrak salam tani/tambang, 1.000 transaksi supply chain murabahah, 300 tawarruq FX hedge).
- **Keputusan Terkait:** `g7-d14`..`g7-d18`.

