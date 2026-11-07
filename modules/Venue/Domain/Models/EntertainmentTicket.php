<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'tax_paid_idr',
        'ticket_hash',
        'prev_hash',
        'idempotency_key',
        'status',
        'reentry_count',
        'last_direction',
        'scanned_at',
    ];

    protected $casts = [
        'price_paid_idr' => 'integer',
        'tax_paid_idr' => 'integer',
        'reentry_count' => 'integer',
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

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function scans(): HasMany
    {
        return $this->hasMany(GateScanLog::class, 'ticket_id');
    }

    public function getPriceIdrAttribute(): int
    {
        return (int) ($this->price_paid_idr ?? 0);
    }

    public function resales(): HasMany
    {
        return $this->hasMany(TicketResale::class, 'ticket_id');
    }

    public function activeResale()
    {
        return $this->hasOne(TicketResale::class, 'ticket_id')->whereIn('status', ['LISTED', 'ESCROW_HOLD']);
    }

    public function activeWaitlistReservation()
    {
        return $this->hasOne(WaitlistReservation::class, 'ticket_id')->where('is_claimed', false);
    }
}
