<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TakafulFund extends Model
{
    protected $table = 'ins_tk_funds';

    protected $fillable = [
        'fund_code',
        'fund_type',
        'name',
        'balance_idr',
        'total_contributions_idr',
        'total_claims_paid_idr',
        'contingency_reserve_idr',
        'is_active',
    ];

    protected $casts = [
        'balance_idr' => 'integer',
        'total_contributions_idr' => 'integer',
        'total_claims_paid_idr' => 'integer',
        'contingency_reserve_idr' => 'integer',
        'is_active' => 'boolean',
    ];

    public function participants(): HasMany
    {
        return $this->hasMany(TakafulParticipant::class, 'fund_id');
    }

    public function surplusDistributions(): HasMany
    {
        return $this->hasMany(TakafulSurplusDistribution::class, 'fund_id');
    }
}
