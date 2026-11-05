<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Insurance\Domain\Concerns\AppendOnly;

class CatBondEvent extends Model
{
    use AppendOnly;

    protected $table = 'ins_cat_bond_events';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'bond_id',
        'event_key',
        'industry_loss_idr',
        'reduction_idr',
        'outstanding_after_idr',
        'ledger_transaction_id',
        'occurred_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }
}
