<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use InvalidArgumentException;

/**
 * Katalog dan validator skema event simulasi resmi.
 */
final class SimulationEventCatalogService
{
    /** @var array<string, array{version: string, required_fields: array<int, string>}> */
    private array $schemas = [
        'core.simulation.run_state_changed.v1' => [
            'version' => '1.0.0',
            'required_fields' => ['event_id', 'event_type', 'run_id', 'from_status', 'to_status', 'occurred_at'],
        ],
        'core.simulation.checkpoint_written.v1' => [
            'version' => '1.0.0',
            'required_fields' => ['event_id', 'event_type', 'run_id', 'day_number', 'chain_hash', 'occurred_at'],
        ],
        'core.simulation.day_completed.v1' => [
            'version' => '1.0.0',
            'required_fields' => ['event_id', 'event_type', 'run_id', 'day_number', 'chain_hash', 'occurred_at'],
        ],
        'core.simulation.divergence_detected.v1' => [
            'version' => '1.0.0',
            'required_fields' => ['event_id', 'event_type', 'run_a_id', 'run_b_id', 'divergence_day', 'reason'],
        ],
    ];

    /**
     * @return array<string, array{version: string, required_fields: array<int, string>}>
     */
    public function getCatalog(): array
    {
        return $this->schemas;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function validatePayload(string $eventType, array $payload): bool
    {
        if (! isset($this->schemas[$eventType])) {
            throw new InvalidArgumentException("Tipe event simulasi tidak terdaftar: {$eventType}");
        }

        $schema = $this->schemas[$eventType];
        foreach ($schema['required_fields'] as $field) {
            if (! array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
                throw new InvalidArgumentException("Field wajib '{$field}' hilang atau kosong pada event [{$eventType}].");
            }
        }

        return true;
    }
}
