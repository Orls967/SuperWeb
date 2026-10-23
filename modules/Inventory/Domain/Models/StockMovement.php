<?php

declare(strict_types=1);

namespace Modules\Inventory\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Domain\Enums\StockMovementReason;

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
        'reference_movement_id',
        'note',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'qty' => 'integer',
        'reason' => StockMovementReason::class,
        'reference_movement_id' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            throw new \RuntimeException('StockMovement adalah catatan immutable (append-only) dan tidak boleh diubah.');
        });

        static::deleting(function (self $model): void {
            throw new \RuntimeException('StockMovement adalah catatan immutable (append-only) dan tidak boleh dihapus.');
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo('Modules\Store\Domain\Models\Product', 'product_id');
    }

    public function referenceMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reference_movement_id');
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
