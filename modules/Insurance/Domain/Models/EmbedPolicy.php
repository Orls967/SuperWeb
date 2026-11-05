<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Party\Contracts\Party;

class EmbedPolicy extends Model
{
    protected $table = 'ins_embed_policies';

    /** @var list<string> */
    protected $fillable = [
        'policy_number',
        'embedded_product_id',
        'customer_party_id',
        'broker_party_id',
        'context_type',
        'context_ref',
        'sum_insured_idr',
        'premium_idr',
        'broker_commission_idr',
        'status',
        'starts_at',
        'expires_at',
        'ledger_transaction_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sum_insured_idr' => 'integer',
            'premium_idr' => 'integer',
            'broker_commission_idr' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EmbedProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(EmbedProduct::class, 'embedded_product_id');
    }

    /** @return BelongsTo<Party, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'customer_party_id');
    }

    /** @return BelongsTo<Party, $this> */
    public function broker(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'broker_party_id');
    }

    /** @return HasMany<MarketCommission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(MarketCommission::class, 'embedded_policy_id');
    }
}
