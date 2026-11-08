<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AviationLoyaltyAccount extends Model
{
    protected $table = 'avi_loyalty_accounts';

    protected $fillable = [
        'airline_id',
        'passenger_party_ref',
        'membership_number',
        'current_tier',
        'tier_miles',
        'redeemable_miles',
        'status',
        'tier_expiry_date',
    ];

    protected $casts = [
        'tier_miles' => 'integer',
        'redeemable_miles' => 'integer',
        'tier_expiry_date' => 'date',
    ];

    public function airline(): BelongsTo
    {
        return $this->belongsTo(AviationAirline::class, 'airline_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AviationLoyaltyTransaction::class, 'loyalty_account_id');
    }

    public function isElit(): bool
    {
        return in_array($this->current_tier, ['silver', 'gold', 'platinum'], true);
    }
}
