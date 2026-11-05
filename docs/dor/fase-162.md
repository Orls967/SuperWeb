# Definition of Ready — Fase 162 (Lini 19: Sukuk, Ijarah & Wealth Syariah)

#### DoR Fase 162
- **Disetujui pemilik:** instruksi eksplisit pemilik pada prompt [G7-P2] (2026-11-06) — lingkup sub-item 162.1–162.5, daftar perluasan wajib, vektor algoritma B1–B4, kontrak G7-K5, dan keputusan G7-D5..D8 ditetapkan di prompt.
- **Tujuan bisnis:** implementasi lengkap Sukuk Ijarah terikat aset riil dengan SPV simulasi dan tokenisasi RWA (G7-K5) serta distribusi ujrah periodik (LargestRemainder); akad Ijarah dan IMBT dengan jadwal sewa, penyusutan garis lurus, eksekusi opsi jual/hibah, dan ta'zir; Wealth Syariah dengan katalog instrumen, screening kepatuhan syariah AAOIFI/OJK (B4), robo-advisor syariah bebas instrumen non-halal, dan purifikasi dividen otomatis; Zakat Engine mal per haul (B2) dengan sertifikat gapless dan distribusi 8 asnaf (B3); serta Wakaf uang (mudharabah, pokok abadi) dan wakaf aset dengan sertifikat wakif gapless.
- **Modul pemilik & prefiks:** `Syariah` / `syb_` (KONSEP §A1 baris 107). Desain & bukti: `docs/design/fase-162.md`.
- **Prasyarat:** Fase 161 🔵 (modul Syariah dasar, murabahah, ledger `SyariahAccounts`), Rwa (`RwaTokenService`, `VotingWeightReader`) ada.
- **Item dikerjakan:** 162.1–162.5 + DAFTAR PERLUASAN WAJIB (kalkulator zakat publik/nasabah, laporan amil per asnaf CSV, portofolio wealth syariah per nasabah, papan sukuk outstanding, riwayat versi parameter syariah). **Ditunda:** tidak ada.
- **Entitas/tabel + state machine:**
  - `syb_parameters`: parameter syariah berversi (label S1, nisab emas, ujrah, ratio screening).
  - `syb_sukuk_facilities`: state machine 8 status (`structuring` -> `dps_review` -> `approved` -> `issued` -> `distributing` -> `matured` -> `redeemed` | `defaulted`).
  - `syb_ijarah_contracts`: state machine 8 status (`draft` -> `dps_review` -> `approved` -> `active` -> `option_exercised` -> `ownership_transferred` | `defaulted` | `terminated`).
  - `syb_wealth_instruments`, `syb_wealth_portfolios`, `syb_wealth_orders`.
  - `syb_zakat_assessments`, `syb_zakat_distributions`.
  - `syb_waqf_principals`, `syb_waqf_distributions`.
  - `syb_gapless_certificates`: sertifikat gapless via `DocumentNumberingInterface`.
- **Posting ledger:** tabel posting D162-6 (ujrah ijarah ditagih, penyusutan aset ijarah, distribusi sukuk, zakat dipungut/disalurkan, purifikasi dividen, wakaf uang diterima). Semua akun non-bunga terdaftar di `SyariahAccounts`.
- **Event:** `syb.sukuk.issued.v1`, `syb.ijarah.activated.v1`, `syb.zakat.collected.v1`, `syb.waqf.received.v1`.
- **Role & rute:** `syariah_officer`, `shariah_board`, `wealth_advisor`, `customer`, `amil`, `nazhir`; rute `/syariah/sukuk`, `/syariah/ijarah`, `/syariah/wealth`, `/syariah/zakat`, `/syariah/waqf`.
- **Audit dua-sumber:** `syb:audit` diperluas + `SybSukukWealthCorruptionFixture` di app/Quality/Audit/Fixtures.
- **Tingkat simulasi:** S1 (parameter berversi syb_parameters, label S1, fixture ditandai). **Tier skala:** T1 (5.000 nasabah wealth, 20 sukuk, 2.000 akad ijarah, 30.000 muzakki).
- **Kontrak lintas modul:** G7-K5 `Modules\Rwa\Contracts\SukukTokenRegistry` (dibuat di Rwa), `VotingWeightReader` (dipakai).
- **Keputusan D-x:** G7-D5, G7-D6, G7-D7, G7-D8, D162-1..D162-7 (DECISIONS).

