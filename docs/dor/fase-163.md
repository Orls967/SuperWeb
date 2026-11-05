# Definition of Ready — Fase 163 (Lini 19: Microfinance, BMT & Pemberdayaan Ekonomi Syariah)

#### DoR Fase 163
- **Disetujui pemilik:** Instruksi eksplisit pemilik pada prompt [G7-P2] (2026-11-06) — lingkup sub-item 163.1–163.6, daftar perluasan wajib, vektor algoritma B5–B6, kontrak baru G7-K6, serta integrasi kontrak G7-K1 dan G7-K2 dari master (G7-P1 #73).
- **Tujuan bisnis:** Digitalisasi ekosistem pembiayaan mikro syariah dan BMT (Baitul Maal wat Tamwil):
  1. Skema pembiayaan mikro mudharabah/musyarakah bertingkat dengan monitoring bagi hasil berkala dan moral hazard loss separation (kerugian non-kelalaian ditanggung shohibul maal, kelalaian ditanggung mudharib).
  2. Layanan BMT koperasi syariah: simpanan wadiah yad dhamanah tanpa janji bonus, simpanan investasi mudharabah, dan pembiayaan qardh al-hasan kebajikan fakir/mustahik.
  3. Pengukuran dampak sosial (Social Impact Scoring): penyerapan tenaga kerja, rasio peningkatan pendapatan, dan pelacakan transformasi mustahik menjadi muzakki.
  4. Penjaminan dan kelayakan kredit mikro gig worker via Kontrak Antar-Modul G7-K6 (`GigPayoutHistory`) dan plafon kredit bersertifikat algoritma B6 (`GigCreditLimit`).
  5. Pembiayaan mikro pertanian (Salam / Muzaraah) terintegrasi data satelit NDVI & defisit curah hujan via Kontrak G7-K1 (`NdviIndexReader`) dan mitigasi gagal panen otomatis via Kontrak G7-K2 (`FarmerLoanOffset`).
  6. Audit mikrofinance dan verifikasi berkelanjutan `syb:audit` dengan fixture korupsi `SybMicrofinanceCorruptionFixture`.
- **Modul pemilik & prefiks:** `Syariah` / `syb_` (KONSEP §A1 baris 107).
- **Prasyarat:** Fase 162 🔵 terimplementasi, Kontrak G7-K1 & G7-K2 telah tersedia di master (`Modules\Agri\Contracts`).
- **Item dikerjakan:** 163.1–163.6 + DAFTAR PERLUASAN WAJIB (portal BMT dashboard cabang, simulasi repayment waterfall interaktif, monitoring panen tani salam terikat NDVI, pelacak transformasi mustahik->muzakki, panel eligibilitas gig worker).
- **Entitas / tabel baru:**
  - `syb_bmt_cooperatives`: registri BMT & koperasi syariah.
  - `syb_bmt_savings`: rekening simpanan wadiah yad dhamanah dan mudharabah.
  - `syb_micro_facilities`: kontrak fasilitas pembiayaan mikro syariah (mudharabah, musyarakah, murabahah mikro, qardh).
  - `syb_group_financings`: pembiayaan kelompok tanggung renteng (solidarity group financing).
  - `syb_gig_microloans`: fasilitas pembiayaan mikro terikat riwayat gig/freelance.
  - `syb_agri_micro_facilities`: fasilitas pembiayaan tani salam/muzaraah dengan parameter NDVI & cuaca.
  - `syb_social_impact_metrics`: metrik tahunan dampak sosial, penyerapan tenaga kerja, konversi mustahik -> muzakki.
  - `syb_bmt_repayments`: riwayat angsuran berjenjang (waterfall allocation).
- **Posting ledger:**
  - `syb:customer_savings_wadiah:IDR`, `syb:customer_savings_mudharabah:IDR`
  - `syb:micro_financing_receivable:IDR`, `syb:agri_financing_receivable:IDR`, `syb:qardh_receivable:IDR`
  - `syb:mudharabah_profit_share_income:IDR`, `syb:micro_ujrah_income:IDR`
  - `syb:tazir_social_fund:IDR` (dana ta'zir keterlambatan dialokasikan ke kebajikan, dilarang diakui laba bank)
- **Kontrak Antar-Modul:**
  - G7-K6: `Modules\Syariah\Contracts\GigPayoutHistory`
  - G7-K1: `Modules\Agri\Contracts\NdviIndexReader` (dipakai)
  - G7-K2: `Modules\Agri\Contracts\FarmerLoanOffset` (dipakai)
- **Algoritma Bersertifikat:**
  - B5: `RepaymentWaterfall` (`RepaymentWaterfallCertificateVerifier`)
  - B6: `GigCreditLimit` (`GigCreditLimitCertificateVerifier`)
- **Role & Rute:** `syariah_officer`, `bmt_manager`, `field_officer`, `customer`; rute `/syb/bmt`, `/syb/micro`, `/syb/social-impact`.
- **Tingkat simulasi:** S1 (parameter margin & scoring berversi di `syb_parameters`). **Tier skala:** T1 (100 kelompok tanggung renteng, 5.000 anggota mikro, 1.000 gig worker, 500 petani salam).
- **Keputusan Terkait:** `g7-d9`..`g7-d13`.

