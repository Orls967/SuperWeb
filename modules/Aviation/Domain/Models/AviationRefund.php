<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AviationRefund extends Model
{
    protected $table = 'avi_refunds';

    protected $fillable = [
        'ticket_id',
        'original_fare_minor',
        'penalty_minor',
        'admin_fee_minor',
        'tax_refund_minor',
        'net_refund_minor',
        'currency',
        'reason',
        'idempotency_key',
        'processed_by',
    ];

    protected $casts = [
        'original_fare_minor' => 'integer',
        'penalty_minor' => 'integer',
        'admin_fee_minor' => 'integer',
        'tax_refund_minor' => 'integer',
        'net_refund_minor' => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(AviationTicket::class, 'ticket_id');
    }
}

