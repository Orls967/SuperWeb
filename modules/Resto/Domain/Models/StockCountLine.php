<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountLine extends Model
{
    protected $table = 'resto_stock_count_lines';

    protected $fillable = [
        'count_id',
        'ingredient_id',
        'system_qty',
        'counted_qty',
        'variance',
        'variance_value',
    ];

    protected $casts = [
        'system_qty' => 'string',
        'counted_qty' => 'string',
        'variance' => 'string',
        'variance_value' => 'integer',
    ];

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class, 'count_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }
}
