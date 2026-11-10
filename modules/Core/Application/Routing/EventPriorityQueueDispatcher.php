<?php

declare(strict_types=1);

namespace Modules\Core\Application\Routing;

use Modules\Core\Domain\Models\SimEventSpine;
use SplPriorityQueue;

/**
 * Antrean Dispatcher Berbasis Prioritas (EventPriorityQueueDispatcher)
 *
 * Mengatur urutan pengiriman batch event dengan mempertimbangkan prioritas pesan
 * (urgent / critical > high > normal > bulk/low) dengan tie-breaking FIFO berurutan ID.
 */
class EventPriorityQueueDispatcher
{
    public const PRIORITY_CRITICAL = 100;

    public const PRIORITY_HIGH = 75;

    public const PRIORITY_NORMAL = 50;

    public const PRIORITY_LOW = 25;

    /**
     * Mengurutkan sekumpulan event spine berdasarkan prioritas dan sequence ID.
     *
     * @param  iterable<SimEventSpine>  $events
     * @return array<int, SimEventSpine>
     */
    public function prioritize(iterable $events): array
    {
        $pq = new class extends SplPriorityQueue
        {
            /**
             * @param  array{priority: int, seq: int}  $priority1
             * @param  array{priority: int, seq: int}  $priority2
             */
            public function compare(mixed $priority1, mixed $priority2): int
            {
                // Prioritas lebih tinggi dieksekusi lebih dulu
                if ($priority1['priority'] !== $priority2['priority']) {
                    return $priority1['priority'] <=> $priority2['priority'];
                }

                // Jika prioritas sama, FIFO: seq ID lebih kecil dieksekusi lebih dulu
                return $priority2['seq'] <=> $priority1['seq'];
            }
        };

        foreach ($events as $event) {
            $priority = $this->resolveEventPriority($event);
            $pq->insert($event, [
                'priority' => $priority,
                'seq' => (int) $event->id,
            ]);
        }

        $ordered = [];
        while (! $pq->isEmpty()) {
            $ordered[] = $pq->extract();
        }

        return $ordered;
    }

    /**
     * Menentukan skor numerik prioritas berdasarkan topik atau payload.
     */
    public function resolveEventPriority(SimEventSpine $event): int
    {
        $payload = (array) $event->payload;

        if (isset($payload['priority'])) {
            $p = strtolower((string) $payload['priority']);

            return match ($p) {
                'critical', 'emergency', 'urgent' => self::PRIORITY_CRITICAL,
                'high' => self::PRIORITY_HIGH,
                'low', 'bulk' => self::PRIORITY_LOW,
                default => self::PRIORITY_NORMAL,
            };
        }

        // Topik rumah sakit darurat code blue selalu critical
        if (str_contains($event->topic, 'code_blue') || str_contains($event->event_name, 'code_blue')) {
            return self::PRIORITY_CRITICAL;
        }

        // Topik anomali dan breach suhu selalu high
        if (str_contains($event->topic, 'anomaly') || str_contains($event->topic, 'breached')) {
            return self::PRIORITY_HIGH;
        }

        return self::PRIORITY_NORMAL;
    }
}
