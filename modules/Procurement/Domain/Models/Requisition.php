<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Party\Domain\Models\LegalEntity;

class Requisition extends Model
{
    use HasUuids;

    protected $table = 'prc_requisitions';

    protected $fillable = [
        'number', 'title', 'notes', 'source', 'budget_center_id', 'legal_entity_id',
        'status', 'approval_id', 'total_estimated_idr', 'requested_by_user_id', 'approved_at',
    ];

    protected $casts = [
        'budget_center_id' => 'integer',
        'approval_id' => 'integer',
        'total_estimated_idr' => 'integer',
        'requested_by_user_id' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(RequisitionLine::class, 'requisition_id');
    }

    public function budgetCenter(): BelongsTo
    {
        return $this->belongsTo(BudgetCenter::class, 'budget_center_id');
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class, 'legal_entity_id');
    }
}
