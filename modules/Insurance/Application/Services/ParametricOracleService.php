<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use InvalidArgumentException;
use Modules\Insurance\Domain\Models\InsuranceParametricOracle;

class ParametricOracleService
{
    public const VALID_SOURCES = [
        'BMKG_WEATHER',
        'FLIGHT_RADAR',
        'IOT_TELEMATICS',
    ];

    /**
     * @param  array{oracle_code: string, name: string, source_type: string, is_active?: bool, api_endpoint_config?: array<string, mixed>}  $data
     */
    public function registerOracle(array $data, string $idempotencyKey): InsuranceParametricOracle
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        $sourceType = (string) ($data['source_type'] ?? '');
        if (! in_array($sourceType, self::VALID_SOURCES, true)) {
            throw new InvalidArgumentException("Invalid oracle source type: '{$sourceType}'. Must be one of: ".implode(', ', self::VALID_SOURCES));
        }

        return InsuranceParametricOracle::updateOrCreate(
            ['oracle_code' => $data['oracle_code']],
            [
                'name' => $data['name'],
                'source_type' => $sourceType,
                'is_active' => $data['is_active'] ?? true,
                'api_endpoint_config' => $data['api_endpoint_config'] ?? null,
            ]
        );
    }
}
