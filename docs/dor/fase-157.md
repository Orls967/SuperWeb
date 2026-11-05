# Definition of Ready — Fase 157 (Lini 18: Reasuransi, Kapital & CAT Modelling)

#### DoR Fase 157
- **Disetujui pemilik:** instruksi eksplisit pemilik pada prompt [G7-P1] (2026-11-06) — lingkup, vektor algoritma, kontrak G7-K3, dan keputusan G7-D1..D4 ditetapkan di prompt.
- **Tujuan bisnis:** polis Asuransi penuh (156) otomatis dialihkan ke program reasuransi bertreaty (QS/surplus/XoL/Cat XoL/stop loss) dengan panel reinsurer (Party), bordereaux & account current, solvabilitas RBC berkorelasi dengan peringatan dini dan aksi what-if, pemodelan CAT berseed (katalog, YLT, AEP/OEP/PML/TVaR, rencana proteksi), dan cat bond simulasi (token Rwa; kupon & trigger indemnity).
- **Modul pemilik & prefiks:** `Insurance` / `ins_` (KONSEP §A1 baris 103). Desain & bukti: `docs/design/fase-157.md`.
- **Prasyarat:** Fase 72 ✅, Fase 156 🔵 (modul Insurance, FullInsuranceService), Rwa (`VotingWeightReader`) ada.
- **Item dikerjakan:** 157.1–157.6 + DAFTAR PERLUASAN WAJIB (account current, commutation, CSV bordereaux, dashboard eksposur, what-if solvabilitas, riwayat versi parameter). **Ditunda:** tidak ada (lihat Register Minus di PROGRESS).
- **Entitas/tabel + state machine:** lihat desain; `TreatyStatus` (draft→negotiating→bound→active→run_off→commuted|cancelled, four-eyes pada bound & commuted), `CatRunStatus`, `CatBondStatus`.
- **Posting ledger:** tabel di desain (kejadian → debit/kredit); semua lewat `Posting::lines()`; key `ins:cede:{event_key}`, `ins:rein:settle:{ac}`, `ins:catbond:coupon:{bond}:{quarter}`.
- **Event:** `ins.policy.bound.v1`, `ins.claim.settled.v1`, `ins.cession.recorded.v1`, `ins.catbond.triggered.v1` (produsen & konsumen nyata, afterCommit).
- **Role & rute:** `reinsurance_mgr`, `actuary`, `cfo_view`, `investor`; prefiks `/insurance/reinsurance`, `/insurance/capital`, `/insurance/cat`, `/insurance/cat-bonds`, `/investor/cat-bonds`.
- **Audit dua-sumber:** `ins:reinsurance-audit` + fixture (lihat desain).
- **Tingkat simulasi:** S1 (parameter berversi, label S1, fixture ditandai). **Tier skala:** T1.
- **Kontrak lintas modul:** G7-K3 (dibuat), `VotingWeightReader` (dipakai).
- **Keputusan D-x:** G7-D1, G7-D2, G7-D3, D157-A..C (DECISIONS).
