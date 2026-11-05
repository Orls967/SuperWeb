# Desain Arsitektur & Rekayasa — Fase 160: Takaful Window, Agri-Insurance & Insurance Ops Command Center

## 1. Arsitektur Domain & Pemisahan Zona
Fase 160 menyelesaikan seluruh cakupan Lini 18 (Asuransi & Reasuransi) dengan 4 pilar fungsional:
1. **Takaful Window System**:
   - Memenuhi prinsip syariah tanpa melanggar batas worktree (kode di `modules/Insurance`, menggunakan penamaan takaful murni).
   - Segregasi dana tabarru' (mutual) vs rekening operasional perusahaan (ujrah).
   - Perhitungan surplus underwriting Tabarru' via Algoritma A8:
     $$\text{Surplus} = \text{Net Tabarru Contribution} - \text{Net Paid Claims} - \text{Technical Reserve Addition}$$
     $$\text{Participant Allocation} = \text{Surplus} \times \alpha$$
     $$\text{Reserve Retained} = \text{Surplus} \times \beta$$
     $$\text{Mudharabah Manager} = \text{Surplus} \times \gamma$$
     Di mana $\alpha + \beta + \gamma = 100\%$.
2. **Parametric Agri-Insurance & Farmer Loan Offset**:
   - Kontrak `Modules\Agri\Contracts\FarmerLoanOffset`:
     ```php
     interface FarmerLoanOffset {
         public function offsetLoanBalance(string $farmerPartyId, int $offsetAmountIdr, string $referenceClaimNumber): int;
         public function getOutstandingLoanBalance(string $farmerPartyId): int;
     }
     ```
   - Implementasi adapter di Agri module (`AgriFarmerLoanOffsetService`) dan binding di `AgriServiceProvider`.
3. **High-Volume Micro-Insurance Engine**:
   - Transaksi instan dengan idempotency guard.
   - Algoritma penetapan premi harian mikro.
4. **Insurance Command Center & Regulatory Compliance**:
   - Agregasi real-time metrik solvabilitas RBC, rasio loss ratio, klaim terbuka, dan pelaporan kepatuhan.

## 2. Struktur File
- **Algorithms**:
  - `Modules\Shared\Domain\Algorithms\Reinsurance\TakafulSurplus`
  - `Modules\Shared\Domain\Algorithms\Reinsurance\TakafulSurplusResult`
  - `Modules\Shared\Domain\Algorithms\Reinsurance\TakafulSurplusCertificateVerifier`
- **Agri Contract & Service**:
  - `Modules\Agri\Contracts\FarmerLoanOffset`
  - `Modules\Agri\Application\Services\AgriFarmerLoanOffsetService`
- **Domain Models**:
  - `Modules\Insurance\Domain\Models\TakafulFund`
  - `Modules\Insurance\Domain\Models\TakafulParticipant`
  - `Modules\Insurance\Domain\Models\TakafulSurplusDistribution`
  - `Modules\Insurance\Domain\Models\AgriInsurancePolicy`
  - `Modules\Insurance\Domain\Models\AgriInsuranceClaim`
  - `Modules\Insurance\Domain\Models\MicroInsurancePolicy`
  - `Modules\Insurance\Domain\Models\InsuranceCommandSnapshot`
  - `Modules\Insurance\Domain\Models\InsuranceGrievanceTicket`
- **Application Actions**:
  - `IssueTakafulPolicyAction`
  - `DistributeTakafulSurplusAction`
  - `IssueAgriParametricPolicyAction`
  - `SettleAgriClaimWithOffsetAction`
  - `IssueMicroPolicyAction`
  - `AggregateCommandCenterMetricsAction`
  - `SubmitRegulatoryGrievanceAction`
- **Services & Auditor**:
  - `TakafulAuditor`
  - `CommandCenterMetricsCalculator`
  - `RegulatoryComplianceService`
- **Console Command & Fixture**:
  - `AuditTakafulCommand` (`ins:takaful-audit`)
  - `TakafulAuditCorruptionFixture`
- **Controllers & Views**:
  - `TakafulController`
  - `AgriInsuranceController`
  - `MicroInsuranceController`
  - `InsuranceCommandCenterController`
  - Views: `takaful/index`, `takaful/surplus`, `agri/index`, `agri/claims`, `micro/index`, `command/dashboard`, `command/regulatory`.

