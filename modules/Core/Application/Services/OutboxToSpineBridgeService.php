<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Carbon\Carbon;
use Modules\Core\Contracts\EventSpineInterface;
use Modules\Core\Domain\Models\OutboxMessage;

/**
 * Service OutboxToSpineBridgeService (DR8-1 / W8-D36)
 *
 * Menjembatani pesan transactional outbox (core_outbox) menuju Universal Event Spine (sim_event_spine).
 * Memproses pesan berstatus pending secara batch, menjamin aggregate sequencing dan idempotensi.
 */
class OutboxToSpineBridgeService
{
    public function __construct(
        protected EventSpineInterface $spine
    ) {}

    /**
     * Memproses sekumpulan pesan outbox yang pending dan memancarkannya ke spine.
     *
     * @return array{dispatched_count: int, failed_count: int}
     */
    public function syncPendingOutboxToSpine(int $batchSize = 100): array
    {
        $messages = OutboxMessage::where('status', 'pending')
            ->orderBy('id', 'asc')
            ->limit($batchSize)
            ->get();

        $dispatched = 0;
        $failed = 0;

        foreach ($messages as $msg) {
            try {
                $payload = is_array($msg->payload) ? $msg->payload : json_decode((string) $msg->payload, true) ?? [];
                $topic = $this->resolveTopicFromEventType($msg->event_type);
                $idempotencyKey = $msg->idempotency_key ?? "outbox-{$msg->id}";

                // Publish ke Event Spine
                $this->spine->publish(
                    topic: $topic,
                    eventName: $msg->event_type,
                    payload: $payload,
                    idempotencyKey: $idempotencyKey,
                    version: 1
                );

                // Update outbox message status
                $msg->status = 'dispatched';
                $msg->dispatched_at = Carbon::now();
                $msg->save();

                $dispatched++;
            } catch (\Throwable $e) {
                $msg->attempts = ($msg->attempts ?? 0) + 1;
                $msg->last_error = $e->getMessage();
                if ($msg->attempts >= 6) {
                    $msg->status = 'dead_letter';
                }
                $msg->save();

                $failed++;
            }
        }

        return [
            'dispatched_count' => $dispatched,
            'failed_count' => $failed,
        ];
    }

    /**
     * Memetakan event_type outbox ke topik event spine standar.
     */
    public function resolveTopicFromEventType(string $eventType): string
    {
        // Format standar: {domain}.{entitas}.{kejadian}.v{n}
        $parts = explode('.', $eventType);
        if (count($parts) >= 2) {
            return "{$parts[0]}.{$parts[1]}";
        }

        return strtolower($eventType);
    }
}
