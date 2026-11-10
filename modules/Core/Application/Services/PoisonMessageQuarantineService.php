<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Carbon\Carbon;
use Modules\Core\Domain\Models\CorePoisonMessage;
use Throwable;

/**
 * Service PoisonMessageQuarantineService (W8-D40)
 *
 * Mengkarantina poison message ke core_poison_messages tanpa menahan offset lain.
 */
class PoisonMessageQuarantineService
{
    /**
     * Karantina pesan yang rusak atau memicu exception berulang.
     *
     * @param  array<string, mixed>  $payload
     */
    public function quarantine(
        string $messageId,
        string $consumerGroup,
        string $topic,
        ?string $eventName,
        array $payload,
        string $failureReason,
        ?Throwable $exception = null,
        int $attempts = 3
    ): CorePoisonMessage {
        return CorePoisonMessage::create([
            'message_key' => $messageId,
            'consumer_group' => $consumerGroup,
            'topic' => $topic,
            'event_name' => $eventName,
            'payload' => $payload,
            'failure_reason' => $failureReason,
            'exception_class' => $exception ? get_class($exception) : null,
            'stack_trace' => $exception ? $exception->getTraceAsString() : null,
            'attempts_count' => $attempts,
            'quarantined_at' => Carbon::now(),
        ]);
    }

    /**
     * Memeriksa apakah suatu pesan telah dikarantina.
     */
    public function isQuarantined(string $messageId, string $consumerGroup): bool
    {
        return CorePoisonMessage::where('message_key', $messageId)
            ->where('consumer_group', $consumerGroup)
            ->exists();
    }
}
