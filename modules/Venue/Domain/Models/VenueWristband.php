<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class VenueWristband extends Model
{
    protected $table = 'ven_wristbands';

    protected $guarded = ['id'];

    protected $casts = [
        'issued_at' => 'datetime',
        'last_tapped_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTicket::class, 'ticket_id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(VenueCashlessWallet::class, 'wristband_id');
    }
}
