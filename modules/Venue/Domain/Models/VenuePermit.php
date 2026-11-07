<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenuePermit extends Model
{
    protected $table = 'ven_permits';

    protected $fillable = [
        'permit_number',
        'event_id',
        'issuing_authority',
        'max_legal_capacity',
        'valid_from',
        'valid_until',
        'status',
        'revocation_reason',
    ];

    protected $casts = [
        'max_legal_capacity' => 'integer',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EntertainmentEvent::class, 'event_id');
    }
}

