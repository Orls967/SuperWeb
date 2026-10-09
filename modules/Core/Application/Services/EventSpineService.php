<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Contracts\EventSpineInterface;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Core\Domain\Models\SimEventSpine;

class EventSpineService implements EventSpineInterface
{
    public function __construct(
        protected SimClockInterface $simClock
    ) {}

    public function publish(
        string $topic,
        string $eventName,
        array $payload,
        string $idempotencyKey,
        int $version = 1
    ): SimEventSpine {
        return DB::transaction(function () use ($topic, $eventName, $payload, $idempotencyKey, $version) {
            $existing = SimEventSpine::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            return SimEventSpine::create([
                'event_id' => (string) Str::uuid(),
                'topic' => $topic,
                'event_name' => $eventName,
                'version' => $version,
                'payload' => $payload,
                'idempotency_key' => $idempotencyKey,
                'occurred_at' => $this->simClock->now(),
            ]);
        });
    }

    public function consume(string $topic, int $fromOffset = 0, int $limit = 100): Collection
    {
        $query = SimEventSpine::where('id', '>', $fromOffset)->orderBy('id', 'asc')->limit($limit);

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

        return $query->get();
    }

    public function commitOffset(string $consumerGroup, string $topic, int $offset): void
    {
        DB::table('sim_event_consumers')->updateOrInsert(
            ['consumer_group' => $consumerGroup, 'topic' => $topic],
            ['last_processed_offset' => $offset, 'updated_at' => Carbon::now()]
        );
    }

    public function getOffset(string $consumerGroup, string $topic): int
    {
        $row = DB::table('sim_event_consumers')
            ->where('consumer_group', $consumerGroup)
            ->where('topic', $topic)
            ->first();

        return $row ? (int) $row->last_processed_offset : 0;
    }
}
