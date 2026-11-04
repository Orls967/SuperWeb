<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MurabahahContract extends Model
{
    public const STATUS_PROPOSED = 'PROPOSED';

    public const STATUS_OFFICER_APPROVED = 'OFFICER_APPROVED';

    public const STATUS_DPS_APPROVED = 'DPS_APPROVED';

    public const STATUS_ASSET_PURCHASED = 'ASSET_PURCHASED';

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_SETTLED = 'SETTLED';

    protected $table = 'syb_murabahah_financings';

    protected $fillable = [
        'contract_code',
        'customer_user_id',
        'supplier_party_ref',
        'asset_description',
        'cost_price_idr',
        'margin_amount_idr',
        'total_selling_price_idr',
        'tenor_months',
        'status',
        'proposed_by_user_id',
        'officer_approved_by_user_id',
        'officer_approved_at',
        'dps_reviewer_user_id',
        'dps_reviewed_at',
        'dps_opinion_note',
        'purchased_at',
        'signed_at',
        'settled_at',
        'total_paid_idr',
        'recognized_margin_idr',
        'total_tazir_idr',
        'contract_hash',
        'idempotency_key',
    ];

    protected $casts = [
        'customer_user_id' => 'integer',
        'supplier_party_ref' => 'integer',
        'cost_price_idr' => 'integer',
        'margin_amount_idr' => 'integer',
        'total_selling_price_idr' => 'integer',
        'tenor_months' => 'integer',
        'proposed_by_user_id' => 'integer',
        'officer_approved_by_user_id' => 'integer',
        'officer_approved_at' => 'datetime',
        'dps_reviewer_user_id' => 'integer',
        'dps_reviewed_at' => 'datetime',
        'purchased_at' => 'datetime',
        'signed_at' => 'datetime',
        'settled_at' => 'datetime',
        'total_paid_idr' => 'integer',
        'recognized_margin_idr' => 'integer',
        'total_tazir_idr' => 'integer',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(MurabahahSchedule::class, 'contract_id')->orderBy('installment_no');
    }

    public function tazirPenalties(): HasMany
    {
        return $this->hasMany(TazirPenalty::class, 'contract_id')->orderBy('id');
    }
}
