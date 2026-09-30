<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Resto\Domain\Enums\ReceiptQuality;

class GoodsReceipt extends Model
{
    protected $table = 'resto_goods_receipts';

    protected $fillable = [
        'uuid',
        'po_id',
        'received_at',
        'received_by',
        'note',
        'quality',
        'photo_ref',
    ];

    protected $casts = [
        'quality' => ReceiptQuality::class,
        'received_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (GoodsReceipt $receipt) {
            if (empty($receipt->uuid)) {
                $receipt->uuid = (string) Str::uuid();
            }
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class, 'receipt_id');
    }
}
