<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Syariah\Domain\Enums\MicroContractType;
use Modules\Syariah\Domain\Enums\MicroFacilityStatus;

class SybMicroFacility extends Model
{
    protected $table = 'syb_micro_facilities';

    protected $fillable = [
        'facility_code',
        'cooperative_code',
        'customer_party_ref',
        'group_code',
        'contract_type',
        'level',
        'principal_amount_idr',
        'outstanding_balance_idr',
        'margin_or_nisbah_bps',
        'tenor_months',
        'status',
        'approved_by',
        'disbursed_at',
    ];

    protected $casts = [
        'contract_type' => MicroContractType::class,
        'status' => MicroFacilityStatus::class,
        'principal_amount_idr' => 'integer',
        'outstanding_balance_idr' => 'integer',
        'margin_or_nisbah_bps' => 'integer',
        'tenor_months' => 'integer',
        'disbursed_at' => 'datetime',
    ];

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(SybBmtCooperative::class, 'cooperative_code', 'cooperative_code');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(SybMicroSchedule::class, 'facility_code', 'facility_code');
    }
}
