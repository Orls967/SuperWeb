<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Domain\Algorithms\ProjectionHashCalculator;
use Modules\Core\Domain\Models\SimEventSpine;
use Modules\Core\Domain\Models\SimRun;

/**
 * Validator Replay Deterministik Simulasi (SimulationReplayDeterministicValidator)
 *
 * Menguji apakah dua simulasi dengan konfigurasi dan seed identik menghasilkan
 * deret event spine yang sama persis dan status hash proyeksi yang identik ($h_1 == h_2$).
 */
class SimulationReplayDeterministicValidator
{
    public function __construct(
        protected ProjectionHashCalculator $hashCalculator
    ) {}

    /**
     * Membandingkan jejak event dari dua proses simulasi (Run A vs Run B).
     *
     * @return array{
     *     is_deterministic: bool,
     *     run_a_id: int,
     *     run_b_id: int,
     *     run_a_event_count: int,
     *     run_b_event_count: int,
     *     hash_run_a: string,
     *     hash_run_b: string,
     *     discrepancies: array<int, string>,
     *     divergence_tick: int|null
     * }
     */
    public function validateRuns(SimRun $runA, SimRun $runB): array
    {
        $eventsA = SimEventSpine::query()
            ->where('payload->sim_run_id', $runA->id)
            ->orderBy('id', 'asc')
            ->get();

        $eventsB = SimEventSpine::query()
            ->where('payload->sim_run_id', $runB->id)
            ->orderBy('id', 'asc')
            ->get();

        $countA = $eventsA->count();
        $countB = $eventsB->count();

        $stateA = [];
        foreach ($eventsA as $idx => $evt) {
            $stateA["step_{$idx}"] = [
                'topic' => $evt->topic,
                'aggregate_id' => $evt->aggregate_id,
                'payload' => $this->stripNonDeterministicFields((array) $evt->payload),
            ];
        }

        $stateB = [];
        foreach ($eventsB as $idx => $evt) {
            $stateB["step_{$idx}"] = [
                'topic' => $evt->topic,
                'aggregate_id' => $evt->aggregate_id,
                'payload' => $this->stripNonDeterministicFields((array) $evt->payload),
            ];
        }

        $hashA = $this->hashCalculator->computeCanonicalHash($stateA);
        $hashB = $this->hashCalculator->computeCanonicalHash($stateB);

        $discrepancies = [];
        $divergenceTick = null;

        if ($countA !== $countB) {
            $discrepancies[] = "Perbedaan jumlah total event: Run A ({$countA}) vs Run B ({$countB}).";
        }

        $minCount = min($countA, $countB);
        for ($i = 0; $i < $minCount; $i++) {
            $evtA = $eventsA[$i];
            $evtB = $eventsB[$i];

            if ($evtA->topic !== $evtB->topic) {
                $discrepancies[] = "Divergensi topik pada urutan #{$i}: Run A '{$evtA->topic}' vs Run B '{$evtB->topic}'.";
                $divergenceTick = $i;
                break;
            }

            if ($evtA->aggregate_id !== $evtB->aggregate_id) {
                $discrepancies[] = "Divergensi aggregate_id pada urutan #{$i}: Run A '{$evtA->aggregate_id}' vs Run B '{$evtB->aggregate_id}'.";
                $divergenceTick = $i;
                break;
            }
        }

        $isDeterministic = ($hashA === $hashB) && empty($discrepancies);

        return [
            'is_deterministic' => $isDeterministic,
            'run_a_id' => $runA->id,
            'run_b_id' => $runB->id,
            'run_a_event_count' => $countA,
            'run_b_event_count' => $countB,
            'hash_run_a' => $hashA,
            'hash_run_b' => $hashB,
            'discrepancies' => $discrepancies,
            'divergence_tick' => $divergenceTick,
        ];
    }

    /**
     * Menghapus field acak/waktu dinding yang tidak relevan dengan determinisme logika.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function stripNonDeterministicFields(array $payload): array
    {
        unset(
            $payload['wall_time'],
            $payload['execution_ms'],
            $payload['sim_run_id'],
            $payload['_tamper_signature']
        );

        return $payload;
    }
}
