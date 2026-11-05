<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InsurancePremiumSchedule extends Model
{
    protected $table = 'ins_full_schedules';

    protected $fillable = [
        'policy_id',
        'installment_number',
        'due_date',
        'amount_idr',
        'payment_status',
        'paid_at',
    ];

    protected $casts = [
        'installment_number' => 'integer',
        'due_date' => 'date',
        'amount_idr' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(InsurancePolicyPenuh::class, 'policy_id');
    }
}
