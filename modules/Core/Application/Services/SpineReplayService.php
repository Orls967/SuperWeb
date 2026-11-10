<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Closure;
use Modules\Core\Contracts\EventSpineInterface;
use Modules\Core\Domain\Algorithms\Events\ProjectionHashCalculator;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Service SpineReplayService (W8-D39 / DR8-3)
 *
 * Mengelola replay aliran event spine secara deterministik tanpa efek keluar sistem,
 * mengakumulasikan proyeksi menggunakan reducer deterministik,
 * dan menghasilkan hash kanonikal SHA-256 state akhir.
 */
class SpineReplayService
{
    /**
     * @var array<string, Closure>
     */
    protected static array $customReducers = [];

    public function __construct(
        protected EventSpineInterface $spine,
        protected ProjectionHashCalculator $hashCalculator
    ) {}

    /**
     * Daftarkan custom projection reducer untuk grup atau topik.
     */
    public static function registerReducer(string $key, Closure $reducer): void
    {
        self::$customReducers[$key] = $reducer;
    }

    /**
     * Jalankan deterministik replay stream dari fromOffset hingga toOffset.
     *
     * @return array{events_count: int, projection: array<string, mixed>, projection_hash: string}
     */
    public function replay(string $group, string $topic = '*', int $fromOffset = 0, ?int $toOffset = null): array
    {
        $query = SimEventSpine::where('id', '>', $fromOffset)->orderBy('id', 'asc');

        if ($toOffset !== null) {
            $query->where('id', '<=', $toOffset);
        }

        if ($topic !== '*') {
            if (str_ends_with($topic, '.*')) {
                $prefix = substr($topic, 0, -2);
                $query->where(function ($q) use ($prefix) {
                    $q->where('topic', $prefix)->orWhere('topic', 'like', "{$prefix}.%");
                });
            } else {
                $query->where('topic', $topic);
            }
        }

        $events = $query->get();

        $state = [
            'total_replayed' => 0,
            'event_counts' => [],
            'aggregates' => [],
            'last_event_id' => null,
        ];

        $reducer = self::$customReducers[$group] ?? self::$customReducers[$topic] ?? null;

        foreach ($events as $event) {
            $state['total_replayed']++;
            $eventName = $event->event_name;
            $state['event_counts'][$eventName] = ($state['event_counts'][$eventName] ?? 0) + 1;
            $state['last_event_id'] = $event->event_id;

            $payload = (array) $event->payload;
            if (isset($payload['amount']) && is_numeric($payload['amount'])) {
                $state['aggregates']['total_amount'] = ($state['aggregates']['total_amount'] ?? 0) + (int) $payload['amount'];
            }

            if ($reducer instanceof Closure) {
                $state = $reducer($state, $event);
            }
        }

        $projectionHash = $this->hashCalculator->computeHash($state);

        return [
            'events_count' => count($events),
            'projection' => $state,
            'projection_hash' => $projectionHash,
        ];
    }
}
