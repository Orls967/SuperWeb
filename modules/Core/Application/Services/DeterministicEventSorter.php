<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Pengurut Event Deterministik Multikunci (DeterministicEventSorter)
 *
 * Mengurutkan koleksi event spine secara stabil dan deterministik lintas mesin dan sistem operasi:
 * Kunci 1: timestamp `occurred_at` (kronologi bisnis)
 * Kunci 2: `sequence` monotonik per aggregate
 * Kunci 3: `id` monotonik basis data (tie-breaker)
 */
class DeterministicEventSorter
{
    /**
     * Mengurutkan daftar event spine secara deterministik tanpa merusak urutan asli untuk elemen setara.
     *
     * @param  iterable<SimEventSpine>  $events
     * @return array<int, SimEventSpine>
     */
    public function sort(iterable $events): array
    {
        $list = is_array($events) ? $events : iterator_to_array($events);

        usort($list, function (SimEventSpine $a, SimEventSpine $b): int {
            // 1. Bandingkan occurred_at jika ada
            $timeA = $this->resolveTimestamp($a);
            $timeB = $this->resolveTimestamp($b);

            if ($timeA !== $timeB) {
                return $timeA <=> $timeB;
            }

            // 2. Bandingkan sequence jika aggregate sama
            $aggA = (string) ($a->aggregate_id ?? '');
            $aggB = (string) ($b->aggregate_id ?? '');

            if ($aggA === $aggB && ! empty($aggA)) {
                $seqA = (int) ($a->sequence ?? 0);
                $seqB = (int) ($b->sequence ?? 0);

                if ($seqA !== $seqB) {
                    return $seqA <=> $seqB;
                }
            }

            // 3. Tie-breaker mutlak menggunakan database primary key ID
            return (int) $a->id <=> (int) $b->id;
        });

        return array_values($list);
    }

    /**
     * Memvalidasi apakah sekumpulan event sudah berada dalam urutan yang tepat secara monotonik.
     *
     * @param  array<int, SimEventSpine>  $events
     */
    public function isMonotonicallyOrdered(array $events): bool
    {
        $count = count($events);
        if ($count <= 1) {
            return true;
        }

        for ($i = 0; $i < $count - 1; $i++) {
            $curr = $events[$i];
            $next = $events[$i + 1];

            $timeA = $this->resolveTimestamp($curr);
            $timeB = $this->resolveTimestamp($next);

            if ($timeA > $timeB) {
                return false;
            }

            if ($timeA === $timeB && (int) $curr->id > (int) $next->id) {
                return false;
            }
        }

        return true;
    }

    protected function resolveTimestamp(SimEventSpine $event): int
    {
        if ($event->created_at) {
            return $event->created_at->timestamp;
        }

        return 0;
    }
}
