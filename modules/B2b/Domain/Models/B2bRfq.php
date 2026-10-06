<?php

namespace Modules\B2b\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bRfq extends Model
{
    use HasUuids;

    protected $table = 'b2b_rfqs';

    protected $fillable = [
        'rfq_number',
        'buyer_id',
        'vendor_id',
        'catalog_id',
        'requested_quantity',
        'target_price_idr',
        'payment_terms',
        'status',
        'agreed_price_idr',
        'notes',
    ];

    protected $casts = [
        'requested_quantity' => 'integer',
        'target_price_idr' => 'integer',
        'agreed_price_idr' => 'integer',
    ];

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(WholesaleCatalog::class, 'catalog_id');
    }
}
