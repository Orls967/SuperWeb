<?php

declare(strict_types=1);

namespace Modules\Supplier\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierRiskFlag extends Model
{
    protected $table = 'sup_risk_flags';

    protected $fillable = [
        'supplier_id', 'type', 'severity', 'message', 'meta', 'is_open', 'resolved_at',
    ];

    protected $casts = ['meta' => 'array', 'is_open' => 'boolean', 'resolved_at' => 'datetime'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
