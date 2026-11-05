<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SybMicroSchedule extends Model
{
    protected $table = 'syb_micro_schedules';

    protected $fillable = [
        'schedule_code',
        'facility_code',
        'installment_number',
        'due_date',
        'principal_due_idr',
        'margin_due_idr',
        'paid_at',
        'status',
    ];

    protected $casts = [
        'installment_number' => 'integer',
        'due_date' => 'date',
        'principal_due_idr' => 'integer',
        'margin_due_idr' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(SybMicroFacility::class, 'facility_code', 'facility_code');
    }
}
