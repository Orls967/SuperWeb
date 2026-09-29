<?php

declare(strict_types=1);

namespace Modules\Inventory\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Store\Domain\Models\Product;

class StockMovement extends Model
{
    use HasFactory;

    protected $table = 'inv_stock_movements';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'qty',
        'reason',
        'source_type',
        'source_id',
        'note',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'qty' => 'integer',
        'reason' => StockMovementReason::class,
        'created_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
