<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Simulation;

use Modules\Core\Domain\Models\SimTwinState;

final class VerifyTwinChainAction
{
    /**
     * @return array{valid: bool, records_checked: int, broken_id: int|null}
     */
    public function execute(string $entityType, string $entityId): array
    {
        $records = SimTwinState::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('id', 'asc')
            ->get();

        if ($records->isEmpty()) {
            return ['valid' => true, 'records_checked' => 0, 'broken_id' => null];
        }

        $prevHash = null;
        $checked = 0;

        foreach ($records as $r) {
            $checked++;
            if ($r->prev_hash !== $prevHash) {
                return ['valid' => false, 'records_checked' => $checked, 'broken_id' => $r->id];
            }

            $payload = (array) $r->state_payload;
            ksort($payload);
            $canonicalJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $computedHash = hash('sha256', "{$prevHash}|{$canonicalJson}");

            if (! hash_equals($computedHash, (string) $r->event_hash)) {
                return ['valid' => false, 'records_checked' => $checked, 'broken_id' => $r->id];
            }

            $prevHash = (string) $r->event_hash;
        }

        return ['valid' => true, 'records_checked' => $checked, 'broken_id' => null];
    }
}
