<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Revaluation extends Model
{
    use HasUuids;

    protected $table = 'trs_revaluations';

    protected $fillable = [
        'period',
        'currency',
        'foreign_balance',
        'book_functional_idr',
        'revalued_functional_idr',
        'gain_loss_idr',
        'ledger_transaction_id',
        'status',
    ];

    protected $casts = [
        'foreign_balance' => 'integer',
        'book_functional_idr' => 'integer',
        'revalued_functional_idr' => 'integer',
        'gain_loss_idr' => 'integer',
        'ledger_transaction_id' => 'integer',
    ];
}
