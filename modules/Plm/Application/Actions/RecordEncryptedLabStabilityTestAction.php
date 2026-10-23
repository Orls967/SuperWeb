<?php

declare(strict_types=1);

namespace Modules\Plm\Application\Actions;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Plm\Domain\Models\LabNotebook;
use Modules\Plm\Domain\Models\PlmProject;

class RecordEncryptedLabStabilityTestAction
{
    /**
     * @param  string  $stabilityResult  'pass'|'fail'|'pending'
     * @param  float  $sensoryScore  1.0 - 9.0
     * @return array{
     *     notebook: LabNotebook,
     *     experiment_code: string,
     *     stability_test_result: string,
     *     sensory_score: float,
     *     is_passing: bool
     * }
     */
    public function execute(
        PlmProject $project,
        string $title,
        string $formulaPayloadRaw,
        string $stabilityResult = 'pass',
        float $sensoryScore = 8.5
    ): array {
        if (empty($title) || empty($formulaPayloadRaw)) {
            throw new InvalidArgumentException('Experiment title and chemical/physical formula payload are required.');
        }

        if ($sensoryScore < 1.0 || $sensoryScore > 9.0) {
            throw new InvalidArgumentException("Sensory hedonic score {$sensoryScore} must be between 1.0 and 9.0.");
        }

        $validResults = ['pass', 'fail', 'pending'];
        if (! in_array($stabilityResult, $validResults, true)) {
            throw new InvalidArgumentException("Invalid stability test result: {$stabilityResult}.");
        }

        $experimentCode = 'EXP-'.strtoupper(Str::random(8));

        $notebook = LabNotebook::create([
            'id' => (string) Str::uuid(),
            'project_id' => $project->id,
            'experiment_code' => $experimentCode,
            'title' => $title,
            'formula_payload_encrypted' => $formulaPayloadRaw, // Cast as 'encrypted' by Eloquent
            'stability_test_result' => $stabilityResult,
            'sensory_score' => $sensoryScore,
        ]);

        return [
            'notebook' => $notebook,
            'experiment_code' => $experimentCode,
            'stability_test_result' => $stabilityResult,
            'sensory_score' => $sensoryScore,
            'is_passing' => $stabilityResult === 'pass' && $sensoryScore >= 6.0,
        ];
    }
}
