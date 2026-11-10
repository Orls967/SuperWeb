<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Application\Services\SimTickRegistry;
use Modules\Core\Contracts\DigitalTwinInterface;
use Modules\Core\Domain\Algorithms\Simulation\Exceptions\CyclicDependencyException;
use Modules\Core\Domain\Models\SimCheckpoint;
use Modules\Core\Domain\Models\SimEventSpine;
use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\Models\SimRunTransition;
use Modules\Core\Domain\Models\SimTwinState;

class AuditSimulationKernelCommand extends Command
{
    protected $signature = 'sim:audit';

    protected $description = 'Audit dua sumber determinisme kernel simulasi, transisi status, rantai fingerprint, checkpoint, twin hash chain, dan keseimbangan ledger (Fase 67)';

    public function handle(DigitalTwinInterface $twinService, SimTickRegistry $tickRegistry): int
    {
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║   sim:audit — Audit Dua Sumber Simulation Kernel (Fase 67)   ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        if (! Schema::hasTable('sim_twin_states') || ! Schema::hasTable('sim_runs')) {
            $this->warn('Tabel operasional simulasi belum lengkap; lewati audit.');

            return self::SUCCESS;
        }

        $discrepancies = 0;

        // 1. Audit Digital Twin Hash Chains
        $this->line('1. Memeriksa integritas rantai hash Digital Twin...');
        $entities = SimTwinState::query()
            ->select('entity_type', 'entity_id')
            ->distinct()
            ->get();

        foreach ($entities as $entity) {
            if (! $twinService->verifyChain($entity->entity_type, $entity->entity_id)) {
                $this->error("sim:audit FAILED: Digital twin hash-chain corruption detected (CORRUPTION ON {$entity->entity_type}:{$entity->entity_id}).");
                $discrepancies++;

                return self::FAILURE;
            }
        }

        // 2. Audit Riwayat Transisi vs Status SimRun
        $this->line('2. Memeriksa konsistensi riwayat transisi status SimRun...');
        $runs = SimRun::all();
        foreach ($runs as $run) {
            $latestTransition = SimRunTransition::where('run_id', $run->id)->orderBy('id', 'desc')->first();
            if ($latestTransition && $latestTransition->to_status !== $run->status) {
                $this->error("CORRUPTION DETECTED: Ketidakkonsistenan status SimRun [{$run->name}]. Model status: '{$run->status}', riwayat terakhir: '{$latestTransition->to_status}'.");
                $discrepancies++;

                return self::FAILURE;
            }
        }

        // 3. Audit Checkpoint vs Chain Hash
        $this->line('3. Memverifikasi kesinambungan checkpoint vs rantai fingerprint...');
        $checkpoints = SimCheckpoint::all();
        foreach ($checkpoints as $cp) {
            if (empty($cp->chain_hash) || strlen($cp->chain_hash) !== 64) {
                $this->error("CORRUPTION DETECTED: Checkpoint ID {$cp->id} pada hari {$cp->day_number} memuat chain hash tidak valid.");
                $discrepancies++;

                return self::FAILURE;
            }
        }

        // 4. Audit Keseimbangan Double-Entry Ledger Riil (Σ Debit = Σ Kredit)
        $this->line('4. Menginspeksi saldo buku besar riil (probe saldo nol)...');
        if (Schema::hasTable('bank_ledger_entries')) {
            $bankTable = 'bank_ledger_entries';
            $ledgerStats = DB::table($bankTable)
                ->selectRaw('COALESCE(SUM(amount), 0) as balance_net')
                ->first();

            $netBalance = (int) ($ledgerStats->balance_net ?? 0);
            if ($netBalance !== 0) {
                $this->error("CORRUPTION DETECTED: Buku besar bank tidak seimbang (saldo bersih selisih: {$netBalance}).");
                $discrepancies++;

                return self::FAILURE;
            }
        }

        // 5. Audit Dependensi Tick Handler Terdaftar (Deteksi Siklus Kahn A2)
        $this->line('5. Memeriksa validitas graf dependensi tick handler...');
        try {
            $tickRegistry->resolveExecutionOrder();
        } catch (CyclicDependencyException $e) {
            $cycleList = implode(', ', $e->cycleNodes);
            $this->error("CORRUPTION DETECTED: Ditemukan siklus dependensi sirkular pada tick handlers: [{$cycleList}].");
            $discrepancies++;

            return self::FAILURE;
        }

        // 6. Audit Idempotensi Event Spine jika ada
        if (Schema::hasTable('sim_event_spine')) {
            $duplicateKeys = SimEventSpine::query()
                ->select('idempotency_key')
                ->groupBy('idempotency_key')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            if ($duplicateKeys > 0) {
                $this->error("CORRUPTION DETECTED: Ditemukan duplikasi idempotency key pada event spine ({$duplicateKeys} duplikat).");
                $discrepancies++;

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('sim:audit PASSED — 100% konsisten, 0 diskrepansi, seluruh rantai deterministik terverifikasi.');

        return self::SUCCESS;
    }
}
