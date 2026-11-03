<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class EntertainmentTicket extends Model
{
    protected $table = 'ven_entertainment_tickets';

    protected $fillable = [
        'ticket_uuid',
        'ticket_number',
        'event_id',
        'tier_id',
        'seat_claim_id',
        'buyer_user_id',
        'price_paid_idr',
        'ticket_hash',
        'prev_hash',
        'idempotency_key',
        'status',
        'scanned_at',
    ];

    protected $casts = [
        'price_paid_idr' => 'integer',
        'scanned_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new RuntimeException('Fakta tiket bersifat append-only dan tidak boleh dihapus (PR5)');
        });

        static::updating(function (self $ticket): void {
            if ($ticket->isDirty(['price_paid_idr', 'ticket_hash', 'prev_hash', 'ticket_number', 'ticket_uuid', 'event_id'])) {
                throw new RuntimeException('Field finansial dan integritas hash tiket tidak boleh diubah (PR5)');
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(EntertainmentEvent::class, 'event_id');
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(TicketTier::class, 'tier_id');
    }

    public function seatClaim(): BelongsTo
    {
        return $this->belongsTo(SeatClaim::class, 'seat_claim_id');
    }
}
