<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shared\Domain\Traits\HasUuid;

class RetOrderItem extends Model
{
    use HasUuid;

    protected $table = 'ret_order_items';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'order_id',
        'sku',
        'quantity',
        'unit_price_minor',
        'subtotal_minor',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_minor' => 'integer',
        'subtotal_minor' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(RetOrder::class, 'order_id');
    }
}
