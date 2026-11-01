# Definition of Ready (DoR): LINI 4 Keuangan Aset (Rwa → Insurance → Wealth)

## 1. Referensi Normatif & Kutipan Baris

Dokumen ini disusun berdasarkan prinsip rekayasa dan spesifikasi normatif pada:
1. `docs/KONSEP.md`:
   - **§A1** (Baris 70–108): Registry Modul & Prefiks Tabel (`rwa_`, `ins_`, `wm_`).
   - **§A2** (Baris 109–150): Ledger & Uang (Double-entry balance $\Sigma=0$, saldo normal per jenis akun, anti-pola pendapatan negatif, idempotensi key deterministik, rekonsiliasi audit dua-sumber).
   - **§A3** (Baris 151–158): State machine, approval, dokumen (Four-eyes approval: approver $\ne$ pengaju, tanpa flag boolean `$approved`).
   - **§A4** (Baris 159–188): Event & Integrasi (Komunikasi lintas modul hanya via Contract / Domain Event / Outbox).
   - **§A5** (Baris 189–196): Audit & Observabilitas (`*:audit` dua-sumber membaca tabel modul vs posting ledger).
   - **§A6** (Baris 197–204): Keamanan, RBAC & Privasi (Peran per modul via RbacService idempoten).
   - **§A7** (Baris 205–208): UI & Jalan Masuk (Blade view terdaftar di rute web).
   - **§A8** (Baris 209–220): Strategi Test (Pengujian (a) HTTP, (b) Otorisasi, (c) Idempotensi, (d) Invarian $\Sigma=0$, (e) Audit).
   - **§A10** (Baris 232–241): Spesifikasi Enabler Lintas Pilar (`ClockInterface`, `IdempotencyEngineInterface`, `OutboxInterface`, `AuditLoggerInterface`).
   - **§A13** (Baris 266–287): Larangan Teknis & Anti-Pola (X1–X25).
   - **§A15** (Baris 324–470): Template Kode Normatif (Service, Action, Controller, Model append-only, Audit command, Fixture korupsi).
   - **§2F** (Baris 582–616): Spesifikasi Implementasi Minimum (MVP Vertical Slice) Pilar 2: Keuangan & Aset Riil (Rwa, Insurance, Wealth).
   - **§3F** (Baris 649–678): Spesifikasi Pilar 3 Kuliner (referensi pembagian lini).
   - **§4F** (Baris 711–740): Spesifikasi Pilar 4 Properti & Konstruksi (referensi pembagian lini).
2. `docs/PROGRESS.md`:
   - **P2** (Baris 97–123): Format blok `Bukti:` terstruktur machine-readable.
   - **P3 (X1–X25)** (Baris 124–153): Anti-pola yang dilarang (uang float, boolean control, idempotensi acak, manipulasi jam Carbon di produksi).
   - **P8** (Baris 202–242): Kriteria Vertical Slice V1–V12.
   - **P9** (Baris 243–259): Format Register Minus wajib.

---

## 2. Bukti Kondisi Kode Lama (L4.0)

Eksplorasi kondisi kode lama telah diuji melalui suite `tests/Acceptance/FinAsset/Eksplorasi/`:
1. `RwaLegacyExplorationTest.php`:
   - `TokenizeRealWorldAssetSupplyAction`: **SEBAGIAN** (status langsung active, tanpa four-eyes approval, tanpa validasi issuer KYB, pembagian harga token float).
   - `SubscribePrimaryRwaEscrowOfferingAction`: **SEBAGIAN** (hardcode KYC verified, tanpa pencatatan ledger, FCFS bukan alokasi pro-rata Hamilton).
   - `DistributeProRataRwaRentalYieldAction`: **SALAH** (input pool manual bukan dari ledger, pembagian floor menyisakan rounding dust, tanpa mutasi dompet kas).
   - `MatchSecondaryRwaOrderbookTradeAction`: **SEBAGIAN** (saldo holding dimutasi in-place tanpa log transfer append-only, tanpa penyelesaian kas ledger).
   - Tabel hilang: `rwa_investor_verifications`, `rwa_offering_orders`, `rwa_token_transfers`.
2. `InsuranceLegacyExplorationTest.php`:
   - `IssueEmbeddedMicroInsurancePolicyAction`: **SEBAGIAN** (nomor polis non-deterministik memakai `rand()`, tanpa penomoran gapless, tanpa pengakuan unearned premium).
   - `TriggerParametricOracleAutoClaimAction`: **SEBAGIAN** (tanpa validasi registry oracle, status hardcoded pending_review tanpa evaluasi STP fraud score).
   - `SettleApprovedInsuranceClaimLedgerAction`: **KERANGKA** (berjudul LedgerAction tetapi berisi nol mutasi ledger).
   - Tabel hilang: `ins_policy_sequences`, `ins_policy_earnings`, `ins_parametric_oracles`.
3. `WealthLegacyExplorationTest.php`:
   - `ComputeInvestorRiskToleranceProfileAction`: **SEBAGIAN** (skala 0-100 ke 3 tier kualitatif, tanpa bobot pertanyaan survei, alokasi target bps, atau batas drift band).
   - `RebalancePortfolioDriftIdempotentAction`: **SEBAGIAN** (perhitungan persentase float, mutasi nilai rupiah langsung tanpa unit, harga NAV, atau optimasi lot beli B0′.8).
   - `SweepCorporateIdleCashToOvernightYieldAction`: **KERANGKA** (parameter kas manual tanpa membaca saldo riil ledger, nol entri jurnal, tanpa event consumer payroll).
   - Tabel hilang: `wm_portfolio_holdings`, `wm_nav_history`, `wm_sweep_configs`.

---

## 3. Checklist Vertical Slice V1–V12

| Kode | Kriteria | Kondisi di LINI 4 | Status DoR |
| :--- | :--- | :--- | :--- |
| **V1** | Modul pemilik & prefiks tabel jelas | `rwa_` (Rwa), `ins_` (Insurance), `wm_` (Wealth). | SIAP |
| **V2** | Tabel & migrasi lengkap | Migrasi baru untuk tabel yang hilang (`rwa_investor_verifications`, `rwa_offering_orders`, `rwa_token_transfers`, `ins_policy_sequences`, `ins_policy_earnings`, `ins_parametric_oracles`, `wm_portfolio_holdings`, `wm_nav_history`, `wm_sweep_configs`). | SIAP |
| **V3** | Chart of Accounts & arah D/C | Akun terdaftar di `RwaAccounts`, `InsuranceAccounts`, `WealthAccounts`. Saldo normal mematuhi U-3 (`allow_negative = false` kecuali clearing/escrow). | SIAP |
| **V4** | Helper ledger idempoten | Helper modul `{Module}Accounts` membuat akun secara idempoten jika belum ada. Dompet pengguna memakai `$user->walletAccount($assetCode)`. | SIAP |
| **V5** | Idempotency-Key wajib | Format deterministik `{prefix}:{action}:{id_bisnis}[:{sub}]`. Penolakan jika key absen (422) atau konflik payload. | SIAP |
| **V6** | Zero floats & integer minor-unit | IDR integer rupiah; persen = basis poin (bps); NAV integer skala 1e4; pembagian kuota Hamilton B0′.1; pembulatan kumulatif B0′.2; rebalancing lot B0′.8. | SIAP |
| **V7** | State machine eksplisit | Four-eyes approval pada penerbitan RWA (approver $\ne$ pengaju via ApprovalEngineInterface). Siklus hidup klaim asuransi terverifikasi fraud score STP. | SIAP |
| **V8** | Append-only domain facts | `rwa_token_transfers`, `ins_policy_earnings`, dan `wm_nav_history` append-only. Saldo turunan dihitung dari rekonsiliasi fakta. | SIAP |
| **V9** | Event catalog (emitted & consumed) | Event domain dipancarkan lewat outbox transactional. Wealth mengonsumsi `HcmPayrollDisbursed` dan `RwaYieldDistributed`. | SIAP |
| **V10** | RBAC roles & routes | Seeder role idempoten via `RbacService` (mis. `rwa_issuer`, `rwa_investor`, `insurance_underwriter`, `insurance_claim_officer`, `wealth_advisor`, `wealth_investor`). | SIAP |
| **V11** | Audit command dua-sumber | `rwa:audit`, `insurance:audit`, `wealth:audit` membandingkan subledger tabel modul vs posting ledger. Dilengkapi corruption fixture. | SIAP |
| **V12** | Acceptance test komprehensif | $\ge 90$ acceptance test (RWA $\ge 35$, Insurance $\ge 30$, Wealth $\ge 25$) mencakup alur (a)–(e). | SIAP |

---

## 4. Keputusan Desain & Batasan Ruang Lingkup

1. **U-1 s.d. U-5**:
   - Seluruh kalkulasi uang memakai IDR integer rupiah, basis point (bps), dan fixed-point arithmetic.
   - Idempotency key deterministik wajib pada setiap mutasi uang, stok, dan status.
   - Waktu UTC dengan batas hari lokal WIB (Asia/Jakarta) melalui `ClockInterface`.
2. **R-1 s.d. R-5 (Rwa)**:
   - Tokenisasi: Validasi aset dasar di Asset + penerbit KYB di Party + kontrak penerbitan di Contract. Approval four-eyes sebelum status `issued`.
   - Investor: Registri `rwa_investor_verifications` membaca KYC aktif via kontrak read-only `InvestorKycLookupInterface`.
   - Penawaran perdana: Hold via PaymentGateway, alokasi pro-rata Hamilton B0′.1 jika oversubscribed, capture senilai alokasi, release sisa.
   - Dividen: Diambil dari saldo akun pendapatan sumber yang sudah terposting, dibagikan via Hamilton apportionment.
   - Pasar sekunder: Orderbook matching dengan transfer log append-only + settlement ledger kas & fee platform.
3. **I-1 s.d. I-5 (Insurance)**:
   - Nomor polis: Gapless sequential locking `POL-{PRODUCT}-{YEAR}-{SEQUENCE:06d}`.
   - Pengakuan premi: D Kas / K Unearned Premium saat terbit; harian diakui ke Earned Premium Revenue via B0′.2 CumulativeRounding.
   - Klaim parametrik: OracleBMKG / IoT terdaftar memicu klaim otomatis; STP approved jika fraud score $< 3000$ bps; pending_investigation jika $\ge 3000$ bps.
   - Settlement klaim: D Beban Klaim / K Klaim Terutang, lalu disbursement K Kas / D Klaim Terutang.
4. **W-1 s.d. W-5 (Wealth)**:
   - Profil risiko: Survei tertimbang menghasilkan skor 1..5; bobot alokasi target bps dan toleransi band $\pm 500$ bps.
   - Portfolio holding: Unit-based holding pada instrumen pasar uang, obligasi, dan token RWA dengan NAV skala 1e4.
   - Cash sweep: Konsumsi event `HcmPayrollDisbursed` dan dividen RWA untuk auto-invest sweep ke portofolio.
   - Rebalancing: Memakai B0′.8 PortfolioRebalancer saat drift melebihi batas band, menjual aset overweight dan membeli lot aset underweight.
   - MTM revaluation: Harian memperbarui nilai portofolio berdasarkan feed NAV terkini.
