<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Resto\Domain\Enums\TransferStatus;

class StockTransfer extends Model
{
    protected $table = 'resto_stock_transfers';

    protected $fillable = [
        'uuid',
        'number',
        'from_outlet_id',
        'to_outlet_id',
        'status',
        'shipped_at',
        'received_at',
        'shipped_by',
        'received_by',
        'lines',
        'qty_shipped',
        'qty_received',
        'variance_note',
    ];

    protected $casts = [
        'status' => TransferStatus::class,
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
        'lines' => 'array',
        'qty_shipped' => 'string',
        'qty_received' => 'string',
    ];

    protected static function booted(): void
    {
        static::creating(function (StockTransfer $transfer) {
            if (empty($transfer->uuid)) {
                $transfer->uuid = (string) Str::uuid();
            }
        });
    }

    public function fromOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'from_outlet_id');
    }

    public function toOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'to_outlet_id');
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
