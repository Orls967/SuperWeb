<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\EventSpine;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Algorithms\Events\AggregateSequenceGapDetector;
use Modules\Core\Domain\Models\CoreSpineGap;

/**
 * Action ScanAggregateGapsAction (DR8-4 / A4)
 *
 * Memindai celah nomor urut pada aggregate event stream secara idempoten
 * dan mencatatnya ke tabel core_spine_gaps.
 */
class ScanAggregateGapsAction
{
    public function __construct(
        protected AggregateSequenceGapDetector $gapDetector
    ) {}

    /**
     * Jalankan pemindaian celah urutan pada aggregate tertentu atau semua aggregate yang ada.
     *
     * @return array{gaps_found: int, gaps_resolved: int}
     */
    public function execute(?string $aggregateType = null, ?string $aggregateId = null): array
    {
        $gapsFound = 0;
        $gapsResolved = 0;

        // Ambil data aggregate outbox/spine
        $query = DB::table('core_outbox')
            ->whereNotNull('aggregate_type')
            ->whereNotNull('aggregate_id')
            ->select('id', 'aggregate_type', 'aggregate_id', 'payload');

        if ($aggregateType !== null) {
            $query->where('aggregate_type', $aggregateType);
        }
        if ($aggregateId !== null) {
            $query->where('aggregate_id', $aggregateId);
        }

        $records = $query->orderBy('id', 'asc')->get();
        $grouped = [];

        foreach ($records as $rec) {
            $key = "{$rec->aggregate_type}:{$rec->aggregate_id}";
            $payload = is_array($rec->payload) ? $rec->payload : json_decode((string) $rec->payload, true) ?? [];
            $seq = isset($payload['sequence']) ? (int) $payload['sequence'] : (isset($payload['seq']) ? (int) $payload['seq'] : (int) $rec->id);
            $grouped[$key][] = $seq;
        }

        foreach ($grouped as $key => $seqs) {
            [$aggType, $aggId] = explode(':', $key, 2);

            $result = $this->gapDetector->detect($seqs);
            $detectedGaps = $result['gaps'];

            foreach ($detectedGaps as $gap) {
                [$start, $end] = $gap;

                $existing = CoreSpineGap::where('aggregate_type', $aggType)
                    ->where('aggregate_id', $aggId)
                    ->where('gap_start', $start)
                    ->where('gap_end', $end)
                    ->first();

                if (! $existing) {
                    CoreSpineGap::create([
                        'aggregate_type' => $aggType,
                        'aggregate_id' => $aggId,
                        'gap_start' => $start,
                        'gap_end' => $end,
                        'status' => CoreSpineGap::STATUS_OPEN,
                        'detected_at' => Carbon::now(),
                    ]);
                    $gapsFound++;
                }
            }

            // Periksa apakah ada gap open yang sekarang nomornya sudah lengkap
            $openGaps = CoreSpineGap::where('aggregate_type', $aggType)
                ->where('aggregate_id', $aggId)
                ->where('status', CoreSpineGap::STATUS_OPEN)
                ->get();

            foreach ($openGaps as $openGap) {
                $isStillGap = false;
                foreach ($detectedGaps as $dGap) {
                    if ($dGap[0] === $openGap->gap_start && $dGap[1] === $openGap->gap_end) {
                        $isStillGap = true;
                        break;
                    }
                }

                if (! $isStillGap) {
                    $openGap->status = CoreSpineGap::STATUS_RESOLVED;
                    $openGap->resolved_at = Carbon::now();
                    $openGap->save();
                    $gapsResolved++;
                }
            }
        }

        return [
            'gaps_found' => $gapsFound,
            'gaps_resolved' => $gapsResolved,
        ];
    }
}
