<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Scale;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Models\SimSeederCheckpoint;

/**
 * Layanan orkestrasi skala volume data simulasi (Tier T1 & Tier T2) dengan chunk streaming dan checkpoint idempoten.
 */
class ScaleTierPlan
{
    public const TIER_T1_ROWS = 50000;

    public const TIER_T2_ROWS = 200000;

    public const CHUNK_SIZE = 5000;

    /**
     * @var list<array{stage: string, elapsed_seconds: float, rows_processed: int, throughput_rps: float}>
     */
    protected array $benchmarkResults = [];

    /**
     * Mengeksekusi provisioning data skala secara deterministik dan bertahap.
     *
     * @param  string  $tier  'T1' atau 'T2'
     * @return array{tier: string, total_rows: int, stages: list<array<string, mixed>>, status: string}
     */
    public function provision(string $tier = 'T1', ?callable $progressCallback = null): array
    {
        $targetRows = ($tier === 'T2') ? self::TIER_T2_ROWS : self::TIER_T1_ROWS;
        $stages = ['scale_actors', 'scale_entities', 'scale_transactions'];
        $totalProvisioned = 0;

        foreach ($stages as $stageIndex => $stageName) {
            $checkpoint = SimSeederCheckpoint::firstOrCreate(
                ['seeder_name' => "scale_provisioner_{$tier}", 'stage' => $stageName],
                ['last_processed_id' => 0, 'batch_number' => 0, 'completed' => false]
            );

            if ($checkpoint->completed) {
                $totalProvisioned += (int) ($checkpoint->metrics['rows'] ?? 0);

                continue;
            }

            $stageTarget = (int) ($targetRows / count($stages));
            $startOffset = (int) $checkpoint->last_processed_id;
            $remaining = max(0, $stageTarget - $startOffset);

            $startTime = microtime(true);
            $stageRows = 0;

            while ($remaining > 0) {
                $chunk = min($remaining, self::CHUNK_SIZE);

                // Eksekusi streaming chunk idempoten
                $this->executeChunkInsert($stageName, $startOffset + $stageRows, $chunk, $tier);

                $stageRows += $chunk;
                $remaining -= $chunk;

                $checkpoint->last_processed_id = $startOffset + $stageRows;
                $checkpoint->batch_number += 1;
                $checkpoint->metrics = [
                    'rows' => $startOffset + $stageRows,
                    'last_batch_at' => now()->toIso8601String(),
                ];
                $checkpoint->save();

                if ($progressCallback !== null) {
                    $progressCallback($stageName, $startOffset + $stageRows, $stageTarget);
                }
            }

            $elapsed = max(0.001, microtime(true) - $startTime);
            $throughput = $stageRows / $elapsed;

            $checkpoint->completed = true;
            $checkpoint->metrics = array_merge($checkpoint->metrics ?? [], [
                'elapsed_seconds' => round($elapsed, 4),
                'throughput_rps' => round($throughput, 2),
            ]);
            $checkpoint->save();

            $this->benchmarkResults[] = [
                'stage' => $stageName,
                'elapsed_seconds' => round($elapsed, 4),
                'rows_processed' => $stageRows,
                'throughput_rps' => round($throughput, 2),
            ];

            $totalProvisioned += $stageRows;
        }

        return [
            'tier' => $tier,
            'total_rows' => $totalProvisioned,
            'stages' => $this->benchmarkResults,
            'status' => 'completed',
        ];
    }

    /**
     * Memasukkan data dummy skala secara aman ke tabel simulasi/seeder.
     */
    protected function executeChunkInsert(string $stage, int $offset, int $count, string $tier): void
    {
        // Chunk insert deterministik ke tabel sim_twin_states dummy atau tabel seeder
        $records = [];
        $now = now();
        for ($i = 0; $i < $count; $i++) {
            $num = $offset + $i + 1;
            $entityId = "SCALE-{$tier}-{$stage}-{$num}";
            $payload = ['stage' => $stage, 'seq' => $num, 'tier' => $tier];
            $canonical = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
            $stateHash = hash('sha256', "GENESIS|scale|{$entityId}|{$canonical}");

            $records[] = [
                'entity_type' => 'scale_entity',
                'entity_id' => $entityId,
                'state' => $canonical,
                'state_hash' => $stateHash,
                'prev_hash' => 'GENESIS',
                'valid_from' => $now,
                'is_sandbox' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('sim_twin_states')->insertOrIgnore($records);
    }

    /**
     * @return list<array{stage: string, elapsed_seconds: float, rows_processed: int, throughput_rps: float}>
     */
    public function getBenchmarkResults(): array
    {
        return $this->benchmarkResults;
    }
}
