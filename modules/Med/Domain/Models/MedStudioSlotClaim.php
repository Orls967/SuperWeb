<?php

declare(strict_types=1);

namespace Modules\Med\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedStudioSlotClaim extends Model
{
    use HasUuids;

    protected $table = 'med_studio_slot_claims';

    protected $fillable = [
        'id',
        'studio_id',
        'slot_start',
        'slot_end',
        'booking_id',
    ];

    protected $casts = [
        'slot_start' => 'datetime',
        'slot_end' => 'datetime',
    ];

    public function studio(): BelongsTo
    {
        return $this->belongsTo(MedStudio::class, 'studio_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(MedStudioBooking::class, 'booking_id');
    }
}
