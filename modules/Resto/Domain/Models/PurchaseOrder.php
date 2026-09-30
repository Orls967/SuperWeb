<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Resto\Domain\Enums\POStatus;

class PurchaseOrder extends Model
{
    protected $table = 'resto_purchase_orders';

    protected $fillable = [
        'uuid',
        'number',
        'outlet_id',
        'supplier_id',
        'status',
        'expected_at',
        'subtotal',
        'ppn',
        'grand_total',
        'paid_amount',
        'created_by',
    ];

    protected $casts = [
        'status' => POStatus::class,
        'expected_at' => 'datetime',
        'subtotal' => 'integer',
        'ppn' => 'integer',
        'grand_total' => 'integer',
        'paid_amount' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $po) {
            if (empty($po->uuid)) {
                $po->uuid = (string) Str::uuid();
            }
        });
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'po_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'po_id');
    }

    public function remainingPayable(): int
    {
        return max(0, $this->grand_total - $this->paid_amount);
    }
}
