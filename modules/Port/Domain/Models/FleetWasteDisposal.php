<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FleetWasteDisposal extends Model
{
    protected $table = 'prt_fleet_waste_disposals';

    protected $fillable = [
        'port_call_id',
        'marpol_annex',
        'quantity_m3',
        'reception_facility_name',
        'receipt_certificate_number',
        'disposed_at',
    ];

    protected $casts = [
        'quantity_m3' => 'float',
        'disposed_at' => 'datetime',
    ];

    public function portCall(): BelongsTo
    {
        return $this->belongsTo(FleetPortCall::class, 'port_call_id');
    }
}
