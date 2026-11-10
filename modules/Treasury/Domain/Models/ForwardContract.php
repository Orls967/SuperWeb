<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Treasury\Domain\Enums\HedgeStatus;

class ForwardContract extends Model
{
    use HasUuids;

    protected $table = 'trs_forward_contracts';

    protected $fillable = [
        'contract_number',
        'currency',
        'creator_id',
        'notional_foreign_amount',
        'forward_rate_scaled',
        'contract_rate_numerator',
        'contract_rate_denominator',
        'spot_rate_numerator',
        'spot_rate_denominator',
        'maturity_date',
        'mtm_value_idr',
        'status',
        'rolled_to_contract_id',
        'rolled_from_contract_id',
        'settlement_amount_idr',
        'settlement_gain_loss_idr',
        'cancellation_fee_payer',
        'cancellation_fee_idr',
        'rejection_reason',
    ];

    protected $casts = [
        'creator_id' => 'integer',
        'notional_foreign_amount' => 'integer',
        'forward_rate_scaled' => 'integer',
        'contract_rate_numerator' => 'integer',
        'contract_rate_denominator' => 'integer',
        'spot_rate_numerator' => 'integer',
        'spot_rate_denominator' => 'integer',
        'maturity_date' => 'date',
        'mtm_value_idr' => 'integer',
        'status' => HedgeStatus::class,
        'settlement_amount_idr' => 'integer',
        'settlement_gain_loss_idr' => 'integer',
        'cancellation_fee_idr' => 'integer',
    ];

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ForwardContractHistory::class, 'forward_contract_id')->orderBy('created_at', 'asc');
    }

    public function rolledTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rolled_to_contract_id');
    }

    public function rolledFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rolled_from_contract_id');
    }
}
