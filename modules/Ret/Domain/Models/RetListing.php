<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shared\Domain\Traits\HasUuid;

class RetListing extends Model
{
    use HasUuid;

    protected $table = 'ret_listings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'seller_id',
        'channel_id',
        'sku',
        'title',
        'price_minor',
        'map_price_minor',
        'is_active',
        'idempotency_key',
    ];

    protected $casts = [
        'price_minor' => 'integer',
        'map_price_minor' => 'integer',
        'is_active' => 'boolean',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(RetSeller::class, 'seller_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(RetChannel::class, 'channel_id');
    }
}
