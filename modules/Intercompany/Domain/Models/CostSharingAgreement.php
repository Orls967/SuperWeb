<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostSharingAgreement extends Model
{
    protected $table = 'ic_cost_sharing_agreements';

    protected $fillable = [
        'agreement_code',
        'agreement_name',
        'service_type',
        'provider_entity',
        'provider_entity_id',
        'total_cost_pool_idr',
        'markup_bps',
        'allocation_basis',
        'service_category',
        'effective_date',
        'expiry_date',
        'description',
        'is_active',
    ];

    protected $casts = [
        'markup_bps' => 'integer',
        'is_active' => 'boolean',
    ];

    public function providerEntity(): BelongsTo
    {
        return $this->belongsTo(IntercompanyEntity::class, 'provider_entity_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CostSharingAllocation::class, 'agreement_id');
    }
}
