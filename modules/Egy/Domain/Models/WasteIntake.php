<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WasteIntake extends Model
{
    protected $table = 'egy_waste_intakes';

    protected $fillable = [
        'facility_id',
        'vehicle_number',
        'waste_type',
        'tonnage_kg',
        'tipping_fee_rate_idr_per_ton',
        'total_tipping_fee_idr',
        'projected_biogas_m3',
        'ledger_transaction_id',
        'recorded_at',
        'status',
    ];

    protected $casts = [
        'tonnage_kg' => 'integer',
        'tipping_fee_rate_idr_per_ton' => 'integer',
        'total_tipping_fee_idr' => 'integer',
        'projected_biogas_m3' => 'integer',
        'ledger_transaction_id' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(WasteTreatmentFacility::class, 'facility_id');
    }
}
