<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Simulation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\StateMachines\SimulationRunStateMachine;
use Modules\Core\Domain\StateMachines\SimulationRunStatus;
use Modules\Core\Domain\ValueObjects\Simulation\HashChainFingerprint;
use Modules\Core\Domain\ValueObjects\Simulation\SeedValue;

final class CreateSimulationRunAction
{
    public function __construct(
        private readonly SimulationRunStateMachine $stateMachine,
    ) {}

    /**
     * @param array{
     *     name: string,
     *     days: int,
     *     seed: int,
     *     mode?: string,
     *     created_by?: int|null
     * } $data
     */
    public function execute(array $data): SimRun
    {
        $seedVO = SeedValue::fromInt((int) $data['seed']);
        $mode = $data['mode'] ?? 'live';
        $genesisHash = HashChainFingerprint::genesis($seedVO)->toHex();

        return DB::transaction(function () use ($data, $seedVO, $mode, $genesisHash) {
            $run = SimRun::create([
                'id' => (string) Str::uuid(),
                'name' => $data['name'],
                'seed' => $seedVO->toInt(),
                'status' => SimulationRunStatus::DRAFT->value,
                'mode' => $mode,
                'current_day' => 0,
                'total_days' => (int) $data['days'],
                'chain_hash' => $genesisHash,
                'created_by' => $data['created_by'] ?? null,
            ]);

            // Transisi dari draft ke provisioned
            $this->stateMachine->transition(
                $run,
                SimulationRunStatus::PROVISIONED,
                'Inisialisasi sesi simulasi dan alokasi sumber daya virtual',
                actorId: (string) ($data['created_by'] ?? 'system'),
                actorRole: 'sim_operator'
            );

            return $run;
        });
    }
}
