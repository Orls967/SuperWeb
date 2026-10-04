<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Supplier\Domain\Models\Supplier;

class Quote extends Model
{
    protected $table = 'prc_quotes';

    protected $fillable = [
        'rfq_id', 'supplier_id', 'total_price_idr', 'currency', 'lead_time_days',
        'payment_terms', 'notes', 'is_selected', 'selection_reason',
    ];

    protected $casts = [
        'total_price_idr' => 'integer', 'lead_time_days' => 'integer', 'is_selected' => 'boolean',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class, 'rfq_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
