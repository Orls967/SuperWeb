<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Supplier extends Model
{
    protected $table = 'resto_suppliers';

    protected $fillable = [
        'uuid',
        'name',
        'contact',
        'terms_days',
        'is_active',
        'rating',
    ];

    protected $casts = [
        'terms_days' => 'integer',
        'is_active' => 'boolean',
        'rating' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Supplier $supplier) {
            if (empty($supplier->uuid)) {
                $supplier->uuid = (string) Str::uuid();
            }
        });
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id');
    }
}
