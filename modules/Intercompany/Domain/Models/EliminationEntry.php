<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EliminationEntry extends Model
{
    protected $table = 'ic_elimination_entries';

    protected $fillable = [
        'elimination_code',
        'period',
        'elimination_type',
        'selling_entity',
        'buying_entity',
        'source_entity',
        'target_entity',
        'selling_entity_id',
        'buying_entity_id',
        'debit_account',
        'debit_account_code',
        'credit_account',
        'credit_account_code',
        'amount_idr',
        'debit_amount_idr',
        'credit_amount_idr',
        'status',
        'description',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'amount_idr' => 'integer',
    ];

    public function sellingEntityModel(): BelongsTo
    {
        return $this->belongsTo(IntercompanyEntity::class, 'selling_entity_id');
    }

    public function buyingEntityModel(): BelongsTo
    {
        return $this->belongsTo(IntercompanyEntity::class, 'buying_entity_id');
    }
}
