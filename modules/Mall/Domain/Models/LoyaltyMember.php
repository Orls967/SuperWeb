<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Mall\Domain\Enums\LoyaltyTier;
use Modules\Shared\Domain\Traits\HasUuid;

class LoyaltyMember extends Model
{
    use HasUuid;

    protected $table = 'mall_loyalty_members';

    protected $fillable = [
        'uuid',
        'user_id',
        'tier',
        'lifetime_spend',
        'current_year_spend',
        'tier_expires_at',
    ];

    protected $casts = [
        'tier' => LoyaltyTier::class,
        'lifetime_spend' => 'integer',
        'current_year_spend' => 'integer',
        'tier_expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Hitung kenaikan tier otomatis berdasarkan akumulasi belanja tahun berjalan.
     */
    public function recalculateTier(): void
    {
        $spend = $this->current_year_spend;

        if ($spend >= LoyaltyTier::PLATINUM->minSpendThreshold()) {
            $this->tier = LoyaltyTier::PLATINUM;
            $this->tier_expires_at = now()->addYear();
        } elseif ($spend >= LoyaltyTier::GOLD->minSpendThreshold()) {
            $this->tier = LoyaltyTier::GOLD;
            $this->tier_expires_at = now()->addYear();
        } else {
            $this->tier = LoyaltyTier::SILVER;
        }

        $this->save();
    }
}
