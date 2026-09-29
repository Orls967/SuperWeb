<?php

declare(strict_types=1);

namespace Modules\Crypto\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Crypto\Domain\Enums\TradeSide;

class CryptoQuote extends Model
{
    protected $table = 'crypto_quotes';

    protected $fillable = [
        'uuid',
        'user_id',
        'asset_id',
        'side',
        'price_idr',
        'quantity',
        'gross_idr',
        'fee_idr',
        'expires_at',
        'is_executed',
    ];

    protected $casts = [
        'side' => TradeSide::class,
        'price_idr' => 'string',
        'quantity' => 'string',
        'gross_idr' => 'string',
        'fee_idr' => 'string',
        'expires_at' => 'datetime',
        'is_executed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CryptoAsset::class, 'asset_id');
    }

    public function isExpired(): bool
    {
        return now()->isAfter($this->expires_at);
    }

    public function secondsRemaining(): int
    {
        return (int) max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }
}
