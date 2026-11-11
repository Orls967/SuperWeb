<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetHistory extends Model
{
    protected $table = 'ef_budget_histories';

    protected $fillable = [
        'budget_id',
        'from_status',
        'to_status',
        'reason',
        'operator_id',
    ];

    protected $casts = [
        'budget_id' => 'integer',
        'operator_id' => 'integer',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(EnterpriseBudget::class, 'budget_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
