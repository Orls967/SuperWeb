<?php

declare(strict_types=1);

namespace Modules\Core\Domain\StateMachines;

use Carbon\Carbon;
use DomainException;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Core\Domain\Algorithms\Events\DeterministicBackoff;
use Modules\Core\Domain\Models\CoreEventDelivery;

/**
 * State Machine EventDelivery (DR8-1)
 *
 * Mengatur siklus hidup 7 status pengiriman event:
 * pending -> dispatched -> delivered -> retrying -> dead_lettered -> replayed -> discarded
 */
class EventDeliveryStateMachine
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_RETRYING = 'retrying';

    public const STATUS_DEAD_LETTERED = 'dead_lettered';

    public const STATUS_REPLAYED = 'replayed';

    public const STATUS_DISCARDED = 'discarded';

    public const MAX_ATTEMPTS = 6;

    /**
     * @var array<string, array<int, string>>
     */
    protected const ALLOWED_TRANSITIONS = [
        self::STATUS_PENDING => [
            self::STATUS_DISPATCHED,
        ],
        self::STATUS_DISPATCHED => [
            self::STATUS_DELIVERED,
            self::STATUS_RETRYING,
            self::STATUS_DEAD_LETTERED,
        ],
        self::STATUS_RETRYING => [
            self::STATUS_DISPATCHED,
            self::STATUS_DEAD_LETTERED,
        ],
        self::STATUS_DEAD_LETTERED => [
            self::STATUS_REPLAYED,
            self::STATUS_DISCARDED,
        ],
        self::STATUS_REPLAYED => [
            self::STATUS_DELIVERED,
            self::STATUS_RETRYING,
            self::STATUS_DISPATCHED,
        ],
        self::STATUS_DELIVERED => [],
        self::STATUS_DISCARDED => [],
    ];

    protected DeterministicBackoff $backoff;

    public function __construct(
        ?DeterministicBackoff $backoff = null,
        protected ?SimClockInterface $simClock = null
    ) {
        $this->backoff = $backoff ?? new DeterministicBackoff;
    }

    /**
     * Memeriksa apakah transisi dari status asal ke status tujuan legal.
     */
    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Menjalankan transisi status pada model CoreEventDelivery dengan guard dan efek samping.
     *
     * @throws DomainException
     */
    public function transition(CoreEventDelivery $delivery, string $targetStatus, ?string $reason = null, ?int $actorId = null): void
    {
        $currentStatus = $delivery->status;

        if (! $this->canTransition($currentStatus, $targetStatus)) {
            throw new DomainException("Transisi pengiriman event tidak valid dari [{$currentStatus}] ke [{$targetStatus}].");
        }

        $now = $this->simClock ? $this->simClock->now() : Carbon::now();

        switch ($targetStatus) {
            case self::STATUS_DISPATCHED:
                $delivery->dispatched_at = $now;
                $delivery->status = self::STATUS_DISPATCHED;
                break;

            case self::STATUS_DELIVERED:
                $delivery->delivered_at = $now;
                $delivery->status = self::STATUS_DELIVERED;
                break;

            case self::STATUS_RETRYING:
                $delivery->attempts++;
                $delivery->last_error = $reason ?? 'Unknown processing error';

                // Hitung jeda A5
                $delaySeconds = $this->backoff->calculateDelaySeconds($delivery->message_id, $delivery->attempts);
                $delivery->next_retry_at = (clone $now)->addSeconds($delaySeconds);
                $delivery->status = self::STATUS_RETRYING;
                break;

            case self::STATUS_DEAD_LETTERED:
                $delivery->last_error = $reason ?? 'Max retries exceeded or fatal error';
                $delivery->status = self::STATUS_DEAD_LETTERED;
                break;

            case self::STATUS_REPLAYED:
                if (empty($reason)) {
                    throw new DomainException('Alasan operasional wajib disertakan saat memutar ulang pesan DLQ.');
                }
                $delivery->last_error = "Replayed: {$reason}";
                $delivery->status = self::STATUS_REPLAYED;
                break;

            case self::STATUS_DISCARDED:
                if (empty($reason)) {
                    throw new DomainException('Alasan pembuangan wajib disertakan saat mengabaikan pesan DLQ.');
                }
                $delivery->last_error = "Discarded: {$reason}";
                $delivery->status = self::STATUS_DISCARDED;
                break;
        }

        $delivery->save();
    }
}
