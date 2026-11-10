# Definition of Ready (DoR) — Fase 54

**Fase**: Fase 54 — Tata Kelola Korporat, Konsolidasi Multi-Entitas & Keuangan Konglomerasi  
**Modul**: `EnterpriseFinance` (prefiks tabel `ef_`)  
**Base Commit**: `3c6630da9761922759e661649d2dd8838ee5d0a6`  
**Branch**: `feature/w8-p5-treasury-grup-3`  
**Ambang B1**: $\ge 7.000$ baris (Target: $\ge 7.350$, margin $\ge +5\%$)  
**Ambang B3**: $\ge 1.500$ baris (Target: $\ge 1.650$, margin $\ge +10\%$)  
**Ambang B2**: 12 view Blade, 0 pelanggaran `ui:lint`  
**Plafon Pengujian**: $\le 5$ Acceptance Tests + 3 Vektor Algoritma Terpisah (D7, D8, D9)

---

## 1. Lingkup Pekerjaan (Scope of Work)

### A. Sub-Item Wajib PROGRESS (54.1 s/d 54.9)
1. **54.1 Konsolidasi Trial Balance Multi-Entitas**:
   - Agregasi neraca saldo multi-entitas anak dan subholding.
   - Eliminasi saldo timbal balik antar-entitas dan penyesuaian PSAK/IFRS.
   - Laporan konsolidasi berjenjang (entitas anak $\to$ subholding $\to$ holding).
2. **54.2 Guardrail Anggaran Departemen & Plafon Belanja**:
   - Siklus anggaran multi-tahun (CAPEX/OPEX).
   - Penguncian anggaran bertingkat: DRAFT $\to$ SUBMITTED $\to$ REVIEWED $\to$ APPROVED $\to$ ACTIVE $\to$ REVISED $\to$ FROZEN $\to$ CLOSED.
   - Enforce hard-stop & soft-stop dengan encumbrance tracking.
3. **54.3 Monitoring Covenant Utang Sindikasi & Rasio Keuangan**:
   - Pelacakan fasilitas pinjaman sindikasi bank dan obligasi korporat.
   - Kalkulasi metrik kepatuhan kuantitatif: DSCR, DER, ISCR, Current Ratio, Minimum Liquidity.
   - Deteksi dini pelanggaran (*covenant breach warning*) dan eskalasi ke CFO.
4. **54.4 Treasury Korporat Grup & Liquidity Forecast**:
   - Peramalan arus kas konsolidasi 13-minggu grup.
   - Optimasi saldo kas minimum per anak perusahaan dan sweep likuiditas holding.
5. **54.5 Manajemen Modal Kerja Grup (Working Capital Optimization)**:
   - Metrik modal kerja grup: DSO (Days Sales Outstanding), DPO (Days Payable Outstanding), DIO (Days Inventory Outstanding), CCC (Cash Conversion Cycle).
   - Target efisiensi modal kerja dan alokasi plafon kas operasional.
6. **54.6 Tata Kelola Konglomerasi & SoD Matrix**:
   - Matriks pemisahan tugas (Segregation of Duties) pencegah konflik kepentingan (*conflict of interest*).
   - Delegasi wewenang (*Delegation of Authority / DoA*) berdasarkan batas nominal belanja.
   - Approval multi-tier berdasar matriks risiko.
7. **54.7 Kalender Kepatuhan Regulasi & Reporting Deadlines**:
   - Manajemen tenggat waktu regulasi pajak (SPT, PPN, PPh), OJK, Bank Indonesia, dan Kementerian Keuangan.
   - Status tracking dan kalkulasi otomatis penalti bunga/denda keterlambatan.
8. **54.8 Pelaporan Eksekutif C-Level & Board**:
   - Dashboard ringkasan eksekutif kesehatan keuangan konglomerasi.
   - Analisis varians anggaran vs realisasi berbasis permil.
   - 8 Generator laporan CSV resmi dengan SHA-256 integrity checksum.
9. **54.9 Audit Terpadu Dua Sumber (Dual-Source Audit)**:
   - Command terjadwal `enterprise:audit` yang merekonsiliasi seluruh data modul `ef_` terhadap saldo transaksi di `Modules\Banking\Domain\Models\LedgerTransaction`.

---

## 2. Tiga Algoritma Bersertifikat Baru
- **D7: SyndicatedLoanCovenantCalculator**:
  - Menghitung rasio keuangan Debt Service Coverage Ratio (DSCR), Debt-to-Equity Ratio (DER), Interest Service Coverage Ratio (ISCR), dan Current Ratio menggunakan aritmetika rasional integer permille ($1.000 = 100\%$).
  - Menghasilkan sertifikat SHA-256 yang memverifikasi kepatuhan rasio terhadap batas ambang perjanjian kredit sindikasi.
- **D8: ConsolidatedTrialBalanceAggregator**:
  - Menggabungkan neraca saldo seluruh anak perusahaan, mencocokkan eliminasi saldo resiprokal, dan memvalidasi bahwa total debit sama dengan total kredit ($\Sigma \text{Debit} - \Sigma \text{Kredit} = 0$).
  - Menghasilkan sertifikat SHA-256 verifikasi zero-sum balance.
- **D9: BudgetVarianceAnalyzer**:
  - Menghitung varians pengeluaran aktual terhadap pagu anggaran dalam basis points / permille, mengidentifikasi overspend atau underspend, serta mendeteksi deviasi kritis yang memerlukan eskalasi darurat.
  - Menghasilkan sertifikat SHA-256 verifikasi analisis varians.

---

## 3. Tata Kelola Ledger & Anti-Float (A3)
- Tidak ada floating point IEEE 754 (`float`/`double`). Seluruh angka moneter memakai `int` (sen/minor unit) dan rasio memakai integer basis points (`bps`) atau permille.
- Seluruh mutasi moneter diposting secara single-sign balance (Debit negative, Kredit positive, netto nol) ke `Modules\Banking\Application\Services\LedgerService`.
- Akun terdaftar di `EnterpriseFinanceAccounts` dan diseed melalui `LedgerAccountsSeeder`.
