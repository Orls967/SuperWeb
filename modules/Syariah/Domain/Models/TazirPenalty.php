<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TazirPenalty extends Model
{
    protected $table = 'syb_tazir_penalties';

    protected $fillable = [
        'contract_id',
        'schedule_id',
        'customer_user_id',
        'amount_idr',
        'reason',
        'idempotency_key',
        'collected_at',
    ];

    protected $casts = [
        'contract_id' => 'integer',
        'schedule_id' => 'integer',
        'customer_user_id' => 'integer',
        'amount_idr' => 'integer',
        'collected_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(MurabahahContract::class, 'contract_id');
    }
}
