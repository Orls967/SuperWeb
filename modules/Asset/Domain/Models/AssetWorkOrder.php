<?php

declare(strict_types=1);

namespace Modules\Asset\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetWorkOrder extends Model
{
    protected $table = 'ast_work_orders';

    protected $fillable = [
        'asset_id', 'number', 'type', 'trigger', 'status', 'due_date', 'due_units', 'completed_units',
        'completed_at', 'parts_cost_idr', 'labor_cost_idr', 'total_cost_idr', 'cost_treatment',
        'description', 'vendor', 'assigned_to_user_id', 'ledger_transaction_id',
    ];

    protected $casts = [
        'due_date' => 'date', 'completed_at' => 'date', 'due_units' => 'integer', 'completed_units' => 'integer',
        'parts_cost_idr' => 'integer', 'labor_cost_idr' => 'integer', 'total_cost_idr' => 'integer',
        'assigned_to_user_id' => 'integer', 'ledger_transaction_id' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
