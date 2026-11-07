<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HotelDigitalKey extends Model
{
    protected $table = 'htl_digital_keys';

    protected $fillable = [
        'key_uuid',
        'reservation_id',
        'room_id',
        'guest_user_id',
        'session_token_hash',
        'qr_payload_encrypted',
        'valid_from',
        'valid_until',
        'first_used_at',
        'revoked_at',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'first_used_at' => 'datetime',
        'revoked_at' => 'datetime',
        'qr_payload_encrypted' => 'encrypted',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(HotelReservation::class, 'reservation_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(HotelRoom::class, 'room_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }
}
