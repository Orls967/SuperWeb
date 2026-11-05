# Definition of Ready (DoR) — P5.3 Lini 18: Asuransi & Reasuransi Penuh (Fase 156)

## 1. Konteks & Tujuan
Mengimplementasikan Lini 18 Asuransi & Reasuransi Penuh (Fase 156) secara vertical slice mandiri di modul `Insurance` (`ins_`). Menggantikan logika statis lama di `Integration/Application/Services/FullInsuranceService.php` dengan implementasi domain-driven berstandar enterprise yang mematuhi:
- Aktuaria Chain-Ladder berbasis loss development triangle (IBNR reserve estimation) di `Modules\Shared\Domain\Algorithms\Actuarial\ChainLadder`.
- Mesin Underwriting deterministik berbasis faktor risiko multi-lini (Kendaraan, Properti, Kargo Laut, Kesehatan, Jiwa, Tanggung Gugat/Liability, Indeks Cuaca).
- Perjanjian Reasuransi Treaty Quota Share (misal 40% ceded share default) dengan pencatatan premi dan pemulihan klaim (recoveries).
- Pengakuan premi belum dihasilkan harian (UPR pro-rata temporis) berdasar formula eksak.
- Double-entry ledger komprehensif mematuhi U-3 & A3 (uang integer IDR).
- Audit dua-sumber `ins:audit` mencakup rekonsiliasi polis, klaim, cadangan aktuaria IBNR, dan mutasi ledger.

## 2. Kriteria Penerimaan (Acceptance Criteria)
1. **Model & Skema Database (`ins_`)**:
   - `ins_products_penuh`: katalog produk asuransi (kategori, base rate bps, minimum premium, deductible bps, quota share pct).
   - `ins_policies_penuh`: polis asuransi gapless number, sum insured, gross premium, ceded premium, retained premium, start/end date, status (active, lapsed, expired, claimed).
   - `ins_premium_schedules`: jadwal penagihan premi, due date, amount, status (pending, paid, lapsed).
   - `ins_claims_penuh`: pengajuan klaim, survey adjuster, approved amount, subrogation recovery, reinsurance recovery, net payout, status.
   - `ins_actuarial_triangles`: rekaman segitiga klaim kumulatif per tahun terjadinya (accident year) dan tahun pengembangan (development year) untuk estimasi IBNR.
2. **Shared Algorithm (`ChainLadder`)**:
   - `Modules\Shared\Domain\Algorithms\Actuarial\ChainLadder`: Menghitung age-to-age development factors ($f_k$) dan proyeksi ultimate losses serta cadangan IBNR ($\text{IBNR} = \text{Ultimate} - \text{Reported}$) dengan pembulatan deterministik integer IDR.
3. **Double-Entry Ledger Integrity**:
   - Pencatatan premi bruto, premi diserahkan reasuradur (ceded premium), piutang reasuradur atas klaim (reinsurance recoverable), dan klaim netto.
   - Menggunakan akun ledger resmi `ins:*` dan `reins:*`.
4. **Keamanan, RBAC & Anti-IDOR**:
   - Rute dilindungi middleware peran (`underwriter`, `actuary`, `claims_adjuster`, `reinsurance_mgr`, `admin`).
   - Pemeriksaan IDOR pada polis dan data klaim nasabah.
5. **Audit Dua Sumber & Seeder**:
   - `php artisan ins:audit` memverifikasi konsistensi saldo buku besar, cadangan kecukupan modal IBNR $\ge$ liabilitas klaim, dan ketiadaan klaim duplikat. Fixture sabotase `InsAuditCorruptionFixture` diperbarui/diuji untuk membuktikan exit status nonzero saat terjadi korupsi data.
   - Seeder skala T1 (`InsuranceT1Seeder`): 5.000 polis dan 500 klaim ter-seed dalam waktu singkat (<3 detik).

