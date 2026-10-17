<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Scale;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ScaleProvisionerInterface;
use Modules\Core\Domain\Enums\VehicleEventType;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Core\Domain\Models\VehicleEvent;

final class PassportScaleProvisioner implements ScaleProvisionerInterface
{
    public function moduleName(): string
    {
        return 'passport';
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

        // Ensure a system user exists for vehicle ownership
        $user = User::first();
        if ($user === null) {
            $user = User::create([
                'name' => 'Scale System Operator',
                'email' => 'scale-operator@example.com',
                'password' => bcrypt('password'),
                'role' => 'customer',
            ]);
        }
        $userId = $user->id;

        // Ensure a dedicated vehicle exists for this scale provisioner
        $vin = 'SCALE-VIN-'.$tier.'-'.$seed;
        $vehicle = Vehicle::firstOrCreate(
            ['vin' => $vin],
            [
                'user_id' => $userId,
                'plate_number' => 'B '.($seed % 9000 + 1000).' SCL',
                'color' => 'Graphite Metallic',
                'status' => 'active',
                'odometer_km' => 10000,
            ]
        );

        $latestEvent = VehicleEvent::where('vehicle_id', $vehicle->id)
            ->orderByDesc('sequence')
            ->first();

        $currentSequence = $latestEvent ? $latestEvent->sequence : 0;
        $prevHash = $latestEvent ? $latestEvent->hash : str_repeat('0', 64);

        $baseDate = Carbon::parse('2026-01-01 08:00:00');

        for ($step = $startStep; $step < $targetCount; $step += $chunkSize) {
            $batchSize = min($chunkSize, $targetCount - $step);

            DB::transaction(function () use (
                $batchSize,
                $vehicle,
                $baseDate,
                $tier,
                $seed,
                &$currentSequence,
                &$prevHash,
                &$totalInserted,
                $step
            ) {
                $batchRows = [];

                for ($i = 0; $i < $batchSize; $i++) {
                    $itemIndex = $step + $i;
                    $currentSequence++;
                    $type = VehicleEventType::ODOMETER_UPDATED;
                    $payload = [
                        'odometer_km' => 10000 + ($currentSequence * 10),
                        'source' => 'scale_provisioner',
                        'tier' => $tier,
                        'seed' => $seed,
                        'index' => $itemIndex,
                    ];
                    $occurredAt = (clone $baseDate)->addMinutes($currentSequence * 5);
                    $hash = VehicleEvent::calculateHash($prevHash, $currentSequence, $type, $payload, $occurredAt);

                    $batchRows[] = [
                        'vehicle_id' => $vehicle->id,
                        'sequence' => $currentSequence,
                        'type' => $type->value,
                        'payload' => json_encode($payload),
                        'occurred_at' => $occurredAt,
                        'prev_hash' => $prevHash,
                        'hash' => $hash,
                        'actor_id' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $prevHash = $hash;
                }

                DB::table('core_vehicle_events')->insert($batchRows);
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
                $onProgress($totalInserted, $targetCount, "Passport provisioned {$totalInserted}/{$targetCount} events");
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
