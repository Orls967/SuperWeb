<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Resto\Domain\Enums\ConsumedState;
use Modules\Resto\Domain\Enums\ItemSource;

class OrderItem extends Model
{
    protected $table = 'resto_order_items';

    protected $fillable = [
        'order_id',
        'menu_item_id',
        'tray_id',
        'name_snapshot',
        'unit_price_snapshot',
        'qty',
        'line_total',
        'source',
        'consumed_state',
        'cogs_snapshot',
    ];

    protected $casts = [
        'source' => ItemSource::class,
        'consumed_state' => ConsumedState::class,
        'unit_price_snapshot' => 'integer',
        'qty' => 'integer',
        'line_total' => 'integer',
        'cogs_snapshot' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function tray(): BelongsTo
    {
        return $this->belongsTo(DisplayTray::class, 'tray_id');
    }
}
