<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Identity;

/**
 * Multi-Source Identity Attribute Conflict Resolver (W8-D56).
 *
 * When multiple profiles are linked across business verticals, resolves discrepant
 * field values (e.g. addresses, phone numbers, tax IDs) using authoritative source precedence
 * and recency weighting.
 */
final class IdentityConflictResolver
{
    /**
     * @var array<string, int> Authority weights per business vertical (higher = more authoritative)
     */
    private array $sourceAuthorityWeights = [
        'HRIS' => 100,
        'HOSPITAL' => 95,
        'BANKING' => 90,
        'PROCUREMENT' => 80,
        'LOGISTICS' => 70,
        'COMMERCE' => 60,
    ];

    /**
     * Resolve attribute conflict between two linked profiles.
     *
     * @param  string  $attribute  Key being reconciled (e.g., 'phone_number', 'legal_name')
     * @param  array{value: mixed, source: string, updated_at: int}  $profileA
     * @param  array{value: mixed, source: string, updated_at: int}  $profileB
     * @return array{
     *     resolved_value: mixed,
     *     selected_source: string,
     *     confidence: float,
     *     has_conflict: bool,
     *     resolution_reason: string
     * }
     */
    public function resolveConflict(
        string $attribute,
        array $profileA,
        array $profileB
    ): array {
        // 1. Direct match: no conflict
        if ($profileA['value'] === $profileB['value']) {
            return [
                'resolved_value' => $profileA['value'],
                'selected_source' => $profileA['source'],
                'confidence' => 1.0,
                'has_conflict' => false,
                'resolution_reason' => 'Attributes match identically across profiles.',
            ];
        }

        // 2. Weight by source authority
        $weightA = $this->sourceAuthorityWeights[strtoupper($profileA['source'])] ?? 50;
        $weightB = $this->sourceAuthorityWeights[strtoupper($profileB['source'])] ?? 50;

        if ($weightA !== $weightB) {
            $winner = $weightA > $weightB ? $profileA : $profileB;
            $loser = $weightA > $weightB ? $profileB : $profileA;
            $conf = round(max($weightA, $weightB) / ($weightA + $weightB), 3);

            return [
                'resolved_value' => $winner['value'],
                'selected_source' => $winner['source'],
                'confidence' => $conf,
                'has_conflict' => true,
                'resolution_reason' => "Resolved by source authority precedence ({$winner['source']} [{$weightA}] over {$loser['source']} [{$weightB}]).",
            ];
        }

        // 3. Fallback: Recency weighting
        $winner = $profileA['updated_at'] >= $profileB['updated_at'] ? $profileA : $profileB;
        $loser = $profileA['updated_at'] >= $profileB['updated_at'] ? $profileB : $profileA;

        return [
            'resolved_value' => $winner['value'],
            'selected_source' => $winner['source'],
            'confidence' => 0.75,
            'has_conflict' => true,
            'resolution_reason' => "Resolved by timestamp recency ({$winner['source']} updated at {$winner['updated_at']}).",
        ];
    }
}
