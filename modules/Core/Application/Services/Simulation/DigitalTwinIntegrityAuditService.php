<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Domain\Models\SimTwinState;

/**
 * Layanan audit komprehensif rantai hash integritas Digital Twin (D34/D35).
 * Mendeteksi anomali, putusnya pointer prev_hash, dan manipulasi payload siluman.
 */
final class DigitalTwinIntegrityAuditService
{
    /**
     * Mengaudit seluruh entitas twin yang terdaftar di sistem.
     *
     * @return array{
     *     total_entities_checked: int,
     *     valid_entities: int,
     *     corrupted_entities: int,
     *     anomalies: array<int, array{entity_type: string, entity_id: string, reason: string}>
     * }
     */
    public function auditEntireTwinBus(): array
    {
        $entities = SimTwinState::select('entity_type', 'entity_id')
            ->distinct()
            ->get();

        $validCount = 0;
        $corruptedCount = 0;
        $anomalies = [];

        foreach ($entities as $e) {
            $report = $this->auditEntity($e->entity_type, $e->entity_id);
            if ($report['valid']) {
                $validCount++;
            } else {
                $corruptedCount++;
                $anomalies[] = [
                    'entity_type' => $e->entity_type,
                    'entity_id' => $e->entity_id,
                    'reason' => $report['error'] ?? 'Rantai hash tidak valid',
                ];
            }
        }

        return [
            'total_entities_checked' => $entities->count(),
            'valid_entities' => $validCount,
            'corrupted_entities' => $corruptedCount,
            'anomalies' => $anomalies,
        ];
    }

    /**
     * Mengaudit satu entitas twin spesifik.
     *
     * @return array{valid: bool, total_records: int, error: string|null}
     */
    public function auditEntity(string $entityType, string $entityId): array
    {
        $records = SimTwinState::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('id', 'asc')
            ->get();

        if ($records->isEmpty()) {
            return ['valid' => true, 'total_records' => 0, 'error' => null];
        }

        $prevHash = null;

        foreach ($records as $r) {
            if ($r->prev_hash !== $prevHash) {
                return [
                    'valid' => false,
                    'total_records' => $records->count(),
                    'error' => "Diskontinuitas prev_hash pada ID #{$r->id}: diharapkan '{$prevHash}', ditemukan '{$r->prev_hash}'",
                ];
            }

            $payload = (array) $r->state_payload;
            ksort($payload);
            $canonical = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $expectedHash = hash('sha256', "{$prevHash}|{$canonical}");

            if (! hash_equals($expectedHash, (string) $r->event_hash)) {
                return [
                    'valid' => false,
                    'total_records' => $records->count(),
                    'error' => "Korupsi hash payload pada ID #{$r->id}: diharapkan '{$expectedHash}', ditemukan '{$r->event_hash}'",
                ];
            }

            $prevHash = (string) $r->event_hash;
        }

        return [
            'valid' => true,
            'total_records' => $records->count(),
            'error' => null,
        ];
    }
}
