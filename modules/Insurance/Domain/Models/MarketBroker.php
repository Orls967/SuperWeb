<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Party\Contracts\Party;

class MarketBroker extends Model
{
    protected $table = 'ins_market_brokers';

    /** @var list<string> */
    protected $fillable = [
        'party_id',
        'license_number',
        'tier',
        'commission_rate_bps',
        'rating_score',
        'total_gross_written_idr',
        'total_commission_earned_idr',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'commission_rate_bps' => 'integer',
            'rating_score' => 'integer',
            'total_gross_written_idr' => 'integer',
            'total_commission_earned_idr' => 'integer',
        ];
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    /** @return HasMany<MarketCommission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(MarketCommission::class, 'broker_id');
    }
}
