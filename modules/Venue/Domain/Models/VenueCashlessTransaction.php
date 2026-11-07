<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VenueCashlessTransaction extends Model
{
    public $timestamps = false;

    protected $table = 'ven_cashless_transactions';

    protected $guarded = ['id'];

    protected $casts = [
        'amount_idr' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(VenueCashlessWallet::class, 'wallet_id');
    }
}
