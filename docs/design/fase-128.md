# Desain Arsitektur — Fase 128: Carbon Trading, REC & ESG Marketplace (Lini 13)

## 1. Ikhtisar Arsitektur
Fase 128 melengkapi ekosistem dekarbonisasi dan komersialisasi atribut lingkungan untuk konglomerasi 17 lini bisnis, menghubungkan produksi energi bersih (Solar Farm Fase 126, Biogas WTE Fase 127) dengan kebutuhan kepatuhan iklim, ekspor manufaktur lintas negara, dan program apresiasi keberlanjutan pelanggan ritel/properti.

## 2. Struktur Tabel & Skema Database
Migrasi: `modules/Egy/database/migrations/2026_11_06_128200_expand_egy_carbon_rec_and_esg_tables.php`
1. `egy_carbon_projects`: Proyek kredit karbon (VCS/Gold Standard/SRN-PPI).
2. `egy_carbon_credit_batches`: Batch kredit tersertifikasi menurut tahun vintage.
3. `egy_carbon_orders`: Buku pesanan beli/jual kredit karbon internal konglomerat.
4. `egy_carbon_trades`: Eksekusi pencocokan transaksi perdagangan karbon.
5. `egy_rec_certificates`: Sertifikat atribut energi terbarukan (I-REC compliance).
6. `egy_cbam_declarations`: Deklarasi emisi tertanam kontainer ekspor Uni Eropa.
7. `egy_esg_marketplace_listings`: Penawaran instrumen keberlanjutan terbuka.
8. `egy_esg_marketplace_orders`: Pembelian instrumen ESG dan penerbitan sertifikat klaim.

## 3. Algoritma Bersertifikat Kriptografis (Zero-Float)
1. **E9: `CarbonOrderbookMatching`** (`Modules\Shared\Domain\Algorithms\Utility\CarbonOrderbookMatching.php`):
   - Engine pencocokan limit order dua arah (Buy/Sell) dengan prioritas harga terbaik dan waktu kedatangan pertama (Price-Time Priority).
   - Eksekusi deterministik parsial atau penuh dengan output hash SHA-256 kanonikal.
2. **E10: `CbamEmissionsTariff`** (`Modules\Shared\Domain\Algorithms\Utility\CbamEmissionsTariff.php`):
   - Perhitungan beban penyesuaian batas karbon (EU CBAM) = Tonase Produk x Faktor Intensitas Emisi Bersih (Spesifik - Benchmark) x Nilai Tukar Kuota Emisi EUR/IDR.
3. **E11: `RecLifecycleTracker`** (`Modules\Shared\Domain\Algorithms\Utility\RecLifecycleTracker.php`):
   - Validasi keabsahan transisi status sertifikat REC: `minted` -> `listed` -> `transferred` -> `retired`.
   - Pencegahan double-claiming / double-counting sertifikat energi terbarukan.

## 4. Kontrak Lintas Modul (G7-K10)
- `Modules\Esg\Contracts\CarbonCreditRetirementRecorder`:
  - Kontrak antarmuka ke modul ESG untuk pencatatan resmi pensiunnya kredit karbon / REC dan pengkreditan ke saldo emisi Scope 1, 2, atau 3 entitas bisnis.

## 5. State Machines
1. `CarbonOrderStateMachine`: `open` -> `partially_filled` -> `filled`, `cancelled`, `expired`.
2. `RecCertificateStateMachine`: `minted` -> `listed` -> `transferred` -> `retired`.

## 6. Rute dan Peran RBAC
- `/energy/carbon`: Perdagangan Karbon Internal (`carbon_trader`, `admin`).
- `/energy/rec`: Manajemen & Pensiun Sertifikat REC (`renewable_dev`, `admin`).
- `/energy/cbam`: Kepatuhan Ekspor & Pajak Karbon CBAM (`export_officer`, `admin`).
- `/energy/esg-marketplace`: Marketplace Instrumen ESG Terpadu (`customer`, `admin`, `energy_auditor`).
