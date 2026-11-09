<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Pipeline;

use Modules\Platform\Domain\Certificates\CanaryConfidenceCertificateVerifier;
use Modules\Platform\Domain\Certificates\ChangeRiskCertificateVerifier;
use Modules\Platform\Domain\Certificates\MigrationSafetyCertificateVerifier;
use Modules\Platform\Domain\Models\ChangeDeployment;

final class DeploymentRolloutAuditor
{
    public function __construct(
        private readonly ChangeRiskCertificateVerifier $riskVerifier,
        private readonly MigrationSafetyCertificateVerifier $safetyVerifier,
        private readonly CanaryConfidenceCertificateVerifier $canaryVerifier,
    ) {}

    /**
     * @return array{
     *     audit_passed: bool,
     *     checks: array<string, bool>,
     *     findings: list<string>
     * }
     */
    public function auditDeploymentGateReadiness(ChangeDeployment $deployment): array
    {
        $findings = [];
        $checks = [
            'change_request_linked' => $deployment->change_request_id !== null,
            'environment_valid' => in_array($deployment->environment, ['staging', 'production', 'sandbox', 'test'], true),
            'risk_certificate_valid' => false,
            'canary_confidence_passed' => false,
        ];

        $cr = $deployment->changeRequest;
        if ($cr === null) {
            $findings[] = 'Deployment does not have an associated Change Request record';
        } else {
            $certPayload = $cr->metadata['risk_certificate'] ?? [];
            if (! empty($certPayload) && isset($certPayload['certificate_hash'])) {
                $checks['risk_certificate_valid'] = $this->riskVerifier->verifySignature(
                    $cr,
                    (string) $certPayload['certificate_hash']
                );
                if (! $checks['risk_certificate_valid']) {
                    $findings[] = 'Change risk certificate verification failed (tampered signature)';
                }
            } else {
                $findings[] = 'Change request lacks formal risk assessment certificate';
            }
        }

        $canaryObservationsCount = $deployment->canaryObservations()->count();
        if ($canaryObservationsCount > 0) {
            $checks['canary_confidence_passed'] = true;
        } else {
            $findings[] = 'Zero canary observations recorded prior to rollout gate check';
        }

        $auditPassed = ! in_array(false, $checks, true);

        return [
            'audit_passed' => $auditPassed,
            'checks' => $checks,
            'findings' => $findings,
        ];
    }
}
