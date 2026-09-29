<?php

declare(strict_types=1);

namespace Modules\Crypto\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CryptoPriceTick extends Model
{
    protected $table = 'crypto_price_ticks';

    protected $fillable = [
        'asset_id',
        'price_idr',
        'recorded_at',
    ];

    protected $casts = [
        'price_idr' => 'string',
        'recorded_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CryptoAsset::class, 'asset_id');
    }
}
