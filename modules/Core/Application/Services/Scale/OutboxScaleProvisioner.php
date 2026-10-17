<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Scale;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ScaleProvisionerInterface;

final class OutboxScaleProvisioner implements ScaleProvisionerInterface
{
    public function moduleName(): string
    {
        return 'outbox';
    }

    public function provision(
        string $tier,
        int $targetCount,
        int $chunkSize,
        int $seed,
        int $startStep = 0,
        ?callable $onProgress = null
    ): int {
        $checkpoint = DB::table('sim_scale_checkpoints')
            ->where('provisioner_key', $this->moduleName())
            ->where('tier', $tier)
            ->where('seed', $seed)
            ->first();

        $startStep = 0;
        $totalInserted = 0;

        if ($checkpoint !== null) {
            $startStep = (int) $checkpoint->last_processed_step;
            $totalInserted = (int) $checkpoint->total_inserted;
            if ($totalInserted >= $targetCount) {
                return $totalInserted;
            }
        } else {
            DB::table('sim_scale_checkpoints')->insert([
                'provisioner_key' => $this->moduleName(),
                'tier' => $tier,
                'seed' => $seed,
                'last_processed_step' => 0,
                'total_inserted' => 0,
                'status' => 'in_progress',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $now = Carbon::now();

        for ($step = $startStep; $step < $targetCount; $step += $chunkSize) {
            $batchSize = min($chunkSize, $targetCount - $step);

            DB::transaction(function () use (
                $batchSize,
                $step,
                $tier,
                $seed,
                $now,
                &$totalInserted
            ) {
                $batch = [];

                for ($i = 0; $i < $batchSize; $i++) {
                    $itemIndex = $step + $i;
                    $idempotencyKey = 'SCALE-OUTBOX-'.$tier.'-'.$seed.'-'.$itemIndex;
                    $batch[] = [
                        'event_type' => 'scale.event.simulated.v1',
                        'aggregate_type' => 'ScaleEntity',
                        'aggregate_id' => 'SCALE-'.$itemIndex,
                        'payload' => json_encode([
                            'tier' => $tier,
                            'seed' => $seed,
                            'index' => $itemIndex,
                            'timestamp' => $now->toIso8601String(),
                        ]),
                        'headers' => json_encode(['x-scale-tier' => $tier]),
                        'idempotency_key' => $idempotencyKey,
                        'status' => 'pending',
                        'attempts' => 0,
                        'last_error' => null,
                        'next_retry_at' => null,
                        'dispatched_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('core_outbox')->insert($batch);
                $totalInserted += $batchSize;
                $currentStep = $step + $batchSize;

                DB::table('sim_scale_checkpoints')
                    ->where('provisioner_key', $this->moduleName())
                    ->where('tier', $tier)
                    ->where('seed', $seed)
                    ->update([
                        'last_processed_step' => $currentStep,
                        'total_inserted' => $totalInserted,
                        'status' => ($totalInserted >= $currentStep) ? 'in_progress' : 'completed',
                        'updated_at' => now(),
                    ]);
            });

            if ($onProgress !== null) {
                $onProgress($totalInserted, $targetCount, "Outbox provisioned {$totalInserted}/{$targetCount} messages");
            }
        }

        DB::table('sim_scale_checkpoints')
            ->where('provisioner_key', $this->moduleName())
            ->where('tier', $tier)
            ->where('seed', $seed)
            ->update([
                'status' => 'completed',
                'updated_at' => now(),
            ]);

        return $totalInserted;
    }
}
