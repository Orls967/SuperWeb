<?php

declare(strict_types=1);

namespace Modules\Esg\Application\Actions;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Esg\Domain\Models\EsgEmission;

class CalculateScope123GhgEmissionAction
{
    /**
     * @param array{
     *     entity_id: string,
     *     scope: string, // scope_1, scope_2, scope_3
     *     activity_type: string,
     *     activity_data_amount: float,
     *     activity_uom: string,
     *     emission_factor: float,
     *     reporting_period: string,
     *     source_module?: string|null,
     *     source_reference?: string|null,
     *     metadata?: array<string, mixed>|null
     * } $data
     * @return array{
     *     emission: EsgEmission,
     *     co2e_kg: float,
     *     co2e_tons: float
     * }
     */
    public function execute(array $data): array
    {
        $validScopes = ['scope_1', 'scope_2', 'scope_3'];
        if (! in_array($data['scope'], $validScopes, true)) {
            throw new InvalidArgumentException("Invalid GHG emission scope: {$data['scope']}.");
        }

        if ($data['activity_data_amount'] <= 0.0 || $data['emission_factor'] <= 0.0) {
            throw new InvalidArgumentException('Activity data amount and emission factor must be positive.');
        }

        $co2eKg = round($data['activity_data_amount'] * $data['emission_factor'], 4);
        $co2eTons = round($co2eKg / 1000.0, 4);

        $emissionNumber = 'EM-'.strtoupper($data['scope']).'-'.strtoupper(Str::random(8));

        $emission = EsgEmission::create([
            'id' => (string) Str::uuid(),
            'emission_number' => $emissionNumber,
            'entity_id' => $data['entity_id'],
            'scope' => $data['scope'],
            'activity_type' => $data['activity_type'],
            'activity_data_amount' => $data['activity_data_amount'],
            'activity_uom' => $data['activity_uom'],
            'emission_factor' => $data['emission_factor'],
            'co2e_kg' => $co2eKg,
            'reporting_period' => $data['reporting_period'],
            'source_module' => $data['source_module'] ?? null,
            'source_reference' => $data['source_reference'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        return [
            'emission' => $emission,
            'co2e_kg' => $co2eKg,
            'co2e_tons' => $co2eTons,
        ];
    }
}
