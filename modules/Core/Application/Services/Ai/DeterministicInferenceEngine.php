<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Ai;

use Modules\Core\Domain\Models\CoreAiInference;
use Modules\Core\Domain\Models\CoreAiModel;
use Modules\Shared\Domain\Algorithms\Forecast\SimpleExponentialSmoothing;
use Modules\Shared\Domain\Algorithms\Stats\ModifiedZScore;

class DeterministicInferenceEngine
{
    private readonly ModifiedZScore $modifiedZScore;

    private readonly SimpleExponentialSmoothing $ses;

    public function __construct()
    {
        $this->modifiedZScore = new ModifiedZScore;
        $this->ses = new SimpleExponentialSmoothing;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function inferAndRecord(
        string $modelCode,
        string $version,
        array $input,
        int $seed
    ): CoreAiInference {
        // Pastikan model terdaftar (idempoten)
        $this->ensureModelRegistered($modelCode, $version);

        // Sort input keys secara kanonik
        $canonicalInput = $this->canonicalizeArray($input);
        $inputJson = json_encode($canonicalInput, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $inputHash = hash('sha256', (string) $inputJson);

        // Eksekusi komputasi deterministik
        $computedOutput = $this->executeDeterministicComputation($modelCode, $canonicalInput, $seed);

        $canonicalOutput = $this->canonicalizeArray($computedOutput);
        $outputJson = json_encode($canonicalOutput, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $outputHash = hash('sha256', (string) $outputJson);

        return CoreAiInference::create([
            'model_code' => $modelCode,
            'model_version' => $version,
            'input_snapshot' => $canonicalInput,
            'input_hash' => $inputHash,
            'seed' => $seed,
            'output' => $canonicalOutput,
            'output_hash' => $outputHash,
        ]);
    }

    /**
     * @return array{is_identical: bool, computed_output: array<string, mixed>, stored_output: array<string, mixed>}
     */
    public function replay(int $inferenceId): array
    {
        $inference = CoreAiInference::findOrFail($inferenceId);

        $recomputed = $this->executeDeterministicComputation(
            $inference->model_code,
            $inference->input_snapshot,
            (int) $inference->seed
        );

        $canonicalRecomputed = $this->canonicalizeArray($recomputed);
        $recomputedJson = json_encode($canonicalRecomputed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $recomputedHash = hash('sha256', (string) $recomputedJson);

        $isIdentical = ($recomputedHash === $inference->output_hash)
            && ($canonicalRecomputed == $inference->output);

        return [
            'is_identical' => $isIdentical,
            'computed_output' => $canonicalRecomputed,
            'stored_output' => $inference->output,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function executeDeterministicComputation(string $modelCode, array $input, int $seed): array
    {
        if ($modelCode === 'ANOMALY_DETECTOR') {
            $amount = (int) ($input['amount_idr'] ?? 0);
            $history = (array) ($input['history'] ?? [$amount]);
            $historyInt = array_map(fn ($v) => (int) $v, $history);

            $res = $this->modifiedZScore->evaluate($amount, $historyInt);

            return [
                'amount_idr' => $amount,
                'is_anomaly' => $res->isAnomaly,
                'median' => $res->median->toFloat(),
                'mad' => $res->mad->toFloat(),
                'z_score' => $res->zFormatted,
                'seed' => $seed,
            ];
        }

        if ($modelCode === 'STOCK_SES') {
            $demands = array_map(fn ($v) => (int) $v, (array) ($input['demands'] ?? [100]));
            $alphaBps = (int) ($input['alpha_bps'] ?? 3000);
            $holidayBps = (int) ($input['holiday_factor_bps'] ?? 10000);

            $res = $this->ses->forecastWithHolidayAdjustment($demands, $alphaBps, $holidayBps);

            return [
                'level' => $res->levelFormatted,
                'adjusted_level' => $res->adjustedLevelFormatted,
                'suggested_units_per_day' => $res->suggestedUnitsPerDay,
                'alpha_bps' => $alphaBps,
                'holiday_factor_bps' => $holidayBps,
                'seed' => $seed,
            ];
        }

        // Generic fallback model
        return [
            'status' => 'COMPUTED',
            'model_code' => $modelCode,
            'seed' => $seed,
            'input_echo' => $input,
        ];
    }

    /**
     * @param  array<mixed>  $array
     * @return array<mixed>
     */
    public function canonicalizeArray(array $array): array
    {
        // Sort keys recursively
        $isAssoc = array_keys($array) !== range(0, count($array) - 1);
        if ($isAssoc) {
            ksort($array, SORT_STRING);
        }

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->canonicalizeArray($value);
            }
        }

        return $array;
    }

    private function ensureModelRegistered(string $modelCode, string $version): void
    {
        $existing = CoreAiModel::where('code', $modelCode)->where('version', $version)->first();
        if ($existing !== null) {
            return;
        }

        $params = [
            'registered_at' => '2026-11-04T00:00:00Z',
            'engine' => 'DeterministicInferenceEngine',
        ];
        $canonicalParams = $this->canonicalizeArray($params);
        $hash = hash('sha256', (string) json_encode($canonicalParams));

        CoreAiModel::create([
            'code' => $modelCode,
            'version' => $version,
            'canonical_parameters' => $canonicalParams,
            'parameter_hash' => $hash,
        ]);
    }
}
