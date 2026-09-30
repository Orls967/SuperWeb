<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemOutlet extends Model
{
    protected $table = 'resto_menu_item_outlet';

    protected $fillable = [
        'menu_item_id',
        'outlet_id',
        'price_override',
        'is_available',
    ];

    protected $casts = [
        'price_override' => 'integer',
        'is_available' => 'boolean',
    ];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }
}
