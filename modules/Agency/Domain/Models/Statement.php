<?php

declare(strict_types=1);

namespace Modules\Agency\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pernyataan komisi agen per periode (45.8). */
class Statement extends Model
{
    protected $table = 'agy_statements';

    protected $fillable = [
        'agent_id', 'period', 'opening_balance_idr', 'accrued_idr', 'clawback_idr',
        'paid_idr', 'closing_balance_idr', 'breakdown',
    ];

    protected $casts = [
        'opening_balance_idr' => 'integer', 'accrued_idr' => 'integer', 'clawback_idr' => 'integer',
        'paid_idr' => 'integer', 'closing_balance_idr' => 'integer', 'breakdown' => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }
}
