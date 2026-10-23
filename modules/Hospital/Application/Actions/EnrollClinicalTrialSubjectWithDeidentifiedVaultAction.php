<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Hospital\Domain\Models\ClinicalTrial;
use Modules\Hospital\Domain\Models\DataVaultAccess;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\TrialSubject;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class EnrollClinicalTrialSubjectWithDeidentifiedVaultAction extends BaseAction
{
    /**
     * Enroll clinical trial subject with deterministic block randomization and de-identified blind HMAC index.
     *
     * @return array{subject: TrialSubject, randomized_arm: string, anonymized_hash: string, vault_access: ?DataVaultAccess}
     */
    public function handle(
        int $trialId,
        int $patientId,
        string $informedConsentHash,
        int $seed = 42,
        ?string $vaultResearcherId = null,
        ?string $vaultPurpose = null
    ): array {
        return DB::transaction(function () use (
            $trialId,
            $patientId,
            $informedConsentHash,
            $seed,
            $vaultResearcherId,
            $vaultPurpose
        ) {
            $trial = ClinicalTrial::lockForUpdate()->findOrFail($trialId);
            $patient = Patient::lockForUpdate()->findOrFail($patientId);

            // Validation 106.6 (a): subjek ganda dalam 1 studi ditolak
            $existing = TrialSubject::where('trial_id', $trial->id)
                ->where('patient_id', $patient->id)
                ->first();

            if ($existing) {
                throw new RuntimeException("Subject enrollment rejected: Patient MRN {$patient->mrn} already enrolled in trial {$trial->trial_code}");
            }

            // Deterministic block-randomization allocation based on seed and patient ID
            $hashVal = crc32("{$seed}-{$trial->trial_code}-{$patient->id}");
            $arm = ($hashVal % 2 === 0) ? 'ARM_A_ACTIVE' : 'ARM_B_PLACEBO';

            $count = TrialSubject::where('trial_id', $trial->id)->count() + 1;
            $subjectCode = sprintf('SUBJ-%s-%04d', $trial->trial_code, $count);

            // Blind HMAC index using application secret key for genomic & clinical research vault
            $hmacKey = (string) config('app.key', 'hsp-blind-index-key-2026');
            $anonymizedHash = hash_hmac('sha256', "TRIAL-ANON-{$patient->mrn}-{$trial->trial_code}", $hmacKey);

            $subject = TrialSubject::create([
                'subject_code' => $subjectCode,
                'trial_id' => $trial->id,
                'patient_id' => $patient->id,
                'anonymized_hash' => $anonymizedHash,
                'informed_consent_hash' => $informedConsentHash,
                'randomized_arm' => $arm,
                'status' => 'ENROLLED',
            ]);

            $vaultAccess = null;
            if ($vaultResearcherId !== null && $vaultPurpose !== null) {
                $vaultAccess = DataVaultAccess::create([
                    'request_code' => 'DVA-'.strtoupper(bin2hex(random_bytes(5))),
                    'trial_id' => $trial->id,
                    'researcher_id' => $vaultResearcherId,
                    'purpose' => $vaultPurpose,
                    'approved_by_irb' => false,
                    'status' => 'PENDING',
                ]);
            }

            $this->audit(
                action: 'hospital.clinical_trial.subject_enrolled',
                auditable: $subject,
                oldValues: null,
                newValues: [
                    'trial_id' => $trial->id,
                    'trial_code' => $trial->trial_code,
                    'subject_code' => $subjectCode,
                    'randomized_arm' => $arm,
                    'anonymized_hash' => $anonymizedHash,
                ],
                correlationId: "hsp_trial_{$trial->trial_code}_{$subjectCode}",
                impactType: 'clinical_trial_enrollment'
            );

            $this->outbox(
                eventType: 'ClinicalTrialSubjectEnrolled',
                payload: [
                    'trial_id' => $trial->id,
                    'subject_id' => $subject->id,
                    'subject_code' => $subjectCode,
                    'randomized_arm' => $arm,
                ],
                idempotencyKey: hash('sha256', "hsp_enroll_{$trial->trial_code}_{$subjectCode}")
            );

            return [
                'subject' => $subject,
                'randomized_arm' => $arm,
                'anonymized_hash' => $anonymizedHash,
                'vault_access' => $vaultAccess,
            ];
        });
    }
}
