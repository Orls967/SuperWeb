<?php

namespace Modules\Hospital\Application\Services;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Hospital\Domain\Models\ClinicalTrial;
use Modules\Hospital\Domain\Models\DataVaultAccess;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\TrialSubject;
use RuntimeException;

class ClinicalTrialAndResearchService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 106.2 Enroll Subject with Deterministic Block-Randomization Seed
     */
    public function enrollSubject(
        ClinicalTrial $trial,
        Patient $patient,
        string $informedConsentHash,
        int $seed = 42
    ): TrialSubject {
        // Validation 106.6 (a): subjek ganda dalam 1 studi ditolak
        $existing = TrialSubject::where('trial_id', $trial->id)
            ->where('patient_id', $patient->id)
            ->first();

        if ($existing) {
            throw new RuntimeException("Subject enrollment rejected: Patient MRN {$patient->mrn} already enrolled in trial {$trial->trial_code}");
        }

        // Deterministic pseudo-random allocation based on seed and patient id
        $hashVal = crc32("{$seed}-{$trial->trial_code}-{$patient->id}");
        $arm = ($hashVal % 2 === 0) ? 'ARM_A_ACTIVE' : 'ARM_B_PLACEBO';

        $subjectCode = 'SUBJ-'.strtoupper(bin2hex(random_bytes(5)));
        $anonymizedHash = hash('sha256', "TRIAL-ANON-{$patient->mrn}-{$trial->trial_code}");

        return TrialSubject::create([
            'subject_code' => $subjectCode,
            'trial_id' => $trial->id,
            'patient_id' => $patient->id,
            'anonymized_hash' => $anonymizedHash,
            'informed_consent_hash' => $informedConsentHash,
            'randomized_arm' => $arm,
            'status' => 'ENROLLED',
        ]);
    }

    /**
     * 106.4 Milestone billing to trial sponsor
     */
    public function billSponsorMilestone(
        ClinicalTrial $trial,
        string $milestoneName,
        int $amountIdr
    ): void {
        $this->ledgerService->post(new PostingDTO(
            type: 'CLINICAL_TRIAL_MILESTONE',
            description: "Sponsor billing milestone: {$milestoneName} for trial {$trial->trial_code}",
            idempotencyKey: "HSP-TRL-{$trial->trial_code}-".strtoupper(str_replace(' ', '_', $milestoneName)),
            entries: [
                PostingEntryDTO::forCode('hsp:trial_sponsor_receivable:IDR', 'IDR', $amountIdr),
                PostingEntryDTO::forCode('hsp:trial_research_revenue:IDR', 'IDR', -$amountIdr),
            ],
            referenceType: 'CLINICAL_TRIAL',
            referenceId: (string) $trial->id,
        ));
    }

    /**
     * 106.3 Request & Authorize Data Vault Access
     */
    public function requestVaultAccess(
        ClinicalTrial $trial,
        string $researcherId,
        string $purpose
    ): DataVaultAccess {
        return DataVaultAccess::create([
            'request_code' => 'DVA-'.strtoupper(bin2hex(random_bytes(5))),
            'trial_id' => $trial->id,
            'researcher_id' => $researcherId,
            'purpose' => $purpose,
            'approved_by_irb' => false,
            'status' => 'PENDING',
        ]);
    }

    /**
     * 106.3 & 106.6 (c) Approve and Unlock Data Vault Access
     */
    public function approveVaultAccess(DataVaultAccess $access, string $irbApprovalHash): DataVaultAccess
    {
        if (empty(trim($irbApprovalHash))) {
            throw new RuntimeException('Data vault approval failed: Missing IRB committee cryptographic approval hash');
        }

        $access->update([
            'approved_by_irb' => true,
            'irb_approval_hash' => $irbApprovalHash,
            'status' => 'APPROVED',
        ]);

        return $access;
    }

    /**
     * Query data vault: throws if not approved
     */
    public function queryVault(DataVaultAccess $access): array
    {
        if (! $access->approved_by_irb || $access->status !== 'APPROVED') {
            throw new RuntimeException('Access denied: Unauthorized attempt to access encrypted genomic/clinical research data vault without IRB approval');
        }

        return [
            'status' => 'SUCCESS',
            'trial_code' => $access->trial->trial_code,
            'records_accessible' => $access->trial->subjects()->count(),
        ];
    }
}
