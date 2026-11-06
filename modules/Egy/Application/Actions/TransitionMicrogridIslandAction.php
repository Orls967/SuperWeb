<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Modules\Egy\Domain\Models\Microgrid;
use Modules\Egy\Domain\Models\MicrogridFeeder;
use Modules\Egy\Domain\StateMachines\MicrogridOperatingModeStateMachine;
use Modules\Shared\Domain\Algorithms\Utility\MicrogridLoadSheddingKnapsack;
use Modules\Shared\Domain\Algorithms\Utility\MicrogridLoadSheddingResult;

/**
 * Aksi transisi Microgrid ke Mode Islanding (terisolasi dari jaringan utama).
 * Mengeksekusi algoritma E13 Knapsack Load Shedding untuk menjaga kestabilan frekuensi
 * dengan mempertahankan beban kritis (ICU, server room) dan memutus feeder non-kritis.
 */
final class TransitionMicrogridIslandAction
{
    public function __construct(
        private readonly MicrogridLoadSheddingKnapsack $knapsackSolver,
        private readonly MicrogridOperatingModeStateMachine $stateMachine,
    ) {}

    public function execute(int $microgridId, int $availableGenerationKw): MicrogridLoadSheddingResult
    {
        $microgrid = Microgrid::with('feeders')->findOrFail($microgridId);

        // Validasi transisi status mode
        $microgrid->operating_mode = $this->stateMachine->transition(
            $microgrid->operating_mode,
            MicrogridOperatingModeStateMachine::MODE_ISLANDED
        );

        $feederData = $microgrid->feeders->map(fn (MicrogridFeeder $f) => [
            'id' => (int) $f->id,
            'name' => (string) $f->feeder_name,
            'tier' => (string) $f->tier,
            'priority_rank' => (int) $f->priority_rank,
            'required_kw' => (int) $f->rated_load_kw,
        ])->all();

        $result = $this->knapsackSolver->solve($availableGenerationKw, $feederData);

        // Terapkan hasil shedding pada database feeder
        $retainedIds = array_column($result->retainedFeeders, 'id');
        $shedIds = array_column($result->shedFeeders, 'id');

        MicrogridFeeder::whereIn('id', $retainedIds)->update(['is_energized' => true]);
        MicrogridFeeder::whereIn('id', $shedIds)->update(['is_energized' => false]);

        $microgrid->current_generation_kw = $result->totalServedKw;
        $microgrid->current_load_kw = $result->totalServedKw;
        $microgrid->save();

        return $result;
    }
}
