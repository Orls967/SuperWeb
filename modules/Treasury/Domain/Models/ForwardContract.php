<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ForwardContract extends Model
{
    use HasUuids;

    protected $table = 'trs_forward_contracts';

    protected $fillable = [
        'contract_number',
        'currency',
        'notional_foreign_amount',
        'forward_rate_scaled',
        'maturity_date',
        'mtm_value_idr',
        'status',
    ];

    protected $casts = [
        'notional_foreign_amount' => 'integer',
        'forward_rate_scaled' => 'integer',
        'maturity_date' => 'date',
        'mtm_value_idr' => 'integer',
    ];
}
