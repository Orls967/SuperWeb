<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\Carbon;
use Modules\Venue\Domain\Algorithms\Biometric\BiometricLivenessMatcherEngine;
use Modules\Venue\Domain\DTOs\BiometricScanResultDTO;
use Modules\Venue\Domain\Models\BiometricCredential;
use Modules\Venue\Domain\StateMachines\BiometricEnrolmentStateMachine;
use Modules\Venue\Domain\ValueObjects\BiometricTemplateDigest;

/**
 * Service orchestrating guest biometric onboarding, facial feature hashing,
 * and UU PDP / GDPR privacy compliance revocation.
 */
class BiometricEnrolmentWorkflowService
{
    public function __construct(
        protected BiometricLivenessMatcherEngine $livenessEngine,
        protected BiometricPrivacyVaultService $privacyVault
    ) {}

    /**
     * Complete guest enrolment from raw captured features.
     * Raw features are immediately salted, hashed, and never stored unencrypted.
     */
    public function enrolGuestBiometrics(
        int $userId,
        string $rawFacialVectorString,
        float $livenessConfidence,
        string $legalDeclarationMode = 'EXPLICIT_OPT_IN',
        string $ipAddress = '127.0.0.1'
    ): array {
        if ($legalDeclarationMode !== 'EXPLICIT_OPT_IN') {
            return [
                'success' => false,
                'status' => 'CONSENT_REJECTED',
                'message' => 'Consent for biometric processing under UU No 27/2022 (UU PDP) is required.',
                'credential_id' => null,
            ];
        }

        $livenessResult = $this->livenessEngine->verifyLiveness([
            'confidence' => $livenessConfidence,
            'micro_movement_detected' => true,
            'infrared_reflectance' => 0.88,
        ]);

        if (! $livenessResult['is_live']) {
            return [
                'success' => false,
                'status' => 'LIVENESS_FAILED',
                'message' => 'Biometric anti-spoofing challenge failed. Please face the sensor directly.',
                'credential_id' => null,
            ];
        }

        // Salt and hash the vector irreversibly
        $digest = BiometricTemplateDigest::fromVectorString($rawFacialVectorString, $userId);

        $stateMachine = BiometricEnrolmentStateMachine::initial();
        $stateMachine = $stateMachine->transition('CONSENT_GRANTED');
        $stateMachine = $stateMachine->transition('CAPTURE_SAMPLE');
        $stateMachine = $stateMachine->transition('VERIFY_LIVENESS');
        $stateMachine = $stateMachine->transition('ACTIVATE');

        $credential = BiometricCredential::updateOrCreate(
            ['user_id' => $userId],
            [
                'template_hash' => $digest->getDigestHash(),
                'status' => $stateMachine->getState(),
                'consent_given_at' => Carbon::now(),
                'revoked_at' => null,
                'privacy_consent_version' => 'UU-PDP-2022-V2',
                'last_enrolled_ip' => $ipAddress,
            ]
        );

        return [
            'success' => true,
            'status' => 'ACTIVE',
            'message' => 'Biometric face credential successfully activated.',
            'credential_id' => $credential->id,
            'template_fingerprint' => substr($digest->getDigestHash(), 0, 16).'...',
        ];
    }

    /**
     * Revoke biometric credentials immediately under GDPR Article 17 / UU PDP Right to Erasure.
     */
    public function revokeBiometricConsent(int $userId, string $revocationReason): array
    {
        $credential = BiometricCredential::where('user_id', $userId)->first();

        if (! $credential) {
            return [
                'success' => false,
                'message' => 'No active biometric credential found for user.',
            ];
        }

        $stateMachine = new BiometricEnrolmentStateMachine($credential->status);

        if ($stateMachine->canTransition('REVOKE')) {
            $stateMachine = $stateMachine->transition('REVOKE');
        }

        // Irreversibly scrub the template hash
        $scrubbedHash = hash('sha256', 'SCRUBBED-'.microtime().'-'.$userId);

        $credential->update([
            'status' => $stateMachine->getState(),
            'template_hash' => $scrubbedHash,
            'revoked_at' => Carbon::now(),
        ]);

        return [
            'success' => true,
            'status' => 'REVOKED',
            'message' => 'Biometric credential purged and revoked according to UU PDP standards.',
        ];
    }

    /**
     * Verify a live kiosk presentation against the enrolled user database.
     */
    public function verifyKioskPresentation(
        int $userId,
        string $presentedVectorString,
        float $livenessScore
    ): BiometricScanResultDTO {
        $credential = BiometricCredential::where('user_id', $userId)->first();

        if (! $credential || $credential->status !== 'ACTIVE') {
            return new BiometricScanResultDTO(
                passed: false,
                confidenceScore: 0.0,
                livenessPassed: false,
                decisionCode: 'NO_ACTIVE_CREDENTIAL',
                narrative: 'Biometric credential not enrolled or previously revoked.'
            );
        }

        $livenessCheck = $this->livenessEngine->verifyLiveness([
            'confidence' => $livenessScore,
            'micro_movement_detected' => true,
            'infrared_reflectance' => 0.85,
        ]);

        if (! $livenessCheck['is_live']) {
            return new BiometricScanResultDTO(
                passed: false,
                confidenceScore: $livenessScore,
                livenessPassed: false,
                decisionCode: 'SPOOF_ATTEMPT_DETECTED',
                narrative: 'Presentation attack detection flagged anomaly. Physical ID required.'
            );
        }

        $targetDigest = BiometricTemplateDigest::fromVectorString($presentedVectorString, $userId);
        $match = hash_equals($credential->template_hash, $targetDigest->getDigestHash());

        return new BiometricScanResultDTO(
            passed: $match,
            confidenceScore: $match ? 0.99 : 0.20,
            livenessPassed: true,
            decisionCode: $match ? 'ENTRY_GRANTED' : 'TEMPLATE_MISMATCH',
            narrative: $match ? 'Facial identity confirmed. Turnstile released.' : 'Face did not match registered template.'
        );
    }
}
