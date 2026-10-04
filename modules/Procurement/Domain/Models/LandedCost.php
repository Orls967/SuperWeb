<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandedCost extends Model
{
    use HasUuids;

    protected $table = 'prc_landed_costs';

    protected $fillable = ['po_id', 'kind', 'amount_idr', 'allocation_method', 'status', 'notes'];

    protected $casts = ['amount_idr' => 'integer'];

    public function po(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }
}
