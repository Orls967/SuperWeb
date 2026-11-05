# Design Specification — P5.3 Lini 18: Asuransi & Reasuransi Penuh (Fase 156)

## 1. Domain Entities & Database Migrations
- Skema di `modules/Insurance/database/migrations/2026_11_05_156000_create_insurance_full_core_tables.php`:
  - `ins_products_penuh`: (id, product_code unique, name, line_category, base_rate_bps, min_premium_idr, deductible_bps, quota_share_pct, is_active, timestamps).
  - `ins_policies_penuh`: (id, policy_number unique, product_id FK, customer_party_ref index, sum_insured_idr, gross_premium_idr, ceded_premium_idr, retained_premium_idr, starts_at, expires_at, status, timestamps).
  - `ins_premium_schedules`: (id, policy_id FK, installment_number, due_date, amount_idr, payment_status, paid_at, timestamps).
  - `ins_claims_penuh`: (id, claim_number unique, policy_id FK, incident_date, description, claimed_amount_idr, approved_amount_idr, subrogation_recovered_idr, reinsurance_recovered_idr, net_payout_idr, status, approved_by_user_ref, timestamps).
  - `ins_actuarial_triangles`: (id, line_category, accident_year, development_year, cumulative_paid_idr, timestamps).

## 2. Shared Algorithm: ChainLadder
- `Modules\Shared\Domain\Algorithms\Actuarial\ChainLadder`:
  - Input: Matriks segitiga klaim kumulatif $C_{i,j}$ berdimensi $n \times n$.
  - Menghitung rasio faktor pengembangan tertimbang:
    $$f_j = \frac{\sum_{i=1}^{n-j} C_{i, j+1}}{\sum_{i=1}^{n-j} C_{i, j}}$$
  - Proyeksi klaim akhir (Ultimate Loss):
    $$\hat{C}_{i, n} = C_{i, n-i+1} \times \prod_{j=n-i+1}^{n-1} f_j$$
  - IBNR Reserve per Accident Year:
    $$\text{IBNR}_i = \hat{C}_{i, n} - C_{i, n-i+1}$$
  - Seluruh perhitungan dibulatkan ke integer rupiah secara konsisten dan deterministik.

## 3. Underwriting & Reinsurance Service
- `Modules\Insurance\Application\Services\FullInsuranceService`:
  - `quoteAndBindPolicy(array $data): InsurancePolicyPenuh`: Menghitung premi bruto deterministik = $\lfloor \text{sumInsured} \times (\text{baseRate} \times \text{riskFactor}) / 10000 \rfloor$, membagi ceded premium (40%) dan retained (60%), menerbitkan jadwal premi, dan membukukan piutang premi ke ledger.
  - `processClaim(array $data): InsuranceClaimPenuh`: Four-eyes approval untuk klaim $> 50.000.000$ IDR, proteksi klaim duplikat, kalkulasi recovery subrogasi & treaty reasuransi, dan posting jurnal ledger penyelesaian klaim.
  - `calculateUnearnedPremiumReserve(int $policyId): int`: Menghitung UPR harian eksak berbasis pro-rata temporis hari tersisa.
  - `estimateIbnrReserves(string $lineCategory): array`: Memakai algoritma `ChainLadder` untuk estimasi cadangan klaim masa depan.

## 4. Ledger Chart of Accounts
- `ins:unearned_premium:IDR`: Liabilitas premi belum dihasilkan (UPR).
- `ins:earned_premium_revenue:IDR`: Pendapatan premi asuransi bersih.
- `ins:claim_expense:IDR`: Beban klaim asuransi netto.
- `ins:claim_clearing:IDR`: Akun kliring pembayaran klaim.
- `reins:ceded_premium_expense:IDR`: Beban premi reasuransi diserahkan.
- `reins:claims_recoverable:IDR`: Piutang klaim ke reasuradur.
- `ar:ins:premium:IDR`: Piutang premi asuransi.

## 5. Console & Audit Integrity
- Memperluas `ins:audit` (`AuditInsuranceCommand`) untuk memvalidasi:
  1. Validasi premi polis terhadap rate produk & jadwal pembayaran.
  2. Ketiadaan klaim duplikat atas insiden yang sama.
  3. Kecukupan cadangan aktuaria IBNR $\ge 0$ dan rekonsiliasi saldo mutasi ledger.

