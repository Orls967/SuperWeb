<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EliminationEntry extends Model
{
    protected $table = 'ic_elimination_entries';

    protected $fillable = [
        'elimination_code',
        'period',
        'elimination_type',
        'debit_account',
        'credit_account',
        'amount_idr',
        'status',
    ];

    protected $casts = [
        'amount_idr' => 'integer',
    ];
}
