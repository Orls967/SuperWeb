# Desain Arsitektur — [G6-P4.2] Gig Bounty Internal (Fase 85)

## 1. Arsitektur Komponen
- **Modul**: `Hcm`
- **Domain Models**:
  - `Modules\Hcm\Domain\Models\Gig\GovBounty` (tabel: `hcm_bounties`)
  - `Modules\Hcm\Domain\Models\Gig\GovBountyClaim` (tabel: `hcm_bounty_claims`)
  - `Modules\Hcm\Domain\Models\Gig\GovBountyPof` (tabel: `hcm_bounty_pofs`)
- **Services**:
  - `Modules\Hcm\Application\Services\Gig\InternalGigEconomyService`: Penanganan posting bounty, claim bounty, dan approval POF dengan guardrail UU Ketenagakerjaan & geofencing.
  - `Modules\Hcm\Application\Services\Gig\GigBountyMatchingService`: Hungarian solver bipartite matching (cost = -score atau max-weight assignment) yang memetakan karyawan berpotensi ke bounty terbuka dengan sertifikat dual bounds.
- **Ledger Integration**:
  - Payout gig dijadwalkan ke payroll atau diposting langsung via transaksi ledger:
    - Debit: `expense:{unit}:gig_labor:IDR` (atau `hcm:gig_cost_expense:IDR`)
    - Credit: `hcm:salary_payable:{employee}:IDR` (atau `hcm:bounty_payout:IDR`)

## 2. Vektor Uji & Keputusan Rekayasa
- **Kredensial Edu**: Menggunakan kontrak `Modules\Edu\Contracts\CredentialVerifier` (`verify(int $employeeId, string $credentialType): bool`).
- **Hungarian Matrix**: Matriks $M \times N$ dengan bobot biaya non-negatif: $C_{i,j} = \max(\text{score}) - \text{score}_{i,j}$. Solver Hungarian menghasilkan pasangan optimal dengan sertifikat $u_i + v_j \le c_{ij}$.
- **Idempotensi**: POF approval menghasilkan ledger posting idempoten dengan kunci `GIG-PAY-{pof_code}`.
