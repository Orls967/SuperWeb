<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashPoolSweep extends Model
{
    use HasUuids;

    protected $table = 'trs_cash_pool_sweeps';

    protected $fillable = [
        'pool_id',
        'sweep_date',
        'swept_amount_idr',
        'direction',
        'header_balance_before',
        'header_balance_after',
        'sub_balance_before',
        'sub_balance_after',
        'ledger_tx_id',
        'intercompany_loan_id',
        'created_by',
    ];

    protected $casts = [
        'sweep_date' => 'date',
        'swept_amount_idr' => 'integer',
        'header_balance_before' => 'integer',
        'header_balance_after' => 'integer',
        'sub_balance_before' => 'integer',
        'sub_balance_after' => 'integer',
        'ledger_tx_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function pool(): BelongsTo
    {
        return $this->belongsTo(CashPool::class, 'pool_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
