<?php

declare(strict_types=1);

namespace Modules\Crypto\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Crypto\Domain\Enums\AlertCondition;

class CryptoAlert extends Model
{
    protected $table = 'crypto_alerts';

    protected $fillable = [
        'user_id',
        'asset_id',
        'condition',
        'target_price_idr',
        'is_triggered',
        'triggered_at',
    ];

    protected $casts = [
        'condition' => AlertCondition::class,
        'target_price_idr' => 'string',
        'is_triggered' => 'boolean',
        'triggered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CryptoAsset::class, 'asset_id');
    }
}
