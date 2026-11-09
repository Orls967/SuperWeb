<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Diagnostics;

use Modules\Hospital\Application\Services\WestgardMultiruleEvaluator;
use Modules\Hospital\Domain\DTO\WestgardQcEvaluationDTO;

class ProcessWestgardQcRunAction
{
    public function __construct(
        protected WestgardMultiruleEvaluator $evaluator
    ) {}

    /**
     * @param  list<float>  $recentZScores
     */
    public function execute(
        string $analyzerCode,
        string $lotNumber,
        string $controlLevel,
        string $testCode,
        float $targetMean,
        float $targetSd,
        float $measuredValue,
        array $recentZScores = [],
        ?int $technologistId = null
    ): WestgardQcEvaluationDTO {
        $eval = $this->evaluator->evaluateQcRun(
            analyzerCode: $analyzerCode,
            lotNumber: $lotNumber,
            controlLevel: $controlLevel,
            testCode: $testCode,
            targetMean: $targetMean,
            targetSd: $targetSd,
            measuredValue: $measuredValue,
            recentZScores: $recentZScores,
            technologistId: $technologistId
        );

        return new WestgardQcEvaluationDTO(
            analyzerCode: $analyzerCode,
            lotNumber: $lotNumber,
            controlLevel: $controlLevel,
            testCode: $testCode,
            targetMean: $targetMean,
            targetSd: $targetSd,
            measuredValue: $measuredValue,
            zScore: $eval['z_score'],
            violation: $eval['violation'],
            isRejected: $eval['is_rejected'],
            description: $eval['description']
        );
    }
}
