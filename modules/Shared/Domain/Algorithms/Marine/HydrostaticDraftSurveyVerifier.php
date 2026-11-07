<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Marine;

final class HydrostaticDraftSurveyVerifier
{
    private const SECRET = 'hydrostatic-draft-survey-reconciler-key-2026';

    public static function sign(
        float $initDisp,
        float $finalDisp,
        float $netCargoTon,
        float $weighbridgeTon,
        float $variancePct
    ): string {
        $payload = sprintf(
            'init:%.2f|fin:%.2f|cargo:%.2f|wb:%.2f|var:%.3f',
            $initDisp,
            $finalDisp,
            $netCargoTon,
            $weighbridgeTon,
            $variancePct
        );

        return hash_hmac('sha256', $payload, self::SECRET);
    }

    public static function verify(
        HydrostaticDraftSurveyReconcilerResult $result
    ): bool {
        $expected = self::sign(
            $result->initialDisplacementTon,
            $result->finalDisplacementTon,
            $result->calculatedNetCargoTon,
            $result->weighbridgeTonnageTon,
            $result->variancePercent
        );

        return hash_equals($expected, $result->hmacSignature);
    }
}
