<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SybIjarahFacilitySchedule extends Model
{
    protected $table = 'syb_ijarah_facility_schedules';

    protected $fillable = [
        'facility_id',
        'period',
        'due_date',
        'ujrah_idr',
        'depreciation_idr',
        'accumulated_depreciation_idr',
        'carrying_book_value_idr',
        'paid_amount_idr',
        'status',
        'paid_at',
        'payment_idempotency_key',
    ];

    protected $casts = [
        'facility_id' => 'integer',
        'period' => 'integer',
        'due_date' => 'date',
        'ujrah_idr' => 'integer',
        'depreciation_idr' => 'integer',
        'accumulated_depreciation_idr' => 'integer',
        'carrying_book_value_idr' => 'integer',
        'paid_amount_idr' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(SybIjarahFacility::class, 'facility_id');
    }
}
