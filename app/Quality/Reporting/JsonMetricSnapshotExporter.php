<?php

declare(strict_types=1);

namespace App\Quality\Reporting;

use Modules\Platform\Domain\Models\PhaseMetricSnapshot;

/**
 * Serializer JSON terstruktur untuk artefak baseline CI ratchet.
 */
final class JsonMetricSnapshotExporter
{
    public function exportToJson(PhaseMetricSnapshot $snapshot, bool $pretty = true): string
    {
        $data = [
            'schema_version' => '1.0.0',
            'phase_id' => $snapshot->phase_id,
            'timestamp' => $snapshot->created_at?->toIso8601String() ?? date('c'),
            'metrics' => [
                'b1_loc' => $snapshot->b1_loc,
                'b2_loc' => $snapshot->b2_loc,
                'b3_loc' => $snapshot->b3_loc,
                'decision_density' => $snapshot->decision_density,
                'duplicate_ratio' => $snapshot->duplicate_ratio,
                'orphan_count' => $snapshot->orphan_count,
            ],
            'compliance' => [
                'status' => $snapshot->status,
                'is_clean' => $snapshot->status === 'compliant',
            ],
            'provenance' => [
                'certificate_hash' => $snapshot->certificate_hash,
                'engine' => 'App\\Quality\\Engine v2.0',
            ],
            'breakdown' => $snapshot->breakdown ?? [],
        ];

        $options = JSON_THROW_ON_ERROR;
        if ($pretty) {
            $options |= JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
        }

        return json_encode($data, $options);
    }
}

