<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportProfile extends Model
{
    protected $table = 'prc_import_profiles';

    protected $fillable = [
        'po_id', 'incoterm', 'currency', 'fx_rate', 'origin_port', 'destination_port',
        'freight_estimate_idr', 'insurance_estimate_idr', 'duty_estimate_idr',
        'landed_cost_estimate_idr', 'notes',
    ];

    protected $casts = [
        'fx_rate' => 'decimal:6', 'freight_estimate_idr' => 'integer',
        'insurance_estimate_idr' => 'integer', 'duty_estimate_idr' => 'integer',
        'landed_cost_estimate_idr' => 'integer',
    ];

    public function po(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }
}
