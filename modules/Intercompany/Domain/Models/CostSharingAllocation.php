<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostSharingAllocation extends Model
{
    protected $table = 'ic_cost_sharing_allocations';

    protected $fillable = [
        'agreement_id',
        'period',
        'beneficiary_entity',
        'beneficiary_entity_id',
        'basis_ratio_permille',
        'allocation_ratio_permille',
        'allocated_amount_idr',
        'allocated_cost_idr',
        'markup_amount_idr',
        'total_billed_idr',
        'status',
        'notes',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'allocation_ratio_permille' => 'integer',
        'allocated_cost_idr' => 'integer',
        'markup_amount_idr' => 'integer',
        'total_billed_idr' => 'integer',
    ];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(CostSharingAgreement::class, 'agreement_id');
    }

    public function beneficiaryEntity(): BelongsTo
    {
        return $this->belongsTo(IntercompanyEntity::class, 'beneficiary_entity_id');
    }
}
