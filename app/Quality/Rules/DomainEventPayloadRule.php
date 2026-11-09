<?php

declare(strict_types=1);

namespace App\Quality\Rules;

/**
 * Aturan Kualitas: Memvalidasi integritas serialisasi payload event domain pilar.
 */
final class DomainEventPayloadRule
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{is_compliant: bool, missing_keys: list<string>}
     */
    public function validatePayload(array $payload): array
    {
        $requiredKeys = ['event_id', 'event_type', 'occurred_at', 'aggregate_id'];
        $missing = [];

        foreach ($requiredKeys as $key) {
            if (! isset($payload[$key])) {
                $missing[] = $key;
            }
        }

        return [
            'is_compliant' => empty($missing),
            'missing_keys' => $missing,
        ];
    }
}
