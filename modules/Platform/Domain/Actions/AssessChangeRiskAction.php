<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use Modules\Platform\Domain\Algorithms\ChangeRiskAssessment;
use Modules\Platform\Domain\Algorithms\ChangeRiskScorer;
use Modules\Platform\Domain\Certificates\ChangeRiskCertificateGenerator;
use Modules\Platform\Domain\Enums\ChangeStatus;
use Modules\Platform\Domain\Models\ChangeRequest;
use Modules\Platform\Domain\StateMachines\ChangeRequestStateMachine;

final class AssessChangeRiskAction
{
    public function __construct(
        private readonly ChangeRiskScorer $scorer,
        private readonly ChangeRiskCertificateGenerator $certGenerator
    ) {}

    public function execute(ChangeRequest $cr, array $diffParams, int $operatorId): ChangeRiskAssessment
    {
        $assessment = $this->scorer->calculateRisk($diffParams);

        $cr->risk_score = (int) round($assessment->score * 100);
        $cr->risk_tier = $assessment->tier->value;
        $cr->save();

        $cert = $this->certGenerator->generateCertificate($cr->reference_number, $assessment, time());
        $metadata = $cr->metadata ?? [];
        $metadata['risk_certificate'] = $cert->toArray();
        $cr->metadata = $metadata;
        $cr->save();

        $sm = new ChangeRequestStateMachine($cr);
        if ($sm->canTransitionTo(ChangeStatus::ASSESSED)) {
            $sm->transitionTo(ChangeStatus::ASSESSED, $operatorId, 'Penilaian risiko kuantitatif (A4) berhasil diterbitkan.');
        }

        return $assessment;
    }
}
