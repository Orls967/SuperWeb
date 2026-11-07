<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningContractorPaymentHold extends Model
{
    use HasUuids;

    protected $table = 'min_contractor_payment_holds';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'hold_reference',
        'contractor_party_ref',
        'contractor_party_id',
        'case_id',
        'hold_amount_idr',
        'released_amount_idr',
        'deducted_amount_idr',
        'status',
        'idempotency_key',
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
        'hold_amount_idr' => 'integer',
        'released_amount_idr' => 'integer',
        'deducted_amount_idr' => 'integer',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(MiningFuelAnomalyCase::class, 'case_id');
    }
}
