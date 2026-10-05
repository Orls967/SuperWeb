<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class IntercompanyLoan extends Model
{
    protected $table = 'ic_loans';

    protected $guarded = [];

    protected $casts = [
        'principal_idr' => 'integer',
        'repaid_principal_idr' => 'integer',
        'arms_length_interest_rate' => 'float',
        'start_date' => 'date',
        'due_date' => 'date',
    ];
}
