<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Modules\Core\Domain\Models\CoreAiAnomalyQuarantine;

class CoreAiAnomalyQuarantineService
{
    /**
     * @param  list<string>  $flags
     * @return array{transaction_reference: string, risk_score: int, is_quarantined: bool, indicators: list<string>}
     */
    public function evaluateTransactionRisk(
        string $reference,
        string $domainCode,
        int $velocityCountLast60s,
        array $flags = []
    ): array {
        $score = 0;
        $indicators = [];

        if ($velocityCountLast60s > 5) {
            $score += 30;
            $indicators[] = 'RAPID_VELOCITY';
        }
        if (in_array('EXTREME_MAGNITUDE', $flags, true)) {
            $score += 35;
            $indicators[] = 'EXTREME_MAGNITUDE_DEVIATION';
        }
        if (in_array('GEO_HOPPING', $flags, true)) {
            $score += 25;
            $indicators[] = 'GEO_HOPPING_SUSPICIOUS';
        }
        if (in_array('CONTRACT_BREACH', $flags, true)) {
            $score += 20;
            $indicators[] = 'CONTRACT_SPEC_BREACH';
        }
        if (in_array('DEVICE_MISMATCH', $flags, true)) {
            $score += 15;
            $indicators[] = 'DEVICE_FINGERPRINT_MISMATCH';
        }

        $finalScore = min(100, $score);
        $quarantined = false;

        if ($finalScore >= 60) {
            CoreAiAnomalyQuarantine::query()->updateOrCreate(
                ['transaction_ref' => $reference],
                [
                    'customer_ref' => 'CUST-'.strtoupper($domainCode),
                    'amount_idr' => 1000000,
                    'anomaly_reasons' => $indicators,
                    'risk_score' => (float) ($finalScore / 100),
                    'status' => 'QUARANTINED',
                    'quarantined_at' => now(),
                ]
            );
            $quarantined = true;
        }

        return [
            'transaction_reference' => $reference,
            'risk_score' => $finalScore,
            'is_quarantined' => $quarantined,
            'indicators' => $indicators,
        ];
    }
}
