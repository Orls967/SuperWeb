<?php

declare(strict_types=1);

namespace Modules\Esg\Application\Actions;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Esg\Domain\Models\SupplierScore;

class EvaluateSupplierGriEsgScorecardAction
{
    /**
     * @param array{
     *     supplier_id: string,
     *     evaluation_year: string,
     *     environmental_score: int, // 0-100
     *     social_score: int,        // 0-100
     *     governance_score: int,    // 0-100
     *     certification_list?: string|null,
     *     audit_notes?: string|null
     * } $data
     */
    public function execute(array $data): SupplierScore
    {
        $env = $data['environmental_score'];
        $soc = $data['social_score'];
        $gov = $data['governance_score'];

        if ($env < 0 || $env > 100 || $soc < 0 || $soc > 100 || $gov < 0 || $gov > 100) {
            throw new InvalidArgumentException('Scores must be between 0 and 100.');
        }

        // GRI standard weighting: 40% Environmental, 30% Social, 30% Governance
        $overallScore = round(($env * 0.40) + ($soc * 0.30) + ($gov * 0.30), 2);

        $ratingLevel = match (true) {
            $overallScore >= 85.0 => 'LEAD',
            $overallScore >= 70.0 => 'ADVANCED',
            $overallScore >= 50.0 => 'COMPLIANT',
            default => 'HIGH_RISK',
        };

        return SupplierScore::updateOrCreate(
            [
                'supplier_id' => $data['supplier_id'],
                'evaluation_year' => $data['evaluation_year'],
            ],
            [
                'id' => (string) Str::uuid(),
                'environmental_score' => $env,
                'social_score' => $soc,
                'governance_score' => $gov,
                'overall_score' => $overallScore,
                'certification_list' => $data['certification_list'] ?? null,
                'rating_level' => $ratingLevel,
                'audit_notes' => $data['audit_notes'] ?? null,
            ]
        );
    }
}
