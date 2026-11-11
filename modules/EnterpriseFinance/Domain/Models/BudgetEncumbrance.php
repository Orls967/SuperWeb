<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetEncumbrance extends Model
{
    use HasUuids;

    protected $table = 'ef_budget_encumbrances';

    protected $fillable = [
        'id',
        'budget_id',
        'commitment_code',
        'source_module',
        'reference_number',
        'amount_minor',
        'liquidated_amount_minor',
        'status',
        'reserved_at',
        'liquidated_at',
        'released_at',
        'reason',
        'payload',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'liquidated_amount_minor' => 'integer',
        'reserved_at' => 'datetime',
        'liquidated_at' => 'datetime',
        'released_at' => 'datetime',
        'payload' => 'array',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(EnterpriseBudget::class, 'budget_id');
    }
}
