<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Mining\Domain\Enums\FuelAnomalyCaseStatus;

class MiningFuelAnomalyCase extends Model
{
    use HasUuids;

    protected $table = 'min_fuel_anomaly_cases';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'case_number',
        'site_id',
        'contractor_party_ref',
        'contractor_party_id',
        'equipment_id',
        'shift_date',
        'shift_code',
        'actual_fuel_ml',
        'baseline_fuel_ml',
        'excess_ml',
        'excess_bps',
        'held_amount_idr',
        'status',
        'dispute_reason',
        'resolution_notes',
    ];

    public function getContractorPartyIdAttribute(): ?string
    {
        return $this->attributes['contractor_party_ref'] ?? null;
    }

    public function setContractorPartyIdAttribute(?string $value): void
    {
        $this->attributes['contractor_party_ref'] = $value;
    }

    protected $casts = [
        'shift_date' => 'date',
        'actual_fuel_ml' => 'integer',
        'baseline_fuel_ml' => 'integer',
        'excess_ml' => 'integer',
        'excess_bps' => 'integer',
        'held_amount_idr' => 'integer',
        'status' => FuelAnomalyCaseStatus::class,
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(MiningEquipment::class, 'equipment_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(MiningFuelAnomalyHistory::class, 'case_id');
    }

    public function paymentHolds(): HasMany
    {
        return $this->hasMany(MiningContractorPaymentHold::class, 'case_id');
    }
}
