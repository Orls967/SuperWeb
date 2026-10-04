<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetEncumbrance extends Model
{
    protected $table = 'prc_budget_encumbrances';

    protected $fillable = ['budget_center_id', 'source_type', 'source_id', 'amount_idr', 'status'];

    protected $casts = ['budget_center_id' => 'integer', 'amount_idr' => 'integer'];

    public function budgetCenter(): BelongsTo
    {
        return $this->belongsTo(BudgetCenter::class, 'budget_center_id');
    }
}
