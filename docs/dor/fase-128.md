# Definition of Ready (DoR) — Fase 128: Carbon Trading, REC & ESG Marketplace (Lini 13)

## 1. Identifikasi Fase
- **Fase:** 128
- **Lini Bisnis:** 13 (Energi & Utilitas)
- **Modul Pemilik:** `Egy` (`egy_`)
- **Prasyarat:** Fase 126 ✅, Fase 127 ✅, Kontrak Ledger Perbankan ✅, RBAC Core ✅.

## 2. Kriteria Penerimaan (Acceptance Criteria)
1. **Sub-Item 128.1 — Carbon Exchange Internal**:
   - Orderbook pencocokan perdagangan kredit karbon internal 17 lini (price-time priority).
   - Pengelompokan vintage year, pencegahan penggunaan kredit kedaluwarsa.
   - Settlement otomatis ke buku besar Banking (`Modules\Banking\Contracts\LedgerTransaction`) saat order cocok.
2. **Sub-Item 128.2 — Renewable Energy Certificate (REC)**:
   - Pencetakan (minting) unit REC unik per batch pembangkitan energi terbarukan (Solar Farm, Biogas).
   - Penjaminan keunikan seri batch generator MWh (zero duplicate minting).
   - Siklus hidup status REC: `minted` -> `listed` -> `transferred` -> `retired` (hangus seketika pasca klaim green lease/tenant).
3. **Sub-Item 128.3 — CBAM & Carbon Border Connector**:
   - Kalkulasi emisi tertanam (embedded emissions) kontainer ekspor berbasis tonase produk dan intensitas emisi.
   - Konversi tarif kuota CBAM Uni Eropa ke liabilitas IDR tanpa float.
   - Integrasi verifikasi pabean ekspor via kontrak lintas modul G7-K10.
4. **Sub-Item 128.4 — ESG Marketplace**:
   - Katalog terpadu instrumen ESG grup: kredit karbon, paket netralitas emisi hotel, sertifikat REC tenant mall, dan tonase bahan daur ulang WTE.
   - Pembelian langsung memotong kuota inventori terverifikasi dan menerbitkan sertifikat dekarbonisasi.
5. **Sub-Item 128.5 — Algoritma Bersertifikat Zero-Float**:
   - `CarbonOrderbookMatching` (E9): engine pencocokan harga-waktu deterministik berakar integer basis points.
   - `CbamEmissionsTariff` (E10): kalkulator liabilitas karbon perbatasan kontainer ekspor.
   - `RecLifecycleTracker` (E11): verifikator siklus hidup sertifikat energi terbarukan.
6. **Sub-Item 128.6 — Tests & Audit**:
   - Acceptance tests mencakup orderbook price-time, REC retirement, CBAM liability calculation, ESG marketplace purchase, dan audit rekonsiliasi `egy:audit` = 0 selisih.

## 3. Ambang Batas Kode & Kualitas
- Kode produksi bersih baru: **≥ 4.000 baris** (tanpa isian/filler).
- Architecture scan: **0 pelanggaran baru, 0 basi**.
- Nilai uang dan kuantitas emisi/energi: **Zero Float (integer kg, Wh, minor IDR)**.
