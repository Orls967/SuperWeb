<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MurabahahSchedule extends Model
{
    public const STATUS_UNPAID = 'UNPAID';

    public const STATUS_PAID = 'PAID';

    protected $table = 'syb_murabahah_schedules';

    protected $fillable = [
        'contract_id',
        'installment_no',
        'due_date',
        'principal_component_idr',
        'margin_component_idr',
        'installment_amount_idr',
        'paid_amount_idr',
        'status',
        'paid_at',
        'payment_idempotency_key',
    ];

    protected $casts = [
        'contract_id' => 'integer',
        'installment_no' => 'integer',
        'due_date' => 'date',
        'principal_component_idr' => 'integer',
        'margin_component_idr' => 'integer',
        'installment_amount_idr' => 'integer',
        'paid_amount_idr' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(MurabahahContract::class, 'contract_id');
    }
}
