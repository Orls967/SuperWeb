<?php

declare(strict_types=1);

namespace Modules\Supplier\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierAsn extends Model
{
    protected $table = 'sup_asns';

    protected $fillable = [
        'supplier_id', 'asn_number', 'purchase_order_id', 'status', 'ship_date',
        'expected_arrival', 'lines', 'tracking_ref', 'created_by_user_id',
    ];

    protected $casts = [
        'purchase_order_id' => 'integer', 'ship_date' => 'date', 'expected_arrival' => 'date', 'lines' => 'array',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
