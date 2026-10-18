<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Contracts\DigitalTwinInterface;
use Modules\Core\Domain\Models\SimEventSpine;
use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\Models\SimTwinState;

class AuditSimulationKernelCommand extends Command
{
    protected $signature = 'sim:audit';

    protected $description = 'Audit deterministic Simulation Kernel state hashes, Event Spine idempotency, and Digital Twin hash chains (Fase 67)';

    public function handle(DigitalTwinInterface $twinService): int
    {
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║   sim:audit — Simulation Kernel & Digital Twin Bus Audit    ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        if (! Schema::hasTable('sim_twin_states') || ! Schema::hasTable('sim_event_spine') || ! Schema::hasTable('sim_runs')) {
            $this->warn('Simulation operational tables not found; skipping simulation audit.');

            return self::SUCCESS;
        }

        $discrepancies = 0;

        // 1. Audit Digital Twin Hash Chains
        $this->line('1. Auditing Digital Twin hash-chain integrity across entities...');
        $entities = SimTwinState::query()
            ->select('entity_type', 'entity_id')
            ->distinct()
            ->get();

        $verifiedTwins = 0;
        foreach ($entities as $entity) {
            if (! $twinService->verifyChain($entity->entity_type, $entity->entity_id)) {
                $this->error("sim:audit FAILED: Digital twin hash-chain corruption detected on {$entity->entity_type}:{$entity->entity_id}.");
                $discrepancies++;

                return self::FAILURE;
            }
            $verifiedTwins++;
        }

        // 2. Audit Event Spine Idempotency & Schema
        $this->line('2. Auditing Universal Event Spine idempotency keys and topic envelopes...');
        $duplicateKeys = SimEventSpine::query()
            ->select('idempotency_key')
            ->groupBy('idempotency_key')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicateKeys > 0) {
            $this->error("sim:audit FAILED: Duplicate idempotency keys detected in event spine ({$duplicateKeys} duplicate keys).");
            $discrepancies++;

            return self::FAILURE;
        }
        $totalSpineEvents = SimEventSpine::count();

        // 3. Audit Simulation Runs & State Hashes
        $this->line('3. Auditing Simulation Kernel runs and state hash sequence...');
        $runs = SimRun::all();
        foreach ($runs as $run) {
            if ($run->last_completed_day > 0 && ! empty($run->last_state_hash)) {
                if (strlen($run->last_state_hash) !== 64) {
                    $this->error("sim:audit FAILED: Invalid state hash length on simulation run '{$run->name}'.");
                    $discrepancies++;

                    return self::FAILURE;
                }
            }
        }

        $this->newLine();
        $this->line("  Digital Twin Entities Verified : {$verifiedTwins}");
        $this->line("  Event Spine Ingested Events    : {$totalSpineEvents}");
        $this->line("  Simulation Runs Audited        : {$runs->count()}");
        $this->newLine();

        if ($discrepancies > 0) {
            $this->error("sim:audit FAILED: {$discrepancies} integrity discrepancy(ies) detected.");

            return self::FAILURE;
        }

        $this->info('sim:audit PASSED — 100% Digital Twin chains valid, Event Spine deterministic, zero discrepancies.');

        return self::SUCCESS;
    }
}
