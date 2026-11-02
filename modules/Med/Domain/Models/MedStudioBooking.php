<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedStudioBooking extends Model
{
    protected $table = 'med_studio_bookings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'booking_code',
        'studio_id',
        'project_id',
        'client_entity_id',
        'start_time',
        'end_time',
        'hold_expires_at',
        'hold_amount_minor',
        'total_price_minor',
        'cancellation_fee_minor',
        'idempotency_key',
        'status',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'hold_expires_at' => 'datetime',
        'hold_amount_minor' => 'integer',
        'total_price_minor' => 'integer',
        'cancellation_fee_minor' => 'integer',
    ];

    public function studio(): BelongsTo
    {
        return $this->belongsTo(MedStudio::class, 'studio_id');
    }

    public function slotClaims(): HasMany
    {
        return $this->hasMany(MedStudioSlotClaim::class, 'booking_id');
    }
}
