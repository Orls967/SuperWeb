<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class VenueCashlessWallet extends Model
{
    protected $table = 'ven_cashless_wallets';

    protected $guarded = ['id'];

    protected $casts = [
        'balance_idr' => 'integer',
        'total_topped_up_idr' => 'integer',
        'total_spent_idr' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function wristband(): BelongsTo
    {
        return $this->belongsTo(VenueWristband::class, 'wristband_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(VenueCashlessTransaction::class, 'wallet_id');
    }
}
