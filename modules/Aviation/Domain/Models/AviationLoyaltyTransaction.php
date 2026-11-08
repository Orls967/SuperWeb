<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AviationLoyaltyTransaction extends Model
{
    public $timestamps = false;

    protected $table = 'avi_loyalty_transactions';

    protected $fillable = [
        'loyalty_account_id',
        'transaction_reference',
        'transaction_type',
        'miles_delta',
        'tier_miles_delta',
        'running_balance',
        'flight_ticket_id',
        'nonce_claim_hash',
        'created_at',
    ];

    protected $casts = [
        'miles_delta' => 'integer',
        'tier_miles_delta' => 'integer',
        'running_balance' => 'integer',
        'created_at' => 'datetime',
    ];

    public function loyaltyAccount(): BelongsTo
    {
        return $this->belongsTo(AviationLoyaltyAccount::class, 'loyalty_account_id');
    }
}
