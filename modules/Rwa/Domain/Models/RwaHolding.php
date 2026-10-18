<?php

declare(strict_types=1);

namespace Modules\Rwa\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RwaHolding extends Model
{
    protected $table = 'rwa_holdings';

    protected $fillable = [
        'rwa_asset_id',
        'user_id',
        'is_kyc_verified',
        'token_balance',
        'locked_balance',
        'lock_expires_at',
    ];

    protected $casts = [
        'is_kyc_verified' => 'boolean',
        'token_balance' => 'integer',
        'locked_balance' => 'integer',
        'lock_expires_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(RwaAsset::class, 'rwa_asset_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
