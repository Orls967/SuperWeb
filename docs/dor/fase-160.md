# Definition of Ready (DoR) — Fase 160: Takaful Window, Agri-Insurance & Insurance Ops Command Center

## 1. Rujukan Aturan Rekayasa
- **KONSEP.md §A0–§A17**:
  - §A1 Lini 18 (Asuransi & Reasuransi, prefiks tabel `ins_`, larangan menyentuh zona Syariah `modules/Syariah/`).
  - §A2 Integritas Finansial: double-entry posting mutlak seimbang ke `Modules\Banking\Contracts\LedgerInterface`.
  - §A3 Integer Minor Unit IDR: seluruh premi, kontribusi tabarru', santunan, dan klaim bertipe integer/int64 (sen).
  - §A4 Idempotensi: `Modules\Insurance\Application\Services\IdempotentRequestGuard` deterministik per entitas bisnis.
  - §A5 Registry Tipe Transaksi: `InsuranceTransactionTypes`.
  - §A6 Zero-flag parameter di Application layer.
  - §A7 Algoritma Bersertifikat A8: `Modules\Shared\Domain\Algorithms\Reinsurance\TakafulSurplus` dengan verifier HMAC-SHA256.
  - §A14 Audit dua-sumber: Artisan command `ins:takaful-audit` / `ins:command-audit` dengan corruption fixture terdaftar di `AuditCommandRegistry`.
- **Katalog Kontrak G7-K2**:
  - `Modules\Agri\Contracts\FarmerLoanOffset`: Penyelesaian klaim asuransi pertanian otomatis memotong saldo pinjaman petani plasma di modul Agri.

## 2. Ruang Lingkup & Acceptance Criteria
- **160.1 Takaful Window (Jembatan Syariah)**:
  - Pemisahan mutlak rekening dana: Dana Tabarru' (kebajikan risiko peserta) vs Dana Perusahaan (ujrah/wakalah fee).
  - Akad Wakalah bil Ujrah: potongan biaya pengelolaan fee bps.
  - Alokasi Surplus Underwriting Tabarru': dihitung via algoritma A8 `TakafulSurplus` (alokasi peserta, cadangan tabarru', dan bagi hasil mudharabah pengelola sesuai fatwa DSN-MUI).
  - Tata kelola Sharia Supervisory Board (SSB) clearance simulation tercatat audit trail.
- **160.2 Agri-Insurance Lanjutan & Parametric Weather**:
  - Polis asuransi indeks cuaca & curah hujan petani plasma (komoditas padi, kelapa sawit, jagung).
  - Integrasi kontrak `FarmerLoanOffset` ke modul Agri untuk offset cicilan pinjaman petani otomatis saat curah hujan / kekeringan melewati trigger.
  - Pelaporan loss ratio per komoditas pertanian.
- **160.3 Micro-Insurance Massal & High-Volume Autopilot**:
  - Produk mikro premi harian kecil (asuransi gadget, kecelakaan ojek online, perjalanan singkat, delayed flight).
  - Penerbitan massal cepat dengan validasi limit agregasi risiko per region.
  - Klaim autopilot instan di bawah plafon micro-claim limit.
- **160.4 Insurance Operations Command Center**:
  - C-Suite dashboard metrik eksekutif: Gross Written Premium (GWP), Net Earned Premium, Loss Ratio lintas 30 lini/produk, Reserve Development aging, Reinsurance Recoverable outstanding, dan Risk-Based Capital (RBC) Solvency ratio.
- **160.5 Regulasi, Compliance & AML Reporting**:
  - Simulasi pelaporan berkala regulator (OJK / APPARDI), log keluhan nasabah (customer grievances) SLA tracking.
  - Anti-Money Laundering (AML) suspicious premium transaction threshold monitoring (> 500jt akumulatif).
- **160.6 Tests & Verification**:
  - Maksimal 5 acceptance tests: `tests/Acceptance/InsTakaful/InsTakafulAcceptanceTest.php`.
  - Unit test algoritma A8 `TakafulSurplusTest`.
  - Ambang kode produksi ≥ 4.000 baris.

## 3. Skema Data (Migrasi `ins_tk_` & `ins_ops_`)
1. `ins_tk_funds`: Dana tabarru' & dana ujrah pengelola.
2. `ins_tk_participants`: Peserta takaful & kontribusi iuran.
3. `ins_tk_surplus_distributions`: Riwayat pembagian surplus underwriting tahunan.
4. `ins_agri_policies`: Polis asuransi pertanian berbasis indeks cuaca/NDVI.
5. `ins_agri_claims`: Klaim asuransi pertanian dengan rujukan loan offset.
6. `ins_micro_policies`: Polis asuransi mikro volume tinggi.
7. `ins_command_center_snapshots`: Snapshot metrik portofolio operasional asuransi.
8. `ins_compliance_grievances`: Tiket keluhan nasabah & log kepatuhan regulasi.

