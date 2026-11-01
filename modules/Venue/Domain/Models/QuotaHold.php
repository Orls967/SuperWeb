<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $event_id
 * @property int $tier_id
 * @property int $buyer_user_id
 * @property int $quantity
 * @property string $hold_token
 * @property string $idempotency_key
 * @property string $status
 * @property CarbonImmutable $held_until
 */
class QuotaHold extends Model
{
    protected $table = 'ven_quota_holds';

    protected $fillable = [
        'event_id',
        'tier_id',
        'buyer_user_id',
        'quantity',
        'hold_token',
        'idempotency_key',
        'status',
        'held_until',
    ];

    protected $casts = [
        'held_until' => 'immutable_datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EntertainmentEvent::class, 'event_id');
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(TicketTier::class, 'tier_id');
    }

    public function isExpired(CarbonImmutable $now): bool
    {
        return $this->held_until->lessThan($now);
    }
}
