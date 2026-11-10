<?php

declare(strict_types=1);

namespace Modules\Core\Application\Projections;

use Modules\Core\Domain\Algorithms\Events\ProjectionHashCalculator;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Pengelola Proyeksi Event Spine (SpineProjectionManager)
 *
 * Mendaftarkan dan memanggil sekumpulan reducer proyeksi terdaftar,
 * merekonstruksi status read-model dari event stream,
 * dan menghasilkan hash kanonikal SHA-256 keadaan akhir.
 */
class SpineProjectionManager
{
    /**
     * @var array<string, ProjectionReducerInterface>
     */
    protected array $reducers = [];

    public function __construct(
        protected ProjectionHashCalculator $hashCalculator
    ) {}

    /**
     * Mendaftarkan reducer proyeksi.
     */
    public function registerReducer(ProjectionReducerInterface $reducer): self
    {
        foreach ($reducer->supportedTopics() as $topic) {
            $this->reducers[$topic] = $reducer;
        }

        return $this;
    }

    /**
     * Menjalankan proyeksi atas kumpulan event spine yang diberikan.
     *
     * @param  iterable<SimEventSpine>  $events
     * @return array{state: array<string, mixed>, hash: string, count: int}
     */
    public function project(iterable $events): array
    {
        $state = [
            'metrics' => [
                'total_events' => 0,
                'total_amount' => 0,
            ],
            'domains' => [],
        ];

        $count = 0;

        foreach ($events as $event) {
            $count++;
            $state['metrics']['total_events']++;

            $reducer = $this->resolveReducer($event);
            if ($reducer !== null) {
                $state = $reducer->reduce($state, $event);
            }
        }

        $hash = $this->hashCalculator->computeHash($state);

        return [
            'state' => $state,
            'hash' => $hash,
            'count' => $count,
        ];
    }

    /**
     * Mencari reducer yang cocok untuk event ini.
     */
    protected function resolveReducer(SimEventSpine $event): ?ProjectionReducerInterface
    {
        if (isset($this->reducers[$event->event_name])) {
            return $this->reducers[$event->event_name];
        }

        if (isset($this->reducers[$event->topic])) {
            return $this->reducers[$event->topic];
        }

        // Cek wildcard prefix (misal store.*)
        foreach ($this->reducers as $pattern => $reducer) {
            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -2);
                if (str_starts_with($event->topic, $prefix) || str_starts_with($event->event_name, $prefix)) {
                    return $reducer;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, ProjectionReducerInterface>
     */
    public function getRegisteredReducers(): array
    {
        return $this->reducers;
    }
}

